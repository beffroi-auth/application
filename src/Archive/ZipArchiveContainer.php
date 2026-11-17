<?php

declare(strict_types=1);

namespace App\Archive;

use Beffroi\Core\Domain\Archive\ArchivePassword;
use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Core\Port\ArchiveContainerInterface;
use function file_get_contents;
use function is_file;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use ZipArchive;

/**
 * One entry encrypted with AES-256, never ZipCrypto, which is broken though Windows and macOS open it natively.
 *
 * The document reaches libzip in memory, so the temporary file libzip writes holds only the encrypted entry.
 */
final readonly class ZipArchiveContainer implements ArchiveContainerInterface
{
    public const string MEDIA_TYPE = 'application/zip';

    public const string EXTENSION = 'zip';

    public function mediaType(): string
    {
        return self::MEDIA_TYPE;
    }

    public function extension(): string
    {
        return self::EXTENSION;
    }

    public function wrap(string $document, string $name, ArchivePassword $password): string
    {
        $path = tempnam(sys_get_temp_dir(), 'beffroi-archive-');
        if ($path === false) {
            throw new RuntimeException('No temporary file could be opened to write the archive into.');
        }

        try {
            return $this->write($path, $document, $name, $password);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @throws RuntimeException
     */
    private function write(string $path, string $document, string $name, ArchivePassword $password): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(sprintf('No zip could be opened for writing (%s).', $zip->getStatusString()));
        }
        $zip->setPassword($password->value);
        if (! $zip->addFromString($name, $document)) {
            throw new RuntimeException(sprintf('The archive could not be written into the zip (%s).', $zip->getStatusString()));
        }
        // Before close(), where libzip encrypts: a libzip without crypto fails here, and nothing is written in clear.
        if (! $zip->setEncryptionName($name, ZipArchive::EM_AES_256)) {
            throw new RuntimeException(sprintf('This installation cannot write an AES-256 entry (%s).', $zip->getStatusString()));
        }
        if (! $zip->close()) {
            throw new RuntimeException(sprintf('The zip could not be closed (%s).', $zip->getStatusString()));
        }
        $wrapped = file_get_contents($path);
        if ($wrapped === false || $wrapped === '') {
            throw new RuntimeException('The zip was written and holds nothing.');
        }

        return $wrapped;
    }
}
