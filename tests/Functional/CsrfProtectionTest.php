<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Beffroi\Core\Domain\Credential\PasswordCredential;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\AuthenticationSessionRepositoryInterface;
use Beffroi\Core\Port\CredentialRepositoryInterface;
use Beffroi\Core\Port\PasswordHasherInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

final class CsrfProtectionTest extends BrowserTestCase
{
    private const string PASSWORD = 'correct horse battery staple';

    #[Test]
    public function aLoginPostedWithoutOriginOrRefererOpensASession(): void
    {
        // Given
        $user = self::userWithPassword('alice', self::PASSWORD);
        $this->authorize();
        $form = $this->browser->followRedirect()->filter('form[action^="/login?"]')->form();
        // The test browser sends the page it is on as the referrer, which this page forbids.
        $this->browser->getHistory()->clear();

        // When
        $this->browser->submit(
            $form,
            [
                '_username' => 'alice',
                '_password' => self::PASSWORD,
            ],
            [
                'HTTP_ORIGIN' => 'null',
            ],
        );

        // Then
        static::assertCount(
            1,
            self::service('test.beffroi.sessions', AuthenticationSessionRepositoryInterface::class)->findActiveByUser($user, self::now()),
        );
    }

    private static function userWithPassword(string $username, string $password): User
    {
        $user = self::user($username);
        $hash = self::service('test.beffroi.password_hasher', PasswordHasherInterface::class)->hash($password);
        self::service('test.beffroi.credentials', CredentialRepositoryInterface::class)
            ->save(
                new PasswordCredential(Uuid::v7(), $user, $hash, self::now()),
            );

        return $user;
    }
}
