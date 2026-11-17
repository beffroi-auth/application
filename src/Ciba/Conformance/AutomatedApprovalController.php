<?php

declare(strict_types=1);

namespace App\Ciba\Conformance;

use App\Doctrine\TenantProvider;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Authentication\AuthenticationFailedException;
use Beffroi\Core\Application\Authentication\PasswordAuthenticator;
use Beffroi\Core\Domain\Session\AuthenticationSession;
use Beffroi\Oidc\Application\Ciba\BackchannelApproval;
use Beffroi\Oidc\Domain\Secret\DrawnSecret;
use function is_string;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use function sprintf;

/**
 * The request is looked up among those awaiting the user who signed in, so none made for another can be answered.
 */
#[AsController]
final readonly class AutomatedApprovalController
{
    public const string ROUTE = 'beffroi_ciba_conformance_approval';

    public const string REQUEST_PARAMETER = 'auth_req_id';

    public const string ANSWER_PARAMETER = 'action';

    public const string ANSWER_ALLOW = 'allow';

    public const string ANSWER_DENY = 'deny';

    public const string USERNAME_PARAMETER = 'username';

    public const string PASSWORD_PARAMETER = 'password';

    public function __construct(
        private TenantProvider $tenants,
        private PasswordAuthenticator $passwords,
        private BackchannelApproval $approval,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route('/approval', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $authRequestId = self::parameter($request, self::REQUEST_PARAMETER);
        $answer = self::parameter($request, self::ANSWER_PARAMETER);
        $username = self::parameter($request, self::USERNAME_PARAMETER);
        $password = self::parameter($request, self::PASSWORD_PARAMETER);
        if ($authRequestId === null || $username === null || $password === null) {
            return self::refused(sprintf(
                'The suite names the request with "%s" and the user with "%s" and "%s".',
                self::REQUEST_PARAMETER,
                self::USERNAME_PARAMETER,
                self::PASSWORD_PARAMETER,
            ));
        }
        if ($answer !== self::ANSWER_ALLOW && $answer !== self::ANSWER_DENY) {
            return self::refused(sprintf('The answer is "%s" or "%s".', self::ANSWER_ALLOW, self::ANSWER_DENY));
        }

        $context = $this->contexts->fromRequest($request);
        try {
            $session = $this->passwords->authenticate($this->tenants->provide(), $username, $password, $context);
        } catch (AuthenticationFailedException) {
            return self::refused(sprintf('The user "%s" did not sign in.', $username));
        }
        if (! $session instanceof AuthenticationSession) {
            return self::refused(sprintf('The user "%s" holds a second factor, and a password alone signs nobody in who does.', $username));
        }

        $record = $this->approval->awaitingAnAnswerFrom($session->user, DrawnSecret::digestOf($authRequestId));
        if ($record === null) {
            return self::refused('No request of that user is waiting for that identifier.');
        }
        if ($answer === self::ANSWER_DENY) {
            $this->approval->deny($record, $context);
        } else {
            if ($this->approval->stepUpFor($record, $session) !== null) {
                return self::refused(sprintf('The request asks for a class the password of "%s" does not prove.', $username));
            }
            $this->approval->approve($record, $session, $context);
        }

        return new JsonResponse([
            'answer' => $answer,
            'client_id' => $record->clientId,
        ]);
    }

    /**
     * From the query: the suite posts the URL it was given with no body.
     */
    private static function parameter(Request $request, string $name): ?string
    {
        $value = $request->query->get($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function refused(string $reason): JsonResponse
    {
        return new JsonResponse([
            'error' => $reason,
        ], Response::HTTP_BAD_REQUEST);
    }
}
