<?php

declare(strict_types=1);

namespace App\Key;

/**
 * How long a key works, counted from its activation, before a successor is staged for it.
 */
final readonly class KeyAge
{
    public const int DEFAULT_SECONDS = 7_776_000;
}
