<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Beffroi\Core\Domain\Identity\Profile;
use Beffroi\Core\Port\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use function sprintf;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

final class AccountProfileTest extends BrowserTestCase
{
    #[Test]
    public function thePageNeedsASessionOfTheCore(): void
    {
        // When
        $crawler = $this->browser->request('GET', '/account/profile');

        // Then
        static::assertSame(Response::HTTP_UNAUTHORIZED, $this->browser->getResponse()->getStatusCode());
        static::assertSame('login_required', $crawler->filter('p[data-error]')->attr('data-error'));
    }

    #[Test]
    public function thePageOffersAFormPerClaimTheTenantOpens(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $page = $this->profilePage();

        // Then
        static::assertSame(
            [
                'phone_number',
                'name',
                'given_name',
                'family_name',
                'middle_name',
                'nickname',
                'preferred_username',
                'birthdate',
                'gender',
                'locale',
                'zoneinfo',
                'profile',
                'picture',
                'website',
            ],
            $page->filter('form[data-editable]')->extract(['data-editable']),
        );
    }

    /**
     * OIDC Core §5.1
     */
    #[Test]
    #[DataProvider('provideVerifications')]
    public function theFieldOfANumberThePersonMayChangeSaysWhetherItWasVerified(bool $verified, string $said): void
    {
        // Given
        $alice = self::user();
        $alice->changePhoneNumber('+33612345678', $verified, self::now());
        self::service('test.beffroi.users', UserRepositoryInterface::class)->save($alice);
        $this->signIn($alice);

        // When
        $page = $this->profilePage();

        // Then
        static::assertSame(
            [$said],
            $page->filter('form[data-editable="phone_number"] span[data-verified]')->extract(['data-verified']),
        );
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function provideVerifications(): iterable
    {
        yield 'verified' => [true, 'yes'];
        yield 'not verified' => [false, 'no'];
    }

    #[Test]
    public function aRefusedValueIsSaidUnderTheFieldItIsAbout(): void
    {
        // Given
        $alice = self::user();
        $this->signIn($alice);

        // When
        $page = $this->describe('zoneinfo', 'Europe/Atlantis');

        // Then
        static::assertSame(
            ['This value is not valid.'],
            $page->filter('form[data-editable="zoneinfo"] ul li')->extract(['_text']),
        );
        static::assertCount(0, $page->filter('form[data-editable="nickname"] ul li'));
        static::assertNull(self::reread($alice)->profile->zoneinfo);
    }

    /**
     * OIDC Core §5.1, HTML §2.3.5.1
     */
    #[Test]
    public function aBirthdateWhoseYearIsWithheldIsOfferedAsALineOfText(): void
    {
        // Given
        $alice = self::user();
        $alice->updateProfile(new Profile(birthdate: '0000-05-21'), self::now());
        self::service('test.beffroi.users', UserRepositoryInterface::class)->save($alice);
        $this->signIn($alice);

        // When
        $page = $this->profilePage();

        // Then
        $field = $page->filter('form[data-editable="birthdate"] input[name="claim[value]"]');
        static::assertSame(['text'], $field->extract(['type']));
        static::assertSame(['0000-05-21'], $field->extract(['value']));
    }

    #[Test]
    public function whatAPersonSaysOfThemselvesIsWritten(): void
    {
        // Given
        $alice = self::user();
        $this->signIn($alice);

        // When
        $this->describe('nickname', 'Al');

        // Then
        static::assertSame('Al', self::reread($alice)->profile->nickname);
    }

    #[Test]
    public function thePageRunsNoScript(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $page = $this->profilePage();

        // Then
        static::assertCount(0, $page->filter('script'));
        static::assertCount(0, $page->filter('[onclick], [onchange], [onsubmit]'));
    }

    private function describe(string $claim, string $value): Crawler
    {
        $form = $this->profilePage()->filter(sprintf('form[data-editable="%s"]', $claim))->form();
        $form->setValues([
            'claim[value]' => $value,
        ]);
        $this->browser->submit($form);

        return $this->afterTheForm();
    }

    private function profilePage(): Crawler
    {
        return $this->page('/account/profile');
    }
}
