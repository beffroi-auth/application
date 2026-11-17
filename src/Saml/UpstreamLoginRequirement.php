<?php

declare(strict_types=1);

namespace App\Saml;

use function array_any;
use App\Http\StepUpOffer;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Session\AuthenticationMethod;
use Beffroi\Core\Domain\Session\AuthenticationProofs;
use Beffroi\Core\Domain\Session\AuthenticationSession;
use Beffroi\Core\Domain\Session\RequestContext;
use Beffroi\Oidc\Application\Authorization\AuthenticationRequirement;
use Beffroi\Oidc\Domain\Authorization\Prompt;
use Beffroi\Oidc\Domain\Interaction\Interaction;
use Beffroi\Oidc\Domain\Interaction\LoginRequirement;
use Beffroi\Oidc\Exception\OAuth2Exception;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class UpstreamLoginRequirement
{
    public function __construct(
        private AuthenticationRequirement $requirement,
    ) {
    }

    /**
     * beffroi/saml S-49
     *
     * @param Interaction|null $interaction null for the login a page waits for
     *
     * @throws OAuth2Exception when the authorization request is one the login page answers with an error
     */
    public function forcesALogin(?Interaction $interaction, ?AuthenticationSession $session, DateTimeImmutable $now): bool
    {
        if (! $session instanceof AuthenticationSession) {
            return $interaction?->request->hasPrompt(Prompt::Login) ?? false;
        }
        if (! $interaction instanceof Interaction) {
            return true;
        }

        return $this->requirement->of($this->requirement->beforeAnyLogin($interaction->request), $interaction, $session, $now) !== null;
    }

    /**
     * beffroi/saml S-58
     *
     * @param non-empty-list<AuthenticationMethod> $methods
     *
     * @throws OAuth2Exception when the authorization request is one the login page answers with an error
     */
    public function stillAsked(
        Interaction $interaction,
        User $person,
        array $methods,
        DateTimeImmutable $authenticatedAt,
        ?AuthenticationSession $held,
        RequestContext $context,
        DateTimeImmutable $now,
    ): ?LoginRequirement {
        $session = self::leftWith($person, $methods, $authenticatedAt, $held, $context, $now);

        return $this->requirement->of($this->requirement->beforeAnyLogin($interaction->request), $interaction, $session, $now)?->requirement;
    }

    /**
     * beffroi/saml S-59
     *
     * @param non-empty-list<AuthenticationMethod> $methods
     */
    public function meets(
        StepUpOffer $stepUp,
        User $person,
        array $methods,
        DateTimeImmutable $authenticatedAt,
        ?AuthenticationSession $held,
        RequestContext $context,
        DateTimeImmutable $now,
    ): bool {
        $session = self::leftWith($person, $methods, $authenticatedAt, $held, $context, $now);
        $policy = $person->tenant->authenticationContextPolicy;

        return array_any($stepUp->classes, static function (string $name) use ($policy, $session): bool {
            $class = $policy->named($name);

            return $class !== null && $session->satisfies($class);
        });
    }

    /**
     * The session the login would leave the browser with, never kept.
     *
     * @param non-empty-list<AuthenticationMethod> $methods
     */
    private static function leftWith(
        User $person,
        array $methods,
        DateTimeImmutable $authenticatedAt,
        ?AuthenticationSession $held,
        RequestContext $context,
        DateTimeImmutable $now,
    ): AuthenticationSession {
        $provenAt = $authenticatedAt > $now ? $now : $authenticatedAt;
        if ($held instanceof AuthenticationSession && $held->user->id->equals($person->id)) {
            $proofs = $held->proofs;
            foreach ($methods as $method) {
                $proofs = $proofs->with($method, $provenAt);
            }
        } else {
            $proofs = AuthenticationProofs::ofAll($methods, $provenAt);
        }

        return new AuthenticationSession(Uuid::v7(), $person->tenant, $person, $now, $proofs, $context, $now->modify('+1 second'));
    }
}
