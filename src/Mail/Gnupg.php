<?php

declare(strict_types=1);

namespace App\Mail;

use function bin2hex;
use FilesystemIterator;
use function mkdir;
use function random_bytes;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function rmdir;
use SplFileInfo;
use Symfony\Component\Mime\Exception\RuntimeException;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;
use function sys_get_temp_dir;
use function unlink;

/**
 * The gpg binary, asked to describe an OpenPGP public key in a home of its own that holds nothing and is removed after:
 * the format of its listing is GnuPG's doc/DETAILS.
 */
final readonly class Gnupg
{
    public function __construct(
        private string $binary = 'gpg',
        private float $timeout = 10.0,
    ) {
    }

    public function binary(): string
    {
        return $this->binary;
    }

    /**
     * @throws RuntimeException when gpg cannot be run, or refuses what it is given
     */
    public function listing(string $armored): string
    {
        $home = sys_get_temp_dir() . '/beffroi-gnupg-' . bin2hex(random_bytes(8));
        if (! @mkdir($home, 0o700)) {
            throw new RuntimeException('A home for gpg cannot be created.');
        }
        try {
            // No agent and no network: a public key is read, and nothing is fetched for it.
            $process = new Process(
                [$this->binary, '--batch', '--no-autostart', '--no-auto-key-retrieve', '--with-colons', '--fixed-list-mode', '--show-keys'],
                null,
                ['GNUPGHOME' => $home],
                $armored,
                $this->timeout,
            );
            $process->mustRun();

            return $process->getOutput();
        } catch (ExceptionInterface $failure) {
            throw new RuntimeException('gpg did not read the key.', 0, $failure);
        } finally {
            self::remove($home);
        }
    }

    private static function remove(string $directory): void
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($entries as $entry) {
            if ($entry instanceof SplFileInfo) {
                $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
            }
        }
        @rmdir($directory);
    }
}
