<?php

declare(strict_types=1);

namespace App\Doctrine\PersonalData;

use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Core\Port\AmbientTenantInterface;
use SensitiveParameter;
use function sprintf;
use function strpos;
use function substr;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeDecrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;
use Throwable;

/**
 * Prefixes the scope with the slug of the ambient tenant and binds the envelope to it. A write with no ambient tenant
 * is refused: a value sealed outside its tenant's scope is out of reach of that tenant's erasure.
 */
final readonly class TenantScopedEncrypter implements EnvelopeDecrypterInterface, EnvelopeEncrypterInterface
{
    /**
     * Never changed: nothing rewraps an envelope, so every value sealed under it must keep opening under it.
     */
    public const string AAD = 'beffroi:personal-data:v1:';

    /**
     * Never a character of a slug, so that the slug of a scope is what precedes the first one.
     */
    public const string SEPARATOR = '.';

    public function __construct(
        private EnvelopeEncrypterInterface&EnvelopeDecrypterInterface $envelopes,
        private AmbientTenantInterface $tenants,
    ) {
    }

    /**
     * @param string $key the family of columns
     *
     * @throws RuntimeException when no tenant is ambient
     */
    public function encrypt(string $key, #[SensitiveParameter] string $plaintext, string $aad = ''): Envelope
    {
        $slug = $this->slug($key);

        return $this->envelopes->encrypt(self::scope($slug, $key), $plaintext, $aad . self::AAD . $slug);
    }

    /**
     * @throws RuntimeException when no tenant is ambient
     */
    public function decrypt(Envelope $envelope, string $aad = ''): string
    {
        return $this->envelopes->decrypt($envelope, $aad . self::AAD . $this->slug(null));
    }

    public static function scope(string $slug, string $family): string
    {
        return $slug . self::SEPARATOR . $family;
    }

    public static function slugOf(string $scope): string
    {
        $separator = strpos($scope, self::SEPARATOR);

        return $separator === false ? $scope : substr($scope, 0, $separator);
    }

    /**
     * @throws RuntimeException
     */
    private function slug(?string $family): string
    {
        try {
            return $this->tenants->provide()->slug;
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf(
                    'No tenant is ambient, so %s cannot be %s: a value sealed outside the scope of its tenant is a value no destruction of that tenant\'s keys reaches.',
                    $family === null ? 'a sealed value' : sprintf('a column of the "%s" family', $family),
                    $family === null ? 'read' : 'sealed',
                ),
                0,
                $exception,
            );
        }
    }
}
