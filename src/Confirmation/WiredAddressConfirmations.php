<?php

declare(strict_types=1);

namespace App\Confirmation;

use Beffroi\Core\Application\Confirmation\AddressEstablishment;
use Beffroi\Core\Domain\Identity\User;

/**
 * The kinds of address a person establishes here: those whose channel the deployment turns on, and no other.
 */
final readonly class WiredAddressConfirmations
{
    /**
     * @param iterable<AddressEstablishment> $establishments
     */
    public function __construct(
        private iterable $establishments,
    ) {
    }

    public function named(string $claim): ?AddressEstablishment
    {
        foreach ($this->establishments as $establishment) {
            if ($establishment->claim() === $claim) {
                return $establishment;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> the address that awaits its confirmation, by the claim of its kind
     */
    public function awaitedOf(User $user): array
    {
        $awaited = [];
        foreach ($this->establishments as $establishment) {
            $address = $establishment->awaits($user);
            if ($address !== null) {
                $awaited[$establishment->claim()] = $address;
            }
        }

        return $awaited;
    }
}
