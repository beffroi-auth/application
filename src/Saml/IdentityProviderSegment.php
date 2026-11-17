<?php

declare(strict_types=1);

namespace App\Saml;

use Beffroi\Core\Domain\Encoding\Base64Url;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Saml\Bridge\Model\ModelEntityId;

/**
 * The entity ID as one path segment, in base64url without padding (RFC 4648 §5): percent-encoded, its "/", "?",
 * "#" and "%" would be decoded or merged by a server or a proxy on the way.
 */
final class IdentityProviderSegment
{
    /**
     * Base64url, at most the 1 366 characters of the 1 024 octets of the longest entity ID (M-6).
     */
    public const string REQUIREMENT = '[A-Za-z0-9_-]{1,1366}';

    private function __construct()
    {
    }

    public static function of(string $entityId): string
    {
        return Base64Url::encode($entityId);
    }

    /**
     * Null unless the segment is the one way of writing an entity ID (M-6), so that a provider has one URL and not
     * several that a cache or a rule at the edge would tell apart.
     */
    public static function entityIdOf(string $segment): ?string
    {
        $entityId = Base64Url::decode($segment);
        if ($entityId === null || Base64Url::encode($entityId) !== $segment) {
            return null;
        }

        try {
            return ModelEntityId::ofIdentityProvider($entityId);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
