<?php

declare(strict_types=1);

namespace App\Http\Tls;

use Beffroi\Oidc\Domain\Client\Certificate\ClientCertificate;
use Beffroi\Oidc\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

/**
 * A deployment behind another proxy brings its own resolver rather than a header to trust (decision 0006).
 */
interface ClientCertificateResolverInterface
{
    /**
     * @throws ForgedClientCertificateException when the request carries a client certificate in a header
     * @throws InvalidArgumentException         when what the edge handed over is not a certificate
     */
    public function ofRequest(Request $request): ?ClientCertificate;
}
