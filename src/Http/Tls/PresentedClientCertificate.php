<?php

declare(strict_types=1);

namespace App\Http\Tls;

use Beffroi\Oidc\Domain\Client\Certificate\ClientCertificate;
use Beffroi\Oidc\Exception\InvalidArgumentException;
use Beffroi\Oidc\Exception\InvalidRequestException;
use Symfony\Component\HttpFoundation\Request;

/**
 * RFC 8705 §2
 *
 * The header a request tried is logged and never written back: an error description is the provider's own sentence.
 */
final readonly class PresentedClientCertificate
{
    public function __construct(
        private ClientCertificateResolverInterface $certificates,
    ) {
    }

    /**
     * @throws InvalidRequestException when the request carries a certificate in a header, or when what
     *                                 the edge handed over is not a certificate
     */
    public function ofRequest(Request $request): ?ClientCertificate
    {
        try {
            return $this->certificates->ofRequest($request);
        } catch (ForgedClientCertificateException $refusal) {
            throw new InvalidRequestException(
                'The request carries a client certificate in a header, which nothing in front of this provider sets.',
                previous: $refusal,
            );
        } catch (InvalidArgumentException $exception) {
            throw new InvalidRequestException('The client certificate of this request could not be read.', previous: $exception);
        }
    }
}
