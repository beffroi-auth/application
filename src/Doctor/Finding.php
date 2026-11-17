<?php

declare(strict_types=1);

namespace App\Doctor;

final readonly class Finding
{
    /**
     * @param list<string> $lines
     */
    private function __construct(
        public string $about,
        public bool $green,
        public array $lines,
    ) {
    }

    public static function green(string $about, string $says): self
    {
        return new self($about, true, [$says]);
    }

    /**
     * @param list<string> $lines what was found, then what it costs, then what makes it green
     */
    public static function red(string $about, array $lines): self
    {
        return new self($about, false, $lines);
    }
}
