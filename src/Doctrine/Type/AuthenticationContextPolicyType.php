<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Application\Session\StoredAuthenticationContextPolicy;
use Beffroi\Core\Domain\Session\AuthenticationContextPolicy;
use Beffroi\Core\Port\AuthenticationPredicatesInterface;
use LogicException;

/**
 * @extends JsonObjectType<AuthenticationContextPolicy>
 */
final class AuthenticationContextPolicyType extends JsonObjectType implements NeedsAuthenticationPredicatesInterface
{
    public const string NAME = 'beffroi_authentication_context_policy';

    private static ?AuthenticationPredicatesInterface $predicates = null;

    public static function resolveWith(AuthenticationPredicatesInterface $predicates): void
    {
        self::$predicates = $predicates;
    }

    protected function encode(object $value): array
    {
        return self::policies()->document($value);
    }

    protected function decode(array $data): object
    {
        return self::policies()->of($data);
    }

    protected function expects(): string
    {
        return AuthenticationContextPolicy::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }

    private static function policies(): StoredAuthenticationContextPolicy
    {
        return new StoredAuthenticationContextPolicy(
            self::$predicates ?? throw new LogicException('The authentication predicates have not been handed to the DBAL type; the kernel does it when it boots.'),
        );
    }
}
