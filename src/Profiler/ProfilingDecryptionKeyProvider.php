<?php

declare(strict_types=1);

namespace App\Profiler;

use Beffroi\Core\Domain\Key\UnsealedKey;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\DecryptionKeyProviderInterface;

final readonly class ProfilingDecryptionKeyProvider implements DecryptionKeyProviderInterface
{
    public function __construct(
        private DecryptionKeyProviderInterface $keys,
        private OpProfile $profile,
        private KmsClient $kms,
    ) {
    }

    public function decryptionKey(Tenant $tenant, string $kid): UnsealedKey
    {
        $unsealed = $this->keys->decryptionKey($tenant, $kid);
        $this->profile->record(OpenedKey::observe($unsealed, 'opens an envelope a client sealed', $this->kms));

        return $unsealed;
    }
}
