<?php

declare(strict_types=1);

namespace App\Administration;

use Beffroi\Core\Application\Credential\AuthenticatorModels;
use Beffroi\Core\Domain\Credential\Credential;
use Beffroi\Core\Domain\Credential\ModelDeclaringCredentialInterface;
use Beffroi\Core\Domain\Credential\RevocableCredentialInterface;
use Beffroi\Core\Domain\Group\GroupMembership;
use Beffroi\Core\Domain\Identity\User;
use function array_filter;
use function array_map;

/**
 * A person as the administration API writes them: the account and the source that created it, the standard claims it
 * holds (OIDC Core §5.1), the groups with the source of each membership, and the credentials with their kind, name,
 * authenticator and dates.
 */
final readonly class PersonDocument
{
    public function __construct(
        private AuthenticatorModels $models,
    ) {
    }

    /**
     * @param list<GroupMembership> $memberships
     * @param list<Credential>      $credentials
     *
     * @return array<string, mixed>
     */
    public function of(User $user, array $memberships, array $credentials): array
    {
        return [
            'id' => $user->id->toRfc4122(),
            'subject' => $user->subject->value,
            'username' => $user->username,
            'enabled' => $user->isEnabled(),
            'disabled_at' => AdministrationAnswer::moment($user->disabledAt),
            'source' => $user->source->provider ?? 'local',
            'created_at' => AdministrationAnswer::moment($user->createdAt),
            'claims' => (object) self::claims($user),
            'groups' => array_map(static fn (GroupMembership $membership): array => [
                'name' => $membership->group->name,
                'source' => $membership->source->value,
                'added_at' => AdministrationAnswer::moment($membership->addedAt),
            ], $memberships),
            'credentials' => array_map(fn (Credential $credential): array => [
                'id' => $credential->id->toRfc4122(),
                'kind' => $credential->kind(),
                'name' => $credential->label(),
                'authenticator' => $credential instanceof ModelDeclaringCredentialInterface ? $this->models->of($credential)?->name : null,
                'created_at' => AdministrationAnswer::moment($credential->createdAt),
                'last_used_at' => AdministrationAnswer::moment($credential->lastUsedAt),
                'revocable' => $credential instanceof RevocableCredentialInterface,
                'revoked_at' => $credential instanceof RevocableCredentialInterface ? AdministrationAnswer::moment($credential->revokedAt) : null,
            ], $credentials),
        ];
    }

    /**
     * The standard claims of OIDC Core §5.1 the account holds, by their names there, and none it does not.
     *
     * @return array<string, mixed>
     */
    private static function claims(User $user): array
    {
        $address = $user->address;

        return array_filter(
            [
                'name' => $user->profile->name,
                'given_name' => $user->profile->givenName,
                'family_name' => $user->profile->familyName,
                'email' => $user->email === null ? null : (string) $user->email,
                'email_verified' => $user->email === null ? null : $user->emailVerified,
                'phone_number' => $user->phoneNumber,
                'phone_number_verified' => $user->phoneNumber === null ? null : $user->phoneNumberVerified,
                'address' => $address === null
                    ? null
                    : array_filter(
                        [
                            'street_address' => $address->streetAddress,
                            'postal_code' => $address->postalCode,
                            'locality' => $address->locality,
                            'region' => $address->region,
                            'country' => $address->country,
                        ],
                        static fn (?string $member): bool => $member !== null,
                    ),
            ],
            static fn (mixed $claim): bool => $claim !== null,
        );
    }
}
