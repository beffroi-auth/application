<?php

declare(strict_types=1);

namespace App\Command;

use App\Http\RequestContextFactory;
use Beffroi\Oidc\Application\Client\Registration\InitialAccessTokenIssuance;
use Beffroi\Oidc\Domain\Client\Metadata\GrantedMembers;
use Beffroi\Oidc\Exception\InvalidArgumentException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use function trim;

#[AsCommand(
    name: self::NAME,
    description: 'Issue an initial access token for the tenant, and what it gives the clients it registers; the value is printed once and never again.',
)]
final readonly class ClientTokenIssueCommand
{
    public const string NAME = 'beffroi:client:token:issue';

    public const int DEFAULT_REGISTRATIONS = 1;

    public const int DEFAULT_DAYS = 30;

    public function __construct(
        private ConfiguredTenant $tenant,
        private InitialAccessTokenIssuance $issuance,
        private RequestContextFactory $contexts,
    ) {
    }

    /**
     * @param list<string> $resource
     * @param list<string> $exchangeSubjectTokenType
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'What the tenant calls this token, to know later which integration it was for.')]
        string $name,
        #[Option(description: 'How many clients it may register.')]
        int $registrations = self::DEFAULT_REGISTRATIONS,
        #[Option(description: 'How many days it is worth anything, counted from now.')]
        int $days = self::DEFAULT_DAYS,
        #[Option(description: 'An API the clients it registers may ask a token for, by the identifier RFC 8707 names it with; once per API.')]
        array $resource = [],
        #[Option(
            description: 'The shape of the access tokens of the clients it registers, among those this provider issues; the first one wired when left out.',
        )]
        ?string $accessTokenFormat = null,
        #[Option(description: 'The clients it registers bind every token to a key of theirs, by DPoP or by a certificate.')]
        bool $requireSenderConstrainedTokens = false,
        #[Option(
            description: 'A type of token the clients it registers may exchange for another (RFC 8693), which gives them the token exchange; once per type.',
        )]
        array $exchangeSubjectTokenType = [],
        #[Option(description: 'They may also exchange a token that names a person, and not only the token of a service.')]
        bool $exchangeForAUser = false,
        #[Option(description: 'The subject that may act for the subject of their tokens (RFC 8693 section 4.4), a gateway by its client id.')]
        ?string $mayAct = null,
        #[Option(description: 'The clients it registers are clients of the administration API of the tenant (decision 0038), and authenticate with a key.')]
        bool $administration = false,
    ): int {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }
        if ($exchangeForAUser && $exchangeSubjectTokenType === []) {
            $io->error('A token that lets its clients exchange a token that names a person names what they may exchange: --exchange-subject-token-type.');

            return Command::INVALID;
        }

        try {
            $issued = $this->issuance->issue(
                $tenant,
                trim($name),
                $registrations,
                $days,
                $this->contexts->fromConsole(self::NAME),
                self::granted($resource, $accessTokenFormat, $requireSenderConstrainedTokens, $exchangeSubjectTokenType, $exchangeForAUser, $mayAct),
                $administration,
            );
        } catch (InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        $io->definitionList(
            [
                'Id' => $issued->token->id->toRfc4122(),
            ],
            [
                'Name' => $issued->token->name,
            ],
            [
                'Registrations' => (string) $issued->token->registrationsAllowed,
            ],
            [
                'Expires' => $issued->token->expiresAt->format('Y-m-d H:i:s'),
            ],
            [
                'Administration' => $issued->token->administration ? 'yes' : 'no',
            ],
            [
                'Gives' => PrintedMembers::lines($issued->token->granted?->members() ?? []),
            ],
        );
        $io->success('The initial access token is issued. It is printed once, here, and this provider cannot show it again.');
        $io->writeln($issued->value);

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $resources
     * @param list<string> $exchanged
     *
     * @throws InvalidArgumentException
     */
    private static function granted(
        array $resources,
        ?string $accessTokenFormat,
        bool $requireSenderConstrainedTokens,
        array $exchanged,
        bool $exchangeForAUser,
        ?string $mayAct,
    ): ?GrantedMembers {
        $members = [];
        if ($resources !== []) {
            $members['resources'] = $resources;
        }
        if ($accessTokenFormat !== null) {
            $members['access_token_format'] = $accessTokenFormat;
        }
        if ($requireSenderConstrainedTokens) {
            $members['require_sender_constrained_tokens'] = true;
        }
        if ($exchanged !== []) {
            $members['token_exchange'] = [
                'subject_token_types' => $exchanged,
                'may_act_for_a_user' => $exchangeForAUser,
            ];
        }
        if ($mayAct !== null) {
            $members['may_act'] = $mayAct;
        }

        return $members === [] ? null : new GrantedMembers($members);
    }
}
