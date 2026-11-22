<?php

declare(strict_types=1);

namespace App\Mail\OpenPgp;

use Symfony\Component\Mime\Part\AbstractPart;

/**
 * RFC 3156 §5: the ASCII-armored signature, application/pgp-signature.
 */
final class PgpSignaturePart extends AbstractPart
{
    public function __construct(
        private readonly string $signature,
    ) {
        parent::__construct();
        $this->getHeaders()->addParameterizedHeader('Content-Disposition', 'attachment', [
            'filename' => 'signature.asc',
        ]);
    }

    public function bodyToString(): string
    {
        return $this->signature;
    }

    /**
     * @return iterable<string>
     */
    public function bodyToIterable(): iterable
    {
        yield $this->signature;
    }

    public function getMediaType(): string
    {
        return 'application';
    }

    public function getMediaSubtype(): string
    {
        return 'pgp-signature';
    }
}
