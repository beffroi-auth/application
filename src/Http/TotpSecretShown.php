<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Credential\TotpProvisioning;
use function implode;
use ParagonIE\ConstantTime\Base32;
use function str_split;

/**
 * The secret of an authenticator app as a page shows it: a QR code the provider draws, and the same secret in base32
 * (RFC 4648 §6) for an app in front of no camera.
 */
final readonly class TotpSecretShown
{
    public function __construct(
        private QrCode $qrCode,
    ) {
    }

    /**
     * @return array{qr_code: string, key: string}
     */
    public function of(TotpProvisioning $provisioning): array
    {
        return [
            'qr_code' => $this->qrCode->svg($provisioning->uri),
            'key' => implode(' ', str_split(Base32::encodeUpperUnpadded($provisioning->secret->bytes), 4)),
        ];
    }
}
