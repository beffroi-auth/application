<?php

declare(strict_types=1);

namespace App\Key;

use function base64_decode;
use function base64_encode;
use Beffroi\Core\Domain\Identity\EmailAddress;
use Beffroi\Core\Domain\Key\MailSigningKey;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Core\Port\MailSigningKeyRepositoryInterface;
use function bin2hex;
use function chmod;
use DateTimeImmutable;
use DateTimeInterface;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use JsonException;
use const LOCK_EX;
use function mkdir;
use function preg_match;
use function random_bytes;
use function rename;
use function sprintf;
use function unlink;
use function usort;

/**
 * The keys the tenant signs its mail with, one file each beside its other keys (architecture §3.3), under "mail/" where
 * the reader of the JWK Set looks for none: the private half sealed by the KMS, the rest in clear. A file that names
 * another tenant is a fault of the storage, never a key absent.
 */
final readonly class FileMailSigningKeyRepository implements MailSigningKeyRepositoryInterface
{
    public function __construct(
        private string $directory,
    ) {
    }

    public function current(Tenant $tenant, string $kind): ?MailSigningKey
    {
        foreach ($this->ofKind($tenant, $kind) as $key) {
            if ($key->isCurrent()) {
                return $key;
            }
        }

        return null;
    }

    public function ofKind(Tenant $tenant, string $kind): array
    {
        // A format is lowercase letters, and nothing else may reach the pattern of glob().
        if (preg_match('/^[a-z]+$/', $kind) !== 1) {
            return [];
        }
        $paths = glob($this->tenantDirectory($tenant) . '/' . $kind . '-*.json');
        if ($paths === false) {
            return [];
        }
        $keys = [];
        foreach ($paths as $path) {
            $json = @file_get_contents($path);
            if ($json === false) {
                throw new RuntimeException(sprintf('The mail signing key "%s" cannot be read.', $path));
            }
            $keys[] = $this->read($tenant, $path, $json);
        }
        usort($keys, static fn (MailSigningKey $a, MailSigningKey $b): int => [$b->setAt, $b->fingerprint] <=> [$a->setAt, $a->fingerprint]);

        return $keys;
    }

    public function save(MailSigningKey $key): void
    {
        $directory = $this->tenantDirectory($key->tenant);
        if (! is_dir($directory) && ! @mkdir($directory, 0o700, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('The storage of the mail signing keys "%s" cannot be created.', $directory));
        }
        $path = $this->path($key);
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $addresses = [];
        foreach ($key->addresses as $address) {
            $addresses[] = $address->value;
        }
        $json = json_encode(
            [
                'tenant_id' => $key->tenant->id->toRfc4122(),
                'kind' => $key->kind,
                'fingerprint' => $key->fingerprint,
                'published' => $key->published,
                'sealed_private_jwk' => base64_encode($key->sealedPrivateJwk),
                'addresses' => $addresses,
                'set_at' => $key->setAt->format(DateTimeInterface::RFC3339_EXTENDED),
                'not_after' => $key->notAfter?->format(DateTimeInterface::RFC3339_EXTENDED),
                'replaced_at' => $key->replacedAt?->format(DateTimeInterface::RFC3339_EXTENDED),
            ],
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
        if (@file_put_contents($temporary, $json, LOCK_EX) === false || ! @chmod($temporary, 0o600) || ! @rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException(sprintf('The mail signing key "%s" cannot be written to "%s".', $key->fingerprint, $path));
        }
    }

    public function remove(MailSigningKey $key): void
    {
        $path = $this->path($key);
        if (is_file($path) && ! @unlink($path)) {
            throw new RuntimeException(sprintf('The mail signing key "%s" cannot be removed.', $key->fingerprint));
        }
    }

    private function read(Tenant $tenant, string $path, string $json): MailSigningKey
    {
        try {
            $document = json_decode($json, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('The mail signing key "%s" is not JSON.', $path), 0, $exception);
        }
        if (! is_array($document) || ($document['tenant_id'] ?? null) !== $tenant->id->toRfc4122()) {
            throw new RuntimeException(sprintf('The mail signing key "%s" is not a key of the tenant "%s".', $path, $tenant->slug));
        }
        $sealed = is_string($document['sealed_private_jwk'] ?? null) ? base64_decode($document['sealed_private_jwk'], true) : false;
        $addresses = [];
        try {
            foreach (is_array($document['addresses'] ?? null) ? $document['addresses'] : [] as $address) {
                $addresses[] = new EmailAddress(is_string($address) ? $address : '');
            }

            return new MailSigningKey(
                $tenant,
                is_string($document['kind'] ?? null) ? $document['kind'] : '',
                is_string($document['fingerprint'] ?? null) ? $document['fingerprint'] : '',
                is_string($document['published'] ?? null) ? $document['published'] : '',
                $sealed === false ? '' : $sealed,
                $addresses,
                self::date($path, $document['set_at'] ?? null) ?? throw self::corrupted($path),
                self::date($path, $document['not_after'] ?? null),
                self::date($path, $document['replaced_at'] ?? null),
            );
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException(sprintf('The mail signing key "%s" is not a key document: %s', $path, $exception->getMessage()), 0, $exception);
        }
    }

    private static function date(string $path, mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        $date = is_string($value) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $value) : false;

        return $date === false ? throw self::corrupted($path) : $date;
    }

    private static function corrupted(string $path): RuntimeException
    {
        return new RuntimeException(sprintf('The mail signing key "%s" is not a key document.', $path));
    }

    private function tenantDirectory(Tenant $tenant): string
    {
        return $this->directory . '/' . $tenant->slug . '/mail';
    }

    private function path(MailSigningKey $key): string
    {
        return $this->tenantDirectory($key->tenant) . '/' . $key->kind . '-' . $key->fingerprint . '.json';
    }
}
