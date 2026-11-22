<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Mail\Smime;
use Beffroi\Core\Port\MailSigningKeyRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 8555 §9.1: the certificate the tenant signs its mail with, then the chain above it. The certificate of a key it
 * replaced is not served: an S/MIME signature carries the certificate that made it (RFC 5652 §5.1). A tenant that holds
 * no S/MIME key serves nothing here.
 */
#[AsController]
final readonly class MailSmimeCertificateController
{
    public const string MEDIA_TYPE = 'application/pem-certificate-chain';

    public function __construct(
        private TenantProvider $tenants,
        private MailSigningKeyRepositoryInterface $keys,
    ) {
    }

    #[Route('/mail/smime-certificate', name: 'beffroi_protocol_mail_smime_certificate', methods: ['GET'])]
    public function __invoke(): Response
    {
        $key = $this->keys->current($this->tenants->provide(), Smime::KIND);
        if ($key === null) {
            throw new NotFoundHttpException('This tenant signs no mail with S/MIME.');
        }

        return new Response($key->published, Response::HTTP_OK, [
            'Content-Type' => self::MEDIA_TYPE,
        ]);
    }
}
