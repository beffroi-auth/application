<?php

declare(strict_types=1);

namespace App\Webauthn;

use function bin2hex;
use function chmod;
use function file_get_contents;
use function file_put_contents;
use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Core\Port\AuthenticatorListStoreInterface;
use function is_dir;
use function is_file;
use const LOCK_EX;
use function mkdir;
use function random_bytes;
use function rename;
use function sprintf;
use function unlink;

final readonly class FileAuthenticatorListStore implements AuthenticatorListStoreInterface
{
    private const string FILE = 'authenticators.json';

    public function __construct(
        private string $directory,
    ) {
    }

    public function write(string $document): void
    {
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0o700, true) && ! is_dir($this->directory)) {
            throw new RuntimeException(sprintf('The storage of the list of authenticators "%s" cannot be created.', $this->directory));
        }
        $path = $this->path();
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (@file_put_contents($temporary, $document, LOCK_EX) === false || ! @chmod($temporary, 0o600) || ! @rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException(sprintf('The list of authenticators cannot be written to "%s".', $path));
        }
    }

    public function read(): ?string
    {
        $path = $this->path();
        if (! is_file($path)) {
            return null;
        }
        $document = @file_get_contents($path);
        if ($document === false) {
            throw new RuntimeException(sprintf('The list of authenticators "%s" cannot be read.', $path));
        }

        return $document;
    }

    private function path(): string
    {
        return $this->directory . '/' . self::FILE;
    }
}
