<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Exception\OAuth2Exception;
use Symfony\Component\HttpFoundation\Request;

interface AuthorizationRequestReaderInterface
{
    /**
     * @throws OAuth2Exception never redirectable
     */
    public function read(Tenant $tenant, Request $request): ReceivedAuthorizationRequest;
}
