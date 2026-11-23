<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use function array_key_last;
use function array_values;
use App\Configuration\ConfirmationSection;
use Beffroi\Core\Domain\Identity\EmailAddress;
use Beffroi\Core\Domain\Identity\Profile;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use Psr\Cache\CacheItemPoolInterface;
use function preg_match;
use function sprintf;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\EventListener\NotificationLoggerListener;

/**
 * NIST SP 800-63B-4 §4.2.1.2, decision 0048
 */
final class AccountAddressConfirmationTest extends BrowserTestCase
{
    private const string NUMBER = '+33612345678';

    private const string ADDRESS = 'alice@op.test';

    private const array CHANNELS = [
        'BEFFROI_MAIL_DSN' => 'null://null',
        'BEFFROI_MAIL_SENDER' => 'Beffroi <no-reply@op.test>',
        'BEFFROI_SMS_DSN' => 'null://null',
    ];

    /**
     * @var array<string, array{mixed, mixed}>
     */
    private array $before = [];

    /**
     * The mailer and the notifier log nothing out of debug, which the suite of the application runs without.
     */
    private MessageLoggerListener $mails;

    private NotificationLoggerListener $texts;

    protected function setUp(): void
    {
        foreach (self::CHANNELS as $name => $value) {
            $this->before[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null];
            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
        }
        parent::setUp();
        $this->mails = new MessageLoggerListener();
        $this->texts = new NotificationLoggerListener();
        $dispatcher = self::service('event_dispatcher', EventDispatcherInterface::class);
        $dispatcher->addSubscriber($this->mails);
        $dispatcher->addSubscriber($this->texts);
        // The budgets of the sends are kept between two tests, and every test texts the same number.
        self::service('cache.rate_limiter', CacheItemPoolInterface::class)->clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->before as $name => [$server, $environment]) {
            $_SERVER[$name] = $server;
            $_ENV[$name] = $environment;
        }
    }

    #[Test]
    public function aNumberThePersonWritesAwaitsItsConfirmation(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $page = $this->writeTheNumber();

        // Then
        static::assertSame([self::NUMBER], $page->filter('li[data-confirmation="phone_number"] [data-detail]')->extract(['_text']));
    }

    #[Test]
    public function theCodeTextedToTheNumberThePersonWroteEstablishesIt(): void
    {
        // Given
        $alice = self::user();
        $this->signIn($alice);
        $this->writeTheNumber();
        $this->sendTheCode('phone_number');

        // When
        $this->typeTheCode('phone_number', self::codeIn($this->lastText()));

        // Then
        static::assertSame(self::NUMBER, self::reread($alice)->verifiedPhoneNumber());
    }

    #[Test]
    public function theLinkTextedToTheNumberThePersonWroteEstablishesIt(): void
    {
        // Given
        $alice = self::user();
        $this->signIn($alice);
        $this->writeTheNumber();
        $this->sendTheCode('phone_number');
        $page = $this->page(self::linkIn($this->lastText()));

        // When
        $this->browser->submit($page->filter('form[data-confirmation="link"]')->form());

        // Then
        $this->afterTheForm();
        static::assertSame(self::NUMBER, self::reread($alice)->verifiedPhoneNumber());
    }

    #[Test]
    public function openingTheLinkEstablishesNothing(): void
    {
        // Given
        $alice = self::user();
        $this->signIn($alice);
        $this->writeTheNumber();
        $this->sendTheCode('phone_number');

        // When
        $this->page(self::linkIn($this->lastText()));

        // Then
        static::assertNull(self::reread($alice)->verifiedPhoneNumber());
    }

    #[Test]
    public function theLinkOpensNothingToABrowserThatHoldsNoSessionOfThePerson(): void
    {
        // Given
        $alice = self::user();
        $this->signIn($alice);
        $this->writeTheNumber();
        $this->sendTheCode('phone_number');
        $this->browser->getCookieJar()->clear();

        // When
        $this->browser->request('GET', self::linkIn($this->lastText()));

        // Then
        static::assertSame(Response::HTTP_UNAUTHORIZED, $this->browser->getResponse()->getStatusCode());
        static::assertNull(self::reread($alice)->verifiedPhoneNumber());
    }

    #[Test]
    public function theTextCarriesTheCodeAndTheLinkToThePageOfTheProvider(): void
    {
        // Given
        $this->signIn(self::user());
        $this->writeTheNumber();

        // When
        $this->sendTheCode('phone_number');

        // Then
        static::assertMatchesRegularExpression(
            '#\b\d{6}\b.*\nhttps://op\.test/account/addresses/phone_number/[0-9a-f-]{36}/[A-Za-z0-9_-]{43}$#s',
            $this->lastText(),
        );
    }

    /**
     * Architecture §5.7, OIDC Core §5.1
     */
    #[Test]
    public function theTextIsWrittenInTheLanguageThePersonReads(): void
    {
        // Given
        $alice = self::user();
        $alice->updateProfile(new Profile(locale: 'fr'), self::now());
        self::service('test.beffroi.users', UserRepositoryInterface::class)->save($alice);
        $this->signIn($alice);
        $this->writeTheNumber();

        // When
        $this->sendTheCode('phone_number');

        // Then
        static::assertStringContainsString('Votre code de confirmation : ', $this->lastText());
    }

    /**
     * Decision 0006
     */
    #[Test]
    public function aWrongCodeIsRefusedInOneSentence(): void
    {
        // Given
        $alice = self::user();
        $this->signIn($alice);
        $this->writeTheNumber();
        $this->sendTheCode('phone_number');
        $code = self::codeIn($this->lastText());

        // When
        $page = $this->typeTheCode('phone_number', $code === '000000' ? '000001' : '000000');

        // Then
        static::assertSame(['address_confirmation_refused'], $page->filter('[data-refusal]')->extract(['data-refusal']));
        static::assertNull(self::reread($alice)->verifiedPhoneNumber());
    }

    /**
     * Decision 0014
     */
    #[Test]
    public function aCodePastTheBudgetOfThePersonIsNotTexted(): void
    {
        // Given
        $this->signIn(self::user());
        $this->writeTheNumber();
        for ($send = 1; $send <= ConfirmationSection::DEFAULT_PERSON_LIMIT; ++$send) {
            $this->sendTheCode('phone_number');
        }

        // When
        $this->sendTheCode('phone_number');

        // Then
        static::assertCount(ConfirmationSection::DEFAULT_PERSON_LIMIT, $this->texts->getEvents()->getMessages());
    }

    /**
     * Decision 0014
     */
    #[Test]
    public function aCodePastTheBudgetOfThePersonIsRefusedInOneSentence(): void
    {
        // Given
        $this->signIn(self::user());
        $this->writeTheNumber();
        for ($send = 1; $send <= ConfirmationSection::DEFAULT_PERSON_LIMIT; ++$send) {
            $this->sendTheCode('phone_number');
        }

        // When
        $page = $this->sendTheCode('phone_number');

        // Then
        static::assertSame(['Too many codes sent. Try again later.'], $page->filter('[data-refusal="confirmation_withheld"]')->extract(['_text']));
    }

    #[Test]
    public function aNumberEstablishedAwaitsNoConfirmationAnyMore(): void
    {
        // Given
        $this->signIn(self::user());
        $this->writeTheNumber();
        $this->sendTheCode('phone_number');

        // When
        $page = $this->typeTheCode('phone_number', self::codeIn($this->lastText()));

        // Then
        static::assertCount(0, $page->filter('li[data-confirmation="phone_number"]'));
    }

    #[Test]
    public function theCodeMailedToAnAddressNobodyVerifiedEstablishesIt(): void
    {
        // Given
        $alice = $this->aliceWithAnAddressNobodyVerified();
        $this->signIn($alice);
        $this->sendTheCode('email');

        // When
        $this->typeTheCode('email', self::codeIn($this->lastMail()));

        // Then
        static::assertSame(self::ADDRESS, self::reread($alice)->verifiedEmail()?->value);
    }

    #[Test]
    public function theLinkMailedToAnAddressNobodyVerifiedEstablishesIt(): void
    {
        // Given
        $alice = $this->aliceWithAnAddressNobodyVerified();
        $this->signIn($alice);
        $this->sendTheCode('email');
        $page = $this->page(self::linkIn($this->lastMail()));

        // When
        $this->browser->submit($page->filter('form[data-confirmation="link"]')->form());

        // Then
        $this->afterTheForm();
        static::assertSame(self::ADDRESS, self::reread($alice)->verifiedEmail()?->value);
    }

    #[Test]
    public function theMailGoesToTheAddressItEstablishes(): void
    {
        // Given
        $this->signIn($this->aliceWithAnAddressNobodyVerified());

        // When
        $this->sendTheCode('email');

        // Then
        $mails = $this->mails();
        static::assertCount(1, $mails);
        static::assertSame(self::ADDRESS, $mails[0]->getTo()[0]->getAddress());
    }

    private function writeTheNumber(): Crawler
    {
        $form = $this->page('/account/profile')->filter('form[data-editable="phone_number"]')->form();
        $form->setValues([
            'claim[value]' => self::NUMBER,
        ]);
        $this->browser->submit($form);

        return $this->afterTheForm();
    }

    private function sendTheCode(string $claim): Crawler
    {
        $this->browser->submit($this->page('/account/profile')->filter(sprintf('li[data-confirmation="%s"] form', $claim))->form());

        return $this->afterTheForm();
    }

    private function typeTheCode(string $claim, string $code): Crawler
    {
        $form = $this->page(sprintf('/account/addresses/%s/confirmation', $claim))->filter('form[data-confirmation="code"]')->form();
        $form->setValues([
            'code' => $code,
        ]);
        $this->browser->submit($form);

        return $this->afterTheForm();
    }

    private function aliceWithAnAddressNobodyVerified(): User
    {
        $alice = self::user();
        $alice->changeEmail(new EmailAddress(self::ADDRESS), false, self::now());
        self::service('test.beffroi.users', UserRepositoryInterface::class)->save($alice);

        return $alice;
    }

    private function lastText(): string
    {
        $texts = array_values($this->texts->getEvents()->getMessages());
        static::assertNotSame([], $texts, 'No text was sent.');

        return $texts[array_key_last($texts)]->getSubject();
    }

    /**
     * @return list<Email>
     */
    private function mails(): array
    {
        $mails = [];
        foreach ($this->mails->getEvents()->getMessages() as $mail) {
            static::assertInstanceOf(Email::class, $mail);
            $mails[] = $mail;
        }

        return $mails;
    }

    private function lastMail(): string
    {
        $mails = $this->mails();
        static::assertNotSame([], $mails, 'No mail was written.');

        return self::string($mails[array_key_last($mails)]->getTextBody());
    }

    private static function codeIn(string $message): string
    {
        static::assertSame(1, preg_match('/\b(\d{6})\b/', $message, $found), $message);

        return $found[1];
    }

    private static function linkIn(string $message): string
    {
        static::assertSame(1, preg_match('#https://op\.test(/account/addresses/\S+)#', $message, $found), $message);

        return $found[1];
    }
}
