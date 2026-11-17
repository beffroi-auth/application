<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\BrowserSession;
use App\Http\FormToken;
use App\Http\LoginTarget;
use App\Http\PendingApprovalsPage;
use App\Http\RequestContextFactory;
use App\Http\RequestParameters;
use App\Http\StepUpOffer;
use Beffroi\Oidc\Application\Ciba\BackchannelApproval;
use function is_string;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * CIBA Core §10.1, BCP 10027 §5.2
 *
 * A request is found by who the user is and named only by a handle of their own list: no link carries one.
 */
#[AsController]
final readonly class PendingApprovalsController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_approvals';

    public const string CSRF_TOKEN_ID = 'beffroi_approvals';

    public const string REQUEST_PARAMETER = 'request';

    public const string ANSWER_PARAMETER = 'answer';

    public const string ANSWER_ALLOW = 'allow';

    public const string ANSWER_DENY = 'deny';

    public function __construct(
        private TenantProvider $tenants,
        private BackchannelApproval $approval,
        private BrowserSession $browser,
        private RequestContextFactory $contexts,
        private PendingApprovalsPage $page,
        private FormToken $formTokens,
        private LoginTarget $loginTargets,
    ) {
    }

    #[Route('/account/approvals', name: self::ROUTE, methods: ['GET', 'POST'])]
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

        $session = $this->browser->authenticationSession($request, $tenant);
        if ($session === null) {
            return $this->loginTargets->sendToLogin($request->getSession(), self::ROUTE, []);
        }
        $this->loginTargets->forget($request->getSession());
        $user = $session->user;
        if (! $request->isMethod('POST')) {
            return $this->page->renderList($user, $this->approval->awaitingAnAnswerBy($user));
        }

        $handle = $parameters[self::REQUEST_PARAMETER] ?? null;
        $record = is_string($handle) ? $this->approval->awaitingAnAnswerFrom($user, $handle) : null;
        $answer = $parameters[self::ANSWER_PARAMETER] ?? null;
        if ($record === null || $answer !== self::ANSWER_ALLOW && $answer !== self::ANSWER_DENY) {
            return $this->page->renderList(
                $user,
                $this->approval->awaitingAnAnswerBy($user),
                PendingApprovalsPage::NOTHING_TO_ANSWER,
                Response::HTTP_BAD_REQUEST,
            );
        }
        if ($answer === self::ANSWER_DENY) {
            $this->approval->deny($record, $this->contexts->fromRequest($request));

            return $this->page->renderAnswer($record);
        }
        $stepUp = $this->approval->stepUpFor($record, $session);
        if ($stepUp !== null) {
            return $this->loginTargets->sendToLogin($request->getSession(), self::ROUTE, [], StepUpOffer::of($stepUp));
        }
        $this->approval->approve($record, $session, $this->contexts->fromRequest($request));

        return $this->page->renderAnswer($record);
    }
}
