<?php

declare(strict_types=1);

namespace App\Command;

use DateTimeInterface;
use Beffroi\Core\Domain\Key\TenantKey;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\TenantKeyRepositoryInterface;
use function sprintf;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class RetiringKey
{
    public function __construct(
        private TenantKeyRepositoryInterface $keys,
    ) {
    }

    public function told(SymfonyStyle $io, Tenant $tenant, TenantKey $previous): void
    {
        // Read again: the repository may hand a fresh object on every read, and the rotation just dated this one.
        $retiring = $this->keys->find($tenant, $previous->kid) ?? $previous;
        $io->note(sprintf(
            'The key "%s" was active and is now retiring: it stays in the JWK Set until %s, while what is bound to it may still arrive.',
            $retiring->kid,
            $retiring->retirementDueAt?->format(DateTimeInterface::RFC3339) ?? '?',
        ));
    }
}
