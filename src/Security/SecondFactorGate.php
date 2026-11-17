<?php

declare(strict_types=1);

namespace App\Security;

use DateTimeImmutable;
use App\Controller\LoginCodeController;
use App\Http\InteractionPages;
use App\Http\PendingLoginStore;
use Beffroi\Core\Application\Authentication\SecondFactorRequirement;
use Beffroi\Core\Domain\Session\AuthenticationMethod;
use Beffroi\Core\Domain\Session\PendingLogin;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Holds the password login of a user with a second factor for the code page, dropping the token the firewall set.
 */
final readonly class SecondFactorGate
{
    public function __construct(
        private SecondFactorRequirement $requirement,
        private PendingLoginStore $pendingLogins,
        private TokenStorageInterface $tokens,
        private InteractionPages $pages,
    ) {
    }

    /**
     * @param non-empty-list<AuthenticationMethod> $methods
     */
    public function holds(LoginSuccessEvent $event, SecurityUser $user, array $methods, DateTimeImmutable $now): bool
    {
        if (! $event->getPassport()->hasBadge(VerifiedPassword::class) || ! $this->requirement->requiredFor($user->user())) {
            return false;
        }
        $request = $event->getRequest();
        $this->pendingLogins->hold($request->getSession(), PendingLogin::provenAt($user->id, $methods, $now));
        $this->tokens->setToken(null);
        $event->setResponse($this->pages->redirectToPage(LoginCodeController::ROUTE, $request));

        return true;
    }
}
