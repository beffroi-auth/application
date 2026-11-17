<?php

declare(strict_types=1);

namespace App\Profiler;

use Beffroi\Core\Domain\Key\SignatureAlgorithmInterface;
use Beffroi\Core\Domain\Key\UnsealedKey;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\SigningKeyProviderInterface;

final readonly class ProfilingSigningKeyProvider implements SigningKeyProviderInterface
{
    public function __construct(
        private SigningKeyProviderInterface $keys,
        private OpProfile $profile,
        private KmsClient $kms,
    ) {
    }

    public function activeKey(Tenant $tenant, SignatureAlgorithmInterface $algorithm): UnsealedKey
    {
        $unsealed = $this->keys->activeKey($tenant, $algorithm);
        $this->profile->record(OpenedKey::observe($unsealed, 'signs', $this->kms));

        return $unsealed;
    }
}
