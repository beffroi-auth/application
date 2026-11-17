<?php

declare(strict_types=1);

namespace App\Command;

use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\UserRepositoryInterface;
use function sprintf;
use Symfony\Component\Console\Style\SymfonyStyle;
use function trim;

final readonly class NamedUser
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function orTold(SymfonyStyle $io, Tenant $tenant, string $username): ?User
    {
        $name = trim($username);
        $user = $this->users->findByUsername($tenant, $name);
        if ($user === null) {
            $io->error(sprintf('There is no user named "%s" in the tenant "%s".', $name, $tenant->slug));

            return null;
        }

        return $user;
    }
}
