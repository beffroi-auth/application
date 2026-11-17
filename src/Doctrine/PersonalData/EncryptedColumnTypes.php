<?php

declare(strict_types=1);

namespace App\Doctrine\PersonalData;

use function array_keys;
use function count;
use function implode;
use Beffroi\Core\Exception\InvalidArgumentException;
use function in_array;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * A sealed column is unbounded binary whatever the length its mapping carries. A ciphertext is bound to its tenant, its
 * family and its type, not to its row: moved to another row of the same column, or to another column of the same
 * family and type, it decrypts there.
 */
final readonly class EncryptedColumnTypes
{
    public const string PREFIX = 'beffroi_encrypted_';

    /**
     * @var array<string, list<string>> by scope, the name of the table without the prefix
     */
    public const array FAMILIES = [
        'user' => ['string', 'beffroi_email_address', 'beffroi_address', 'beffroi_profile', 'beffroi_user_source'],
        'linked_identity' => ['string', 'json'],
        'passkey_credential' => ['string'],
        'push_subscription' => ['string', 'text'],
        'authentication_session' => ['beffroi_request_context'],
        'interaction' => ['text'],
        'pushed_authorization_request' => ['json'],
        'device_authorization_request' => ['beffroi_request_context'],
        'backchannel_authentication_request' => ['beffroi_binding_message', 'beffroi_request_context'],
        'client' => ['json', 'beffroi_client_secret'],
        'journal' => ['beffroi_request_context'],
        'personal_archive' => ['text'],
        'claim_change' => ['string', 'beffroi_request_context'],
        'disclosure' => ['beffroi_scopes', 'beffroi_claims_request', 'beffroi_authorization_details'],
        'saml_service_provider' => ['text'],
        'saml_upstream_identity_provider' => ['text'],
    ];

    /**
     * @throws InvalidArgumentException
     */
    public static function name(string $family, string $type): string
    {
        if (! in_array($type, self::FAMILIES[$family] ?? [], true)) {
            throw new InvalidArgumentException(sprintf(
                'The family "%s" seals no column of type "%s". The families are %s.',
                $family,
                $type,
                implode(', ', array_keys(self::FAMILIES)),
            ));
        }

        return self::PREFIX . $family . '_' . (str_starts_with($type, 'beffroi_') ? substr($type, strlen('beffroi_')) : $type);
    }

    /**
     * @return array<string, array{type: string, key: string}>
     */
    public static function declarations(): array
    {
        $declarations = [];
        foreach (self::FAMILIES as $family => $types) {
            foreach ($types as $type) {
                $declarations[self::name($family, $type)] = [
                    'type' => $type,
                    'key' => $family,
                ];
            }
        }

        return $declarations;
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::declarations());
    }

    /**
     * @return array<string, string>
     */
    public static function familyByType(): array
    {
        $families = [];
        foreach (self::declarations() as $name => $declaration) {
            $families[$name] = $declaration['key'];
        }

        return $families;
    }

    /**
     * @return list<string>
     */
    public static function families(): array
    {
        return array_keys(self::FAMILIES);
    }

    public static function familyCount(): int
    {
        return count(self::FAMILIES);
    }
}
