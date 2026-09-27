<?php

declare(strict_types=1);

namespace App\Controller;

use Heidi\Bundle\Http\Tls\ClientCertificateResolverInterface;
use Heidi\Bundle\Http\Tls\ForgedClientCertificateException;
use function strlen;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * What a client certificate looks like once it has crossed the edge (issue #157, architecture §6):
 * the one endpoint of the development stack that says whether the certificate a client presented at the
 * TLS handshake reached PHP, and what its thumbprint is. It is the measurement everything mTLS waits on,
 * and it is not an API of the product: the route file that carries it is imported by this application in
 * the development environment only, as the endpoints of the FIDO conformance tools are.
 *
 * Three answers, one per thing the deployment has to get right: "present" with the thumbprint RFC 8705
 * §3.1 binds a token to, "absent" when the request went through the listener without a certificate, which
 * must serve and not fail at the handshake, and "refused" when the request tried to carry a certificate
 * in a header.
 */
#[AsController]
final readonly class ClientCertificateController
{
    public function __construct(
        private ClientCertificateResolverInterface $certificates,
    ) {
    }

    #[Route('/mtls/client-certificate', name: 'app_mtls_client_certificate', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        try {
            $certificate = $this->certificates->ofRequest($request);
        } catch (ForgedClientCertificateException $refusal) {
            return self::answer([
                'host' => $request->getHost(),
                'client_certificate' => 'refused',
                'detail' => $refusal->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($certificate === null) {
            return self::answer([
                'host' => $request->getHost(),
                'client_certificate' => 'absent',
            ]);
        }

        return self::answer([
            'host' => $request->getHost(),
            'client_certificate' => 'present',
            'x5t#S256' => $certificate->x5tS256(),
            'der_bytes' => strlen($certificate->der),
        ]);
    }

    /**
     * @param array<string, mixed> $answer
     */
    private static function answer(array $answer, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse($answer, $status, [
            'Cache-Control' => 'no-store',
        ]);
    }
}
