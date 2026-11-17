<?php

declare(strict_types=1);

namespace App\Security;

use App\Saml\ServiceProviderRoutes;
use Beffroi\Saml\Domain\Upstream\AuthnContextMethods;
use DateTimeImmutable;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final readonly class UpstreamLogin implements AnnouncesItsRefusalInterface, BoundsItsSessionInterface, DatesItsProofsInterface
{
    public function proofs(Passport $passport): ?array
    {
        return self::badge($passport)?->methods;
    }

    public function everyProvableMethod(): array
    {
        return AuthnContextMethods::everyProvable();
    }

    public function provenAt(Passport $passport): ?DateTimeImmutable
    {
        return self::badge($passport)?->provenAt;
    }

    public function sessionNotOnOrAfter(Passport $passport): ?DateTimeImmutable
    {
        return self::badge($passport)?->sessionNotOnOrAfter;
    }

    public function route(): string
    {
        return ServiceProviderRoutes::ASSERTION_CONSUMER_SERVICE;
    }

    public function refusal(AuthenticationException $exception, ?Passport $passport): ?LoginFailure
    {
        if ($exception instanceof UpstreamLoginRefusedException) {
            return $exception->failure;
        }
        $reason = AuthenticationFailureCause::of($exception);
        if ($passport === null || $reason === null) {
            return null;
        }
        $badge = self::badge($passport);
        $user = $passport->getUser();
        if ($badge === null || ! $user instanceof SecurityUser) {
            return null;
        }

        return new LoginFailure($badge->methods[0], $reason, null, $user->user());
    }

    private static function badge(Passport $passport): ?UpstreamCredentials
    {
        $badge = $passport->getBadge(UpstreamCredentials::class);

        return $badge instanceof UpstreamCredentials ? $badge : null;
    }
}
