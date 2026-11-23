<?php

declare(strict_types=1);

namespace App\Security;

use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\CodeAttemptLimiterInterface;
use Beffroi\Core\Port\PasswordAttemptLimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final readonly class RateLimitedAttempts implements CodeAttemptLimiterInterface, PasswordAttemptLimiterInterface
{
    public const string CODE_LIMITER = 'beffroi_code_attempts';

    public const string PASSWORD_LIMITER = 'beffroi_password_attempts';

    public const string PASSWORD_CHANGE_LIMITER = 'beffroi_password_change_attempts';

    /**
     * @var list<string>
     */
    public const array LIMITERS = [self::CODE_LIMITER, self::PASSWORD_LIMITER, self::PASSWORD_CHANGE_LIMITER];

    public const int LIMIT = 5;

    public const string INTERVAL = '1 minute';

    public function __construct(
        private RateLimiterFactoryInterface $limiters,
    ) {
    }

    /**
     * Consuming zero tokens is always accepted, so what is read is the number of tokens left.
     */
    public function mayAttempt(User $user): bool
    {
        return $this->limiters->create($user->id->toRfc4122())->consume(0)->getRemainingTokens() > 0;
    }

    public function attempt(User $user): bool
    {
        return $this->limiters->create($user->id->toRfc4122())->consume()->isAccepted();
    }

    public function reset(User $user): void
    {
        $this->limiters->create($user->id->toRfc4122())->reset();
    }
}
