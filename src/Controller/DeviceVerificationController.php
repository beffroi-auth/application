<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\BrowserSession;
use App\Http\DeviceApprovalPage;
use App\Http\FormToken;
use App\Http\LoginTarget;
use App\Http\RequestContextFactory;
use App\Http\RequestParameters;
use Beffroi\Core\Port\CodeAttemptLimiterInterface;
use Beffroi\Oidc\Application\Device\DeviceApproval;
use Beffroi\Oidc\Domain\Device\DeviceAuthorizationResponse;
use Beffroi\Oidc\Domain\Device\UserCode;
use function is_string;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 8628 §3.3, BCP 10027 §2
 *
 * Nothing is approved on a GET: a link followed or prefetched must not be an approval.
 */
#[AsController]
final readonly class DeviceVerificationController
{
    public const string ROUTE = 'beffroi_interaction_device';

    public const string CSRF_TOKEN_ID = 'beffroi_device';

    public const string ANSWER_PARAMETER = 'answer';

    public const string ANSWER_ALLOW = 'allow';

    public const string ANSWER_DENY = 'deny';

    public function __construct(
        private TenantProvider $tenants,
        private DeviceApproval $approval,
        private BrowserSession $browser,
        private RequestContextFactory $contexts,
        private DeviceApprovalPage $page,
        private FormToken $formTokens,
        private LoginTarget $loginTargets,
        private CodeAttemptLimiterInterface $attempts,
    ) {
    }

    #[Route('/device', name: self::ROUTE, methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $tenant = $this->tenants->provide();
        $parameters = RequestParameters::ofBrowserRequest($request);
        if ($request->isMethod('POST')) {
            $refusal = $this->formTokens->refusal($parameters[FormToken::PARAMETER] ?? null, self::CSRF_TOKEN_ID);
            if ($refusal !== null) {
                return $refusal;
            }
        }
        $typed = $parameters[DeviceAuthorizationResponse::USER_CODE_PARAMETER] ?? null;
        $typed = is_string($typed) ? UserCode::normalize($typed) : '';

        $session = $this->browser->authenticationSession($request, $tenant);
        if ($session === null) {
            return $this->loginTargets->sendToLogin(
                $request->getSession(),
                self::ROUTE,
                UserCode::isWellFormed($typed)
                    ? [
                        DeviceAuthorizationResponse::USER_CODE_PARAMETER => $typed,
                    ] : [],
            );
        }
        $this->loginTargets->forget($request->getSession());
        if ($typed === '') {
            return $this->page->renderForm();
        }
        $shown = UserCode::shown($typed);
        if (! $this->attempts->mayAttempt($session->user)) {
            return $this->page->renderForm(DeviceApprovalPage::TOO_MANY_ATTEMPTS, $shown, Response::HTTP_TOO_MANY_REQUESTS);
        }
        $record = $this->approval->awaitingAnAnswer($tenant, $typed);
        if ($record === null) {
            $this->attempts->attempt($session->user);

            return $this->page->renderForm(DeviceApprovalPage::UNKNOWN_CODE, $shown, Response::HTTP_BAD_REQUEST);
        }

        $answer = $parameters[self::ANSWER_PARAMETER] ?? null;
        if (! $request->isMethod('POST') || $answer !== self::ANSWER_ALLOW && $answer !== self::ANSWER_DENY) {
            return $this->page->renderApproval($record, $session->user, $shown);
        }
        if ($answer === self::ANSWER_DENY) {
            $this->approval->deny($record);
        } else {
            $this->approval->approve($record, $session, $this->contexts->fromRequest($request));
        }

        return $this->page->renderAnswer($record);
    }
}
