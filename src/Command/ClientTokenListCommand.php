<?php

declare(strict_types=1);

namespace App\Command;

use function array_map;
use DateTimeImmutable;
use Beffroi\Oidc\Domain\Client\Registration\InitialAccessToken;
use Beffroi\Oidc\Port\IssuedInitialAccessTokensInterface;
use Psr\Clock\ClockInterface;
use function sprintf;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: self::NAME,
    description: 'List the initial access tokens of the tenant: what each has served, what it has left and whether it still authorises anything.',
)]
final readonly class ClientTokenListCommand
{
    public const string NAME = 'beffroi:client:token:list';

    public function __construct(
        private ConfiguredTenant $tenant,
        private IssuedInitialAccessTokensInterface $tokens,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }

        $tokens = [...$this->tokens->issuedIn($tenant)];
        if ($tokens === []) {
            $io->text(sprintf('The tenant "%s" has issued no initial access token.', $tenant->slug));

            return Command::SUCCESS;
        }

        $now = $this->clock->now();
        $io->table(
            ['Id', 'Name', 'Issued', 'Expires', 'Served', 'Administration', 'State', 'Gives'],
            array_map(static fn (InitialAccessToken $token): array => [
                $token->id->toRfc4122(),
                PrintedText::of($token->name),
                $token->issuedAt->format('Y-m-d H:i:s'),
                $token->expiresAt->format('Y-m-d H:i:s'),
                sprintf('%d of %d', $token->registrationsServed, $token->registrationsAllowed),
                $token->administration ? 'yes' : 'no',
                self::state($token, $now),
                PrintedMembers::lines($token->granted?->members() ?? []),
            ], $tokens),
        );

        return Command::SUCCESS;
    }

    private static function state(InitialAccessToken $token, DateTimeImmutable $now): string
    {
        return match (true) {
            $token->isRevoked() => sprintf('revoked on %s', $token->revokedAt?->format('Y-m-d')),
            $token->isSpent() => 'spent',
            $token->isExpired($now) => 'expired',
            default => 'authorises a registration',
        };
    }
}
