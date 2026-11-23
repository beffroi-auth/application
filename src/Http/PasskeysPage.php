<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Application\Credential\DeclaredModels;
use Beffroi\Core\Application\Identity\FederatedDomains;
use Beffroi\Core\Domain\Credential\AuthenticatorTransport;
use Beffroi\Core\Domain\Credential\CredentialQuestion;
use Beffroi\Core\Domain\Credential\PasskeyCredential;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\CanPageCredentialsInterface;
use function array_map;
use Symfony\Component\HttpFoundation\Response;

/**
 * The revoked passkeys are left out unless they are asked for, and come after the others when they are.
 */
final readonly class PasskeysPage
{
    public const string USED_FROM = 'used_from';

    public const string USED_TO = 'used_to';

    public const string STATE = 'state';

    public const string REVOKED = 'revoked';

    public const string EVERY_STATE = 'all';

    public const string MODEL = 'authenticator';

    public const string SYNC = 'sync';

    public const string SYNCED = 'synced';

    public const string DEVICE_BOUND = 'device_bound';

    public const string TRANSPORT = 'transport';

    public function __construct(
        private CanPageCredentialsInterface $credentials,
        private DeclaredModels $models,
        private AskedPage $asked,
        private AskedPeriod $period,
        private AskedWord $word,
        private InteractionPages $pages,
        private FederatedDomains $federated,
    ) {
    }

    public function render(User $user, ?string $error = null, int $status = Response::HTTP_OK): Response
    {
        $question = $this->question($user);

        return $this->pages->render(
            'account/passkeys.html.twig',
            [
                'user' => $user,
                'passkeys' => $this->credentials->pageOfCredentials($user, PasskeyCredential::class, $question, $this->asked->asked()),
                'question' => $question,
                'narrowed' => self::narrows($question),
                'models' => $this->models->of($user, PasskeyCredential::class),
                'transports' => AuthenticatorTransport::cases(),
                'error' => $error,
                'federated' => ! $this->federated->admitsLocalCredentials($user),
            ],
            $status,
        );
    }

    private function question(User $user): CredentialQuestion
    {
        $transports = array_map(static fn (AuthenticatorTransport $transport): string => $transport->value, AuthenticatorTransport::cases());
        $transport = $this->word->among(self::TRANSPORT, $transports);

        return new CredentialQuestion(
            enrolled: $this->period->asked($user),
            used: $this->period->asked($user, self::USED_FROM, self::USED_TO),
            revoked: match ($this->word->among(self::STATE, [self::REVOKED, self::EVERY_STATE])) {
                self::REVOKED => true,
                self::EVERY_STATE => null,
                null => false,
            },
            model: $this->word->uuid(self::MODEL),
            synced: $this->word->either(self::SYNC, self::SYNCED, self::DEVICE_BOUND),
            reachedThrough: $transport === null ? null : AuthenticatorTransport::from($transport),
        );
    }

    private static function narrows(CredentialQuestion $question): bool
    {
        return (
            $question->enrolled !== null
            || $question->used !== null
            || $question->revoked !== false
            || $question->model !== null
            || $question->synced !== null
            || $question->reachedThrough !== null
        );
    }
}
