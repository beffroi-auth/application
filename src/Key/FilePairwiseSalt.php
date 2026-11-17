<?php

declare(strict_types=1);

namespace App\Key;

use function base64_decode;
use function base64_encode;
use function bin2hex;
use function chmod;
use function file_get_contents;
use function file_put_contents;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Core\Port\RandomnessInterface;
use Beffroi\Core\Port\SecretSealerInterface;
use Beffroi\Oidc\Port\PairwiseSaltInterface;
use function is_dir;
use function is_string;
use function link;
use const LOCK_EX;
use function mkdir;
use function random_bytes;
use function sprintf;
use function unlink;

/**
 * Hard-linked into place rather than renamed, as a link fails when the name is taken: of two workers drawing at
 * once, the loser reads the salt back, since a salt overwritten would change every pairwise identifier of the
 * tenant. The salt is never logged.
 */
final readonly class FilePairwiseSalt implements PairwiseSaltInterface
{
    public const string FILE = 'pairwise.salt';

    private const int LENGTH = 32;

    public function __construct(
        private string $directory,
        private SecretSealerInterface $sealer,
        private RandomnessInterface $randomness,
    ) {
    }

    public function saltOf(Tenant $tenant): string
    {
        $path = $this->path($tenant);
        $sealed = self::read($path);

        return $sealed === null ? $this->draw($tenant, $path) : $this->open($tenant, $sealed, $path);
    }

    /**
     * @throws RuntimeException
     *
     * @return non-empty-string
     */
    private function draw(Tenant $tenant, string $path): string
    {
        $directory = $this->directory . '/' . $tenant->slug;
        if (! is_dir($directory) && ! @mkdir($directory, 0o700, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('The key storage "%s" cannot be created.', $directory));
        }
        $salt = $this->randomness->bytes(self::LENGTH);
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (@file_put_contents($temporary, base64_encode($this->sealer->seal($tenant, $salt)), LOCK_EX) === false || ! @chmod($temporary, 0o600)) {
            @unlink($temporary);

            throw new RuntimeException(sprintf('The pairwise salt of the tenant "%s" cannot be written to "%s".', $tenant->slug, $path));
        }
        $linked = @link($temporary, $path);
        @unlink($temporary);
        if ($linked) {
            return $salt;
        }
        $sealed = self::read($path);
        if ($sealed === null) {
            throw new RuntimeException(sprintf('The pairwise salt of the tenant "%s" cannot be written to "%s".', $tenant->slug, $path));
        }

        return $this->open($tenant, $sealed, $path);
    }

    /**
     * @throws RuntimeException
     *
     * @return non-empty-string
     */
    private function open(Tenant $tenant, string $sealed, string $path): string
    {
        $envelope = base64_decode($sealed, true);
        $salt = $envelope === false ? '' : $this->sealer->unseal($tenant, $envelope);
        if ($salt === '') {
            throw new RuntimeException(sprintf('The pairwise salt of the tenant "%s" in "%s" cannot be read.', $tenant->slug, $path));
        }

        return $salt;
    }

    /**
     * @return non-empty-string|null
     */
    private static function read(string $path): ?string
    {
        $sealed = @file_get_contents($path);

        return is_string($sealed) && $sealed !== '' ? $sealed : null;
    }

    private function path(Tenant $tenant): string
    {
        return $this->directory . '/' . $tenant->slug . '/' . self::FILE;
    }
}
