<?php

declare(strict_types=1);

namespace App\Mail;

use Closure;
use function file_put_contents;
use Symfony\Component\Mime\Exception\RuntimeException;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * The components of symfony/mime that sign and encrypt take a path where they take a key or a certificate: what they
 * read lives in a file of the temporary directory that tempnam() creates readable by its owner alone, for the call that
 * reads it and no longer.
 */
final readonly class TemporaryFiles
{
    /**
     * @template T
     *
     * @param list<string>             $contents
     * @param Closure(list<string>): T $use      handed the path of each content, in their order
     *
     * @throws RuntimeException when a file cannot be written
     *
     * @return T
     */
    public static function holding(array $contents, Closure $use): mixed
    {
        $paths = [];
        try {
            foreach ($contents as $content) {
                $path = tempnam(sys_get_temp_dir(), 'beffroi-mail-');
                if ($path === false) {
                    throw new RuntimeException('A temporary file of the mail cannot be created.');
                }
                $paths[] = $path;
                if (file_put_contents($path, $content) === false) {
                    throw new RuntimeException('A temporary file of the mail cannot be written.');
                }
            }

            return $use($paths);
        } finally {
            foreach ($paths as $path) {
                @unlink($path);
            }
        }
    }
}
