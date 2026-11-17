<?php

declare(strict_types=1);

namespace App\Profiler;

use Beffroi\Core\Domain\Key\UnsealedKey;

final readonly class OpenedKey
{
    public static function observe(UnsealedKey $unsealed, string $purpose, KmsClient $kms): Observation
    {
        return new Observation('Keys', 'Key', [
            'kid' => $unsealed->key->kid,
            'algorithm' => $unsealed->key->algorithm->name(),
            'use' => $unsealed->key->algorithm->keyUse()->value,
            'what it does here' => $purpose,
            'status' => $unsealed->key->status->value,
            'opened by' => $kms->name(),
        ]);
    }
}
