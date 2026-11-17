<?php

declare(strict_types=1);

namespace App\Identity;

use Beffroi\Core\Domain\Identity\Claim\ClaimInterface;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\ClaimsInterface;
use function array_keys;

final readonly class WiredClaims implements ClaimsInterface
{
    /**
     * @var array<string, ClaimInterface>
     */
    private array $byName;

    /**
     * @param iterable<ClaimInterface> $claims
     */
    public function __construct(iterable $claims)
    {
        $byName = [];
        foreach ($claims as $claim) {
            $byName[$claim->name()] = $claim;
        }
        $this->byName = $byName;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->byName);
    }

    public function named(string $name): ?ClaimInterface
    {
        return $this->byName[$name] ?? null;
    }

    /**
     * @return list<ClaimInterface>
     */
    public function openedTo(User $user): array
    {
        $opened = [];
        foreach ($this->byName as $name => $claim) {
            if ($user->maySayOfThemselves($name)) {
                $opened[] = $claim;
            }
        }

        return $opened;
    }
}
