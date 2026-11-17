<?php

declare(strict_types=1);

namespace App\Security;

use function array_any;
use Beffroi\Core\Domain\Session\AuthenticationMethod;
use Beffroi\Core\Domain\Tenant\Tenant;
use DateTimeImmutable;
use function sprintf;
use Beffroi\Oidc\Port\ProvableMethodsInterface;
use LogicException;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final readonly class PassportProofs implements ProvableMethodsInterface
{
    /**
     * @param iterable<ProvingLoginInterface> $logins
     */
    public function __construct(
        private iterable $logins,
    ) {
    }

    /**
     * @return non-empty-list<AuthenticationMethod>
     */
    public function methods(Passport $passport): array
    {
        foreach ($this->logins as $login) {
            $methods = $login->proofs($passport);
            if ($methods !== null) {
                return $methods;
            }
        }

        throw new LogicException('The firewall authenticated a user of the provider with a login none of the tagged ones recognises: the passport carries the badge of no login of the provider.');
    }

    public function provenAt(Passport $passport): ?DateTimeImmutable
    {
        foreach ($this->logins as $login) {
            $provenAt = $login instanceof DatesItsProofsInterface ? $login->provenAt($passport) : null;
            if ($provenAt !== null) {
                return $provenAt;
            }
        }

        return null;
    }

    public function sessionNotOnOrAfter(Passport $passport): ?DateTimeImmutable
    {
        foreach ($this->logins as $login) {
            $bound = $login instanceof BoundsItsSessionInterface ? $login->sessionNotOnOrAfter($passport) : null;
            if ($bound !== null) {
                return $bound;
            }
        }

        return null;
    }

    /**
     * The floor is asked login by login: a login it closes announces none of its methods.
     *
     * @return non-empty-list<AuthenticationMethod>
     */
    public function provableMethods(Tenant $tenant): array
    {
        $methods = [];
        $wired = 0;
        foreach ($this->logins as $login) {
            ++$wired;
            if (! $tenant->loginFloor->admits($login->everyProvableMethod())) {
                continue;
            }
            foreach ($login->everyProvableMethod() as $method) {
                if (! array_any($methods, static fn (AuthenticationMethod $known): bool => $known->equals($method))) {
                    $methods[] = $method;
                }
            }
        }
        if ($methods === [] && $wired === 0) {
            throw new LogicException('The firewall of the interaction pages offers at least one login.');
        }
        if ($methods === []) {
            throw new LogicException(sprintf(
                'No login of the firewall of the interaction pages proves what the tenant "%s" requires of every login, "%s".',
                $tenant->slug,
                $tenant->loginFloor->name(),
            ));
        }

        return $methods;
    }
}
