<?php

declare(strict_types=1);

namespace App\Http\Tls;

use Beffroi\Oidc\Domain\Client\Certificate\ClientCertificate;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reads SSL_CLIENT_CERT, which only the TLS listener sets, never a header: a client's "Ssl-Client-Cert" reaches PHP
 * as HTTP_SSL_CLIENT_CERT (decision 0006).
 */
final readonly class EdgeClientCertificateResolver implements ClientCertificateResolverInterface
{
    /**
     * The leaf, in the PEM envelope of mod_ssl's SSL_CLIENT_CERT and nginx's $ssl_client_cert.
     */
    public const string VARIABLE = 'SSL_CLIENT_CERT';

    public const array REFUSED_HEADERS = [
        'ssl-client-cert',
        'x-ssl-client-cert',
        'x-client-cert',
        'x-forwarded-client-cert',
    ];

    public function ofRequest(Request $request): ?ClientCertificate
    {
        foreach (self::REFUSED_HEADERS as $header) {
            if ($request->headers->has($header)) {
                throw ForgedClientCertificateException::carriedBy($header);
            }
        }
        $pem = $request->server->getString(self::VARIABLE);
        if ($pem === '') {
            return null;
        }

        return ClientCertificate::fromPem($pem);
    }
}
