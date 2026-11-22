<?php

declare(strict_types=1);

namespace App\Mail\OpenPgp;

use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\AbstractPart;

/**
 * RFC 3156 §5, RFC 1847 §2.1: the part as it is sent, then its detached signature. The classes symfony/mime writes it
 * with are internal to its PGP signer.
 */
final class PgpSignedPart extends AbstractMultipartPart
{
    public function __construct(AbstractPart $signed, PgpSignaturePart $signature)
    {
        parent::__construct($signed, $signature);
        $this->getHeaders()->addParameterizedHeader('Content-Type', 'multipart/signed', [
            'micalg' => 'pgp-sha256',
            'protocol' => 'application/pgp-signature',
        ]);
    }

    public function getMediaSubtype(): string
    {
        return 'signed';
    }
}
