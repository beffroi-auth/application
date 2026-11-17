<?php

declare(strict_types=1);

namespace App\Http;

use function is_array;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Read back once, so that a challenge is answered once and by the browser it was drawn for (WebAuthn Level 3 §13.4.3).
 */
final readonly class PasskeyOptionsStore
{
    public const string CREATION_KEY = 'beffroi.passkey.creation';

    public const string REQUEST_KEY = 'beffroi.passkey.request';

    public function storeCreation(SessionInterface $session, Uuid $userId, PublicKeyCredentialCreationOptions $options): void
    {
        $session->set(self::CREATION_KEY, [
            'user' => $userId->toRfc4122(),
            'options' => $options,
        ]);
    }

    public function takeCreation(SessionInterface $session, Uuid $userId): ?PublicKeyCredentialCreationOptions
    {
        $stored = $session->remove(self::CREATION_KEY);
        if (! is_array($stored) || ($stored['user'] ?? null) !== $userId->toRfc4122()) {
            return null;
        }
        $options = $stored['options'] ?? null;

        return $options instanceof PublicKeyCredentialCreationOptions ? $options : null;
    }

    public function storeRequest(SessionInterface $session, PublicKeyCredentialRequestOptions $options): void
    {
        $session->set(self::REQUEST_KEY, $options);
    }

    public function takeRequest(SessionInterface $session): ?PublicKeyCredentialRequestOptions
    {
        $options = $session->remove(self::REQUEST_KEY);

        return $options instanceof PublicKeyCredentialRequestOptions ? $options : null;
    }
}
