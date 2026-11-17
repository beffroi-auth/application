<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Oidc\Domain\Resource\ProtectedResourceErrorResponse;
use Beffroi\Oidc\Domain\Token\JwtPayload;
use Beffroi\Oidc\Domain\UserInfo\UserInfoResponse;
use Beffroi\Oidc\Exception\MissingBearerTokenException;
use Beffroi\Oidc\Exception\OAuth2Exception;
use Beffroi\Oidc\Port\ChallengeParametersInterface;
use Beffroi\Oidc\Port\ServedTokenSchemesInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer of a protected resource of the provider that refuses the token of a request, the UserInfo endpoint and
 * the administration API alike (RFC 6750 §3, RFC 9449 §7.1): the challenge of every scheme it serves, with the
 * algorithms a proof may be signed with, and a JSON body repeating the error when there is one. A request that
 * presented no token gets the bare challenge and an empty body (RFC 6750 §3.1).
 */
final readonly class ProtectedResourceRefusals
{
    public function __construct(
        private ServedTokenSchemesInterface $schemes,
        private ChallengeParametersInterface $challenges,
    ) {
    }

    public function of(OAuth2Exception|MissingBearerTokenException $exception): Response
    {
        $error = ProtectedResourceErrorResponse::fromException($exception, $this->schemes->servedTokenSchemes(), $this->challenges->challengeParameters());
        $parameters = $error->parameters();
        if ($parameters === []) {
            return new Response('', $error->statusCode, $error->headers());
        }

        return new Response(JwtPayload::encode($parameters), $error->statusCode, [
            'Content-Type' => UserInfoResponse::CONTENT_TYPE,
        ] + $error->headers());
    }
}
