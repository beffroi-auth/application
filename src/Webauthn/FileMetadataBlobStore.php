<?php

declare(strict_types=1);

namespace App\Webauthn;

use function basename;
use function bin2hex;
use function chmod;
use function file_get_contents;
use function file_put_contents;
use function glob;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Core\Port\MetadataBlobStoreInterface;
use function is_dir;
use const LOCK_EX;
use function mkdir;
use function preg_match;
use function random_bytes;
use function rename;
use function sort;
use function sprintf;
use function unlink;

final readonly class FileMetadataBlobStore implements MetadataBlobStoreInterface
{
    /**
     * What may name a file: nothing that walks out of the directory.
     */
    private const string NAME_PATTERN = '/^[A-Za-z0-9._-]+$/D';

    public function __construct(
        private string $directory,
    ) {
    }

    public function write(string $name, string $payload): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" cannot name a metadata document.', $name));
        }
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0o700, true) && ! is_dir($this->directory)) {
            throw new RuntimeException(sprintf('The metadata storage "%s" cannot be created.', $this->directory));
        }
        $path = $this->path($name);
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (@file_put_contents($temporary, $payload, LOCK_EX) === false || ! @chmod($temporary, 0o600) || ! @rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException(sprintf('The metadata document "%s" cannot be written to "%s".', $name, $path));
        }
    }

    public function readAll(): array
    {
        $paths = glob($this->directory . '/*.json');
        if ($paths === false || $paths === []) {
            return [];
        }
        sort($paths);
        $payloads = [];
        foreach ($paths as $path) {
            $payload = @file_get_contents($path);
            if ($payload === false) {
                throw new RuntimeException(sprintf('The metadata document "%s" cannot be read.', $path));
            }
            $payloads[basename($path, '.json')] = $payload;
        }

        return $payloads;
    }

    private function path(string $name): string
    {
        return $this->directory . '/' . $name . '.json';
    }
}
