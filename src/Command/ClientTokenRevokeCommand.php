<?php

declare(strict_types=1);

namespace App\Command;

use Beffroi\Oidc\Application\Client\Registration\InitialAccessTokenRevocation;
use function sprintf;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;
use function trim;

#[AsCommand(
    name: self::NAME,
    description: 'Revoke an initial access token by its identifier; it authorises no registration from that instant.',
)]
final readonly class ClientTokenRevokeCommand
{
    public const string NAME = 'beffroi:client:token:revoke';

    public function __construct(
        private ConfiguredTenant $tenant,
        private InitialAccessTokenRevocation $revocation,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The identifier of the token, as "beffroi:client:token:list" shows it.')]
        string $id,
    ): int {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }
        $named = trim($id);
        if (! Uuid::isValid($named)) {
            $io->error(sprintf('"%s" is not the identifier of an initial access token.', $named));

            return Command::INVALID;
        }

        $token = $this->revocation->revoke($tenant, Uuid::fromString($named));
        if ($token === null) {
            $io->error(sprintf('The tenant "%s" has no initial access token "%s".', $tenant->slug, $named));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'The initial access token "%s" is revoked; it authorises no registration, and the %d client(s) it registered stay.',
            $token->name,
            $token->registrationsServed,
        ));

        return Command::SUCCESS;
    }
}
