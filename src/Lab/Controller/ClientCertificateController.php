<?php

declare(strict_types=1);

namespace App\Lab\Controller;

use App\Http\Tls\ClientCertificateResolverInterface;
use App\Http\Tls\ForgedClientCertificateException;
use function strlen;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A development endpoint that says whether the client certificate of the handshake reached PHP, and its thumbprint.
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
