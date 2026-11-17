<?php

declare(strict_types=1);

namespace App\Profiler;

use Beffroi\Oidc\Domain\Token\UnverifiedJwt;
use Beffroi\Oidc\Port\CompactJwtReaderInterface;

final readonly class ProfilingCompactJwtReader implements CompactJwtReaderInterface
{
    public function __construct(
        private CompactJwtReaderInterface $jwts,
        private OpProfile $profile,
        private PanelValues $values,
    ) {
    }

    public function read(string $jwt): UnverifiedJwt
    {
        $read = $this->jwts->read($jwt);
        $this->profile->record(new Observation('Request', 'JWT received, not yet verified', [
            'alg' => $read->algorithm() ?? 'none declared',
            'kid' => $read->keyId() ?? 'none declared',
            'typ' => self::header($read, 'typ'),
            'claims' => $this->values->all($read->claims),
        ]));

        return $read;
    }

    private static function header(UnverifiedJwt $jwt, string $name): string
    {
        $value = $jwt->protectedHeader[$name] ?? null;

        return $value === null ? 'none declared' : WithheldValues::of($name, $value);
    }
}
