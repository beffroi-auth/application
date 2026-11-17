<?php

declare(strict_types=1);

namespace App\Configuration;

use function array_any;
use Closure;

/**
 * Whether a role the deployment plays, such as the SAML identity provider or web push, is on.
 */
final readonly class RoleSwitch
{
    public function __construct(
        public bool $on,
    ) {
    }

    /**
     * @param list<self> $roles
     */
    public static function anyOf(array $roles): self
    {
        return new self(array_any($roles, static fn (self $role): bool => $role->on));
    }

    /**
     * @template T of object
     *
     * @param Closure(): T $service
     *
     * @return T|null
     */
    public function served(Closure $service): ?object
    {
        return $this->on ? $service() : null;
    }
}
