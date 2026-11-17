<?php

declare(strict_types=1);

namespace App\Security;

use DateTimeImmutable;
use Beffroi\Core\Domain\Event\PasskeyUsed;
use App\Controller\PasskeyLoginController;
use Beffroi\Core\Domain\Event\AuthenticationFailureReason;
use Beffroi\Core\Domain\Session\AuthenticationMethod;
use Beffroi\Core\Domain\Session\RequestContext;
use Beffroi\Core\Port\CredentialRepositoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Throwable;

final readonly class PasskeyLogin implements AnnouncesItsRefusalInterface, DatesItsCredentialInterface, OfferedLoginInterface
{
    public function kind(): string
    {
        return 'passkey';
    }

    public function __construct(
        private CredentialRepositoryInterface $credentials,
        private EventDispatcherInterface $events,
    ) {
    }

    public function proofs(Passport $passport): ?array
    {
        $badge = $passport->getBadge(PasskeyCredentials::class);
        if (! $badge instanceof PasskeyCredentials) {
            return null;
        }

        return $badge->assertion->credential->methods($badge->assertion->authenticatorData);
    }

    public function everyProvableMethod(): array
    {
        return [
            new AuthenticationMethod(AuthenticationMethod::HARDWARE_KEY),
            new AuthenticationMethod(AuthenticationMethod::SOFTWARE_KEY),
            new AuthenticationMethod(AuthenticationMethod::USER_PRESENCE),
            new AuthenticationMethod(AuthenticationMethod::MULTIPLE_FACTORS),
        ];
    }

    /**
     * WebAuthn Level 3 §7.2, step 26
     */
    public function recordUse(Passport $passport, SecurityUser $user, DateTimeImmutable $now, RequestContext $context): bool
    {
        $badge = $passport->getBadge(PasskeyCredentials::class);
        if (! $badge instanceof PasskeyCredentials) {
            return false;
        }
        $credential = $badge->assertion->credential;
        $credential->recordUse($badge->assertion->authenticatorData, $now);
        $this->credentials->save($credential);
        $this->events->dispatch(new PasskeyUsed($credential, $now, $context));

        return true;
    }

    public function route(): string
    {
        return PasskeyLoginController::ROUTE;
    }

    public function refusal(AuthenticationException $exception, ?Passport $passport): ?LoginFailure
    {
        for ($cause = $exception; $cause instanceof Throwable; $cause = $cause->getPrevious()) {
            if ($cause instanceof PasskeyLoginException) {
                return new LoginFailure($cause->method, $cause->reason, null, $cause->user);
            }
        }
        $badge = $passport?->getBadge(PasskeyCredentials::class);
        if (! $badge instanceof PasskeyCredentials || AuthenticationFailureCause::of($exception) !== AuthenticationFailureReason::DisabledUser) {
            return null;
        }
        $credential = $badge->assertion->credential;

        return new LoginFailure(
            $credential->methods($badge->assertion->authenticatorData)[0],
            AuthenticationFailureReason::DisabledUser,
            null,
            $credential->user,
        );
    }
}
