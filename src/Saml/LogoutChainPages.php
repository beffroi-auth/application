<?php

declare(strict_types=1);

namespace App\Saml;

use App\EndSession\FrontChannelLogoutRenderer;
use App\Http\FormTarget;
use App\Http\InteractionPages;
use App\Key\KmsUnavailableListener;
use Beffroi\Core\Domain\Session\AuthenticationSession;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Saml\Application\SingleLogout\ProviderLogout;
use Beffroi\Saml\Domain\SingleLogout\LogoutChainEnded;
use Beffroi\Saml\Domain\SingleLogout\LogoutChainSecret;
use Beffroi\Saml\Domain\SingleLogout\LogoutChainStarted;
use Beffroi\Saml\Domain\SingleLogout\RedirectedMessage;
use Beffroi\Saml\Domain\SingleSignOn\PostedForm;
use Beffroi\Saml\Exception\LogoutRefusedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

/**
 * beffroi/saml S-29 to S-36
 */
final readonly class LogoutChainPages
{
    public function __construct(
        private ProviderLogout $logout,
        private FrontChannelLogoutRenderer $frontChannel,
        private InteractionPages $pages,
        private SamlPostPage $postPage,
        private UrlGeneratorInterface $urls,
        private FormTarget $formTargets,
        private LogoutChainCookie $cookie,
    ) {
    }

    /**
     * beffroi/saml S-29, S-35
     *
     * @param list<AuthenticationSession> $sessions
     * @param string|null $continueTo where the logout ends, null for the page that says the browser is signed out
     *
     * @throws LogoutRefusedException when another request took the chain first
     * @throws KeyNotFoundException
     * @throws RuntimeException when the salt of the tenant can neither be read nor drawn
     */
    public function begin(Tenant $tenant, array $sessions, ?string $continueTo): ?Response
    {
        $begun = $this->logout->begin($tenant, $sessions, null, $continueTo);
        if ($begun instanceof LogoutChainStarted) {
            return $this->started($tenant, $begun);
        }

        return $this->frontChannel->render($sessions, $begun->continueTo);
    }

    /**
     * beffroi/saml S-32, S-35
     *
     * @throws LogoutRefusedException when another request took the chain first
     * @throws KeyNotFoundException
     * @throws RuntimeException
     */
    public function started(Tenant $tenant, LogoutChainStarted $started): Response
    {
        $continue = $this->urls->generate(
            IdentityProviderRoutes::SINGLE_LOGOUT_CONTINUE,
            [
                IdentityProviderRoutes::CHAIN_PARAMETER => $started->reference,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $page = $this->frontChannel->render($started->sessions, $continue);
        if ($page !== null) {
            return $this->cookie->given($page, $started->secret);
        }
        try {
            return $this->step($this->logout->resume($tenant, $started->reference, $started->secret), $started->secret);
        } catch (Throwable $failure) {
            if (KmsUnavailableListener::outageBehind($failure) === null) {
                throw $failure;
            }

            return $this->cookie->given($this->pages->redirect($continue), $started->secret);
        }
    }

    /**
     * SAML Bindings §3.4.5.1, beffroi/saml S-32, S-34
     */
    public function step(PostedForm|RedirectedMessage|LogoutChainEnded $step, ?LogoutChainSecret $secret): Response
    {
        if ($step instanceof LogoutChainEnded) {
            return $this->cookie->cleared($this->ended($step));
        }
        $response = $this->message($step);

        return $secret === null ? $response : $this->cookie->given($response, $secret);
    }

    private function message(PostedForm|RedirectedMessage $message): Response
    {
        if ($message instanceof RedirectedMessage) {
            return new RedirectResponse($message->url, Response::HTTP_FOUND, [
                ...InteractionPages::HEADERS,
                'Cache-Control' => 'no-cache, no-store',
                'Pragma' => 'no-cache',
            ]);
        }

        return $this->postPage->logoutMessage($message);
    }

    /**
     * beffroi/saml S-34
     */
    private function ended(LogoutChainEnded $ended): Response
    {
        if ($ended->answer !== null) {
            return $this->message($ended->answer);
        }
        if ($ended->continueTo !== null) {
            return $this->pages->redirect($ended->continueTo);
        }

        return $this->pages->render('logged_out.html.twig', [
            'logged_out' => true,
        ]);
    }

    /**
     * beffroi/saml S-36
     *
     * Names in the "form-action" of the page the origin of each single logout service the chain of $session reaches:
     * a browser checks the directive over every redirection of the submission.
     */
    public function mayEnd(Request $request, AuthenticationSession $session): void
    {
        foreach ($this->logout->destinations($session) as $destination) {
            $this->formTargets->mayReach($request, SamlPostPage::originOf($destination));
        }
    }
}
