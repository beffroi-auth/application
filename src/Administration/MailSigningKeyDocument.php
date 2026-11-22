<?php

declare(strict_types=1);

namespace App\Administration;

use Beffroi\Core\Domain\Key\MailSigningKey;

/**
 * What the administration API writes of a key the tenant signs its mail with: its public half or its certificate,
 * never its private half, which nothing reads back.
 */
final readonly class MailSigningKeyDocument
{
    /**
     * @return array<string, mixed>
     */
    public static function of(MailSigningKey $key): array
    {
        $senders = [];
        foreach ($key->addresses as $address) {
            $senders[] = $address->value;
        }

        return [
            'format' => $key->kind,
            'fingerprint' => $key->fingerprint,
            'current' => $key->isCurrent(),
            'senders' => $senders,
            'published' => $key->published,
            'set_at' => AdministrationAnswer::moment($key->setAt),
            'not_after' => AdministrationAnswer::moment($key->notAfter),
            'replaced_at' => AdministrationAnswer::moment($key->replacedAt),
        ];
    }
}
