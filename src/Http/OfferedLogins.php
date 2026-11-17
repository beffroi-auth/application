<?php

declare(strict_types=1);

namespace App\Http;

use App\Security\OfferedLoginInterface;
use Beffroi\Core\Domain\Tenant\Tenant;
use LogicException;
use function array_keys;
use function implode;
use function sprintf;

final readonly class OfferedLogins
{
    /**
     * @param iterable<OfferedLoginInterface> $logins at least one
     */
    public function __construct(
        private iterable $logins,
    ) {
    }

    /**
     * @throws LogicException when the floor of the tenant admits none of the wired logins
     *
     * @return non-empty-list<string>
     */
    public function kinds(Tenant $tenant): array
    {
        $kinds = [];
        $closed = [];
        foreach ($this->logins as $login) {
            if ($tenant->loginFloor->admits($login->everyProvableMethod())) {
                $kinds[$login->kind()] = true;
            } else {
                $closed[$login->kind()] = true;
            }
        }
        if ($kinds === []) {
            throw new LogicException(sprintf(
                'The tenant "%s" requires "%s" of every login and this provider wires no login that proves it; it wires %s.',
                $tenant->slug,
                $tenant->loginFloor->name(),
                $closed === [] ? 'none at all' : implode(', ', array_keys($closed)),
            ));
        }

        return array_keys($kinds);
    }
}
