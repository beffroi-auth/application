<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Mail\PgpMime;
use Beffroi\Core\Port\MailSigningKeyRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 3156 §7: the OpenPGP key the tenant signs its mail with and the keys it replaced, newest first, so that a mail
 * signed before a replacement still verifies. A tenant that holds no OpenPGP key serves nothing here.
 */
#[AsController]
final readonly class MailPgpKeysController
{
    public const string MEDIA_TYPE = 'application/pgp-keys';

    public function __construct(
        private TenantProvider $tenants,
        private MailSigningKeyRepositoryInterface $keys,
    ) {
    }

    #[Route('/mail/pgp-keys', name: 'beffroi_protocol_mail_pgp_keys', methods: ['GET'])]
    public function __invoke(): Response
    {
        $armored = '';
        foreach ($this->keys->ofKind($this->tenants->provide(), PgpMime::KIND) as $key) {
            $armored .= $key->published;
        }
        if ($armored === '') {
            throw new NotFoundHttpException('This tenant signs no mail with OpenPGP.');
        }

        return new Response($armored, Response::HTTP_OK, [
            'Content-Type' => self::MEDIA_TYPE,
        ]);
    }
}
