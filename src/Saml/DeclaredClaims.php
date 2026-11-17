<?php

declare(strict_types=1);

namespace App\Saml;

use Beffroi\Core\Domain\Identity\User;
use Beffroi\Saml\Port\ProfileClaimsInterface;
use Beffroi\Saml\Port\ServedClaimsInterface;
use function is_array;
use function is_string;

/**
 * Serves exactly the claims the declarations name, in place of the claims providers a compiler pass cannot
 * build; MappedClaimsWarmer checks them against the real ones.
 */
final readonly class DeclaredClaims implements ServedClaimsInterface, ProfileClaimsInterface
{
    /**
     * @param list<string> $names
     */
    private function __construct(
        private array $names,
    ) {
    }

    /**
     * @param array<array-key, mixed> $declarations
     */
    public static function mappedBy(array $declarations): self
    {
        $names = [];
        foreach ($declarations as $declaration) {
            $attributes = is_array($declaration) ? $declaration['attributes'] ?? null : null;
            if (! is_array($attributes)) {
                continue;
            }
            foreach ($attributes as $attribute) {
                $claim = is_array($attribute) ? $attribute['claim'] ?? null : null;
                if (is_string($claim)) {
                    $names[] = $claim;
                }
            }
        }

        return new self($names);
    }

    /**
     * @param array<array-key, mixed> $declarations
     */
    public static function filledBy(array $declarations): self
    {
        $names = [];
        foreach ($declarations as $declaration) {
            $profile = is_array($declaration) ? $declaration['profile'] ?? null : null;
            if (! is_array($profile)) {
                continue;
            }
            foreach ($profile as $claim) {
                if (is_string($claim)) {
                    $names[] = $claim;
                }
            }
        }

        return new self($names);
    }

    public function names(): array
    {
        return $this->names;
    }

    public function of(User $user, array $names): array
    {
        return [];
    }
}
