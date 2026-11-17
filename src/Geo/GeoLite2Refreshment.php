<?php

declare(strict_types=1);

namespace App\Geo;

use DateTimeImmutable;
use function sprintf;

final readonly class GeoLite2Refreshment
{
    private function __construct(
        public string $what,
        public bool $refused,
    ) {
    }

    public static function stored(string $source, string $edition, DateTimeImmutable $builtOn, int $nodes): self
    {
        return new self(
            sprintf(
                'The "%s" database of %s is stored: built on %s, a search tree of %d nodes.',
                $edition,
                $source,
                $builtOn->format('Y-m-d'),
                $nodes,
            ),
            false,
        );
    }

    public static function unchanged(string $source): self
    {
        return new self(sprintf('The database of %s is the one already stored, nothing was downloaded.', $source), false);
    }

    public static function refused(string $source, string $reason): self
    {
        return new self(sprintf('The archive of %s is refused, nothing of it is kept: %s', $source, $reason), true);
    }
}
