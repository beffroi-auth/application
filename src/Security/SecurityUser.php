<?php

declare(strict_types=1);

namespace App\Security;

use Beffroi\Core\Domain\Credential\PasswordCredential;
use Beffroi\Core\Domain\Identity\User;
use function is_bool;
use function is_string;
use LogicException;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    public const string ROLE = 'ROLE_USER';

    public readonly Uuid $id;

    /**
     * @var non-empty-string
     */
    public readonly string $username;

    public readonly bool $enabled;

    private readonly ?string $password;

    private ?User $user;

    private ?PasswordCredential $credential;

    public function __construct(User $user, ?PasswordCredential $credential)
    {
        $this->id = $user->id;
        $this->username = self::username($user->username);
        $this->enabled = $user->isEnabled();
        $this->password = $credential === null ? null : CorePasswordHasher::encode($credential->hash);
        $this->user = $user;
        $this->credential = $credential;
    }

    public function user(): User
    {
        return $this->user ?? throw new LogicException('This user was restored from the session; refresh it through the provider first.');
    }

    public function passwordCredential(): ?PasswordCredential
    {
        if ($this->user === null) {
            throw new LogicException('This user was restored from the session; refresh it through the provider first.');
        }

        return $this->credential;
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /**
     * @return non-empty-list<string>
     */
    public function getRoles(): array
    {
        return [self::ROLE];
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * A password change signs every other browser out, a rename does not.
     */
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self && $user->id->equals($this->id) && $user->password === $this->password;
    }

    /**
     * @return array{id: string, username: string, enabled: bool, password: string|null}
     */
    public function __serialize(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'username' => $this->username,
            'enabled' => $this->enabled,
            'password' => $this->password,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $id = $data['id'] ?? null;
        $username = $data['username'] ?? null;
        $enabled = $data['enabled'] ?? null;
        $password = $data['password'] ?? null;
        if (! is_string($id) || ! Uuid::isValid($id) || ! is_string($username) || ! is_bool($enabled) || $password !== null && ! is_string($password)) {
            throw new LogicException('The serialised user is not one this class wrote.');
        }
        $this->id = Uuid::fromString($id);
        $this->username = self::username($username);
        $this->enabled = $enabled;
        $this->password = $password;
        $this->user = null;
        $this->credential = null;
    }

    /**
     * @return non-empty-string
     */
    private static function username(string $username): string
    {
        if ($username === '') {
            throw new LogicException('A user of the core has a username.');
        }

        return $username;
    }
}
