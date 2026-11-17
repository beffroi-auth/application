<?php

declare(strict_types=1);

namespace App\Http\Account;

use function array_filter;
use function array_values;
use Beffroi\Core\Domain\Identity\Address;
use Beffroi\Core\Domain\Identity\User;
use function implode;

/**
 * The "sub" is left out: a client of a pairwise sector receives another identifier.
 */
final readonly class RecordedInformation
{
    /**
     * @return list<RecordedDetail>
     */
    public static function identityOf(User $user): array
    {
        return [
            RecordedDetail::said('username', $user->username),
            RecordedDetail::dated('created_at', $user->createdAt),
            RecordedDetail::dated('updated_at', $user->updatedAt),
        ];
    }

    /**
     * @return list<RecordedDetail>
     */
    public static function claimsOf(User $user): array
    {
        return array_values(array_filter(
            self::everyClaimOf($user),
            static fn (RecordedDetail $detail): bool => $detail->value !== null,
        ));
    }

    /**
     * @return list<RecordedDetail>
     */
    public static function everyClaimOf(User $user): array
    {
        $details = [];
        $details[] = $user->email === null
            ? RecordedDetail::nothing('email')
            : RecordedDetail::verifiable('email', $user->email->value, $user->emailVerified);
        $details[] = $user->phoneNumber === null
            ? RecordedDetail::nothing('phone_number')
            : RecordedDetail::verifiable('phone_number', $user->phoneNumber, $user->phoneNumberVerified);
        $profile = $user->profile;
        $claims = [
            'name' => $profile->name,
            'given_name' => $profile->givenName,
            'family_name' => $profile->familyName,
            'middle_name' => $profile->middleName,
            'nickname' => $profile->nickname,
            'preferred_username' => $profile->preferredUsername,
            'birthdate' => $profile->birthdate,
            'gender' => $profile->gender,
            'locale' => $profile->locale,
            'zoneinfo' => $profile->zoneinfo,
            'profile' => $profile->profileUrl,
            'picture' => $profile->pictureUrl,
            'website' => $profile->websiteUrl,
            'address' => self::address($user->address),
        ];
        foreach ($claims as $key => $value) {
            $details[] = $value === null ? RecordedDetail::nothing($key) : RecordedDetail::said($key, $value);
        }

        return $details;
    }

    private static function address(?Address $address): ?string
    {
        if ($address === null) {
            return null;
        }
        if ($address->formatted !== null) {
            return $address->formatted;
        }
        $parts = array_filter(
            [$address->streetAddress, $address->postalCode, $address->locality, $address->region, $address->country],
            static fn (?string $part): bool => $part !== null,
        );

        return $parts === [] ? null : implode(', ', $parts);
    }
}
