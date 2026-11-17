<?php

declare(strict_types=1);

namespace App\Fido\Conformance;

use function hash;
use function is_array;
use function is_string;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Found back by the challenge the response carries: the conformance tools hold no cookie.
 */
final readonly class CeremonyStore
{
    public const int LIFETIME = 300;

    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function storeCreation(Uuid $userId, PublicKeyCredentialCreationOptions $options): void
    {
        $item = $this->cache->getItem(self::key('creation', $options->challenge));
        $item->set([
            'user' => $userId->toRfc4122(),
            'options' => $options,
        ]);
        $item->expiresAfter(self::LIFETIME);
        $this->cache->save($item);
    }

    /**
     * @return array{Uuid, PublicKeyCredentialCreationOptions}|null
     */
    public function takeCreation(string $challenge): ?array
    {
        $key = self::key('creation', $challenge);
        $stored = $this->cache->getItem($key)->get();
        $this->cache->deleteItem($key);
        if (! is_array($stored) || ! is_string($stored['user'] ?? null) || ! ($stored['options'] ?? null) instanceof PublicKeyCredentialCreationOptions) {
            return null;
        }

        return [Uuid::fromString($stored['user']), $stored['options']];
    }

    public function storeRequest(PublicKeyCredentialRequestOptions $options): void
    {
        $item = $this->cache->getItem(self::key('request', $options->challenge));
        $item->set($options);
        $item->expiresAfter(self::LIFETIME);
        $this->cache->save($item);
    }

    public function takeRequest(string $challenge): ?PublicKeyCredentialRequestOptions
    {
        $key = self::key('request', $challenge);
        $options = $this->cache->getItem($key)->get();
        $this->cache->deleteItem($key);

        return $options instanceof PublicKeyCredentialRequestOptions ? $options : null;
    }

    private static function key(string $ceremony, string $challenge): string
    {
        return 'beffroi.fido.conformance.' . $ceremony . '.' . hash('sha256', $challenge);
    }
}
