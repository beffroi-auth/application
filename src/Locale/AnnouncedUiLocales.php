<?php

declare(strict_types=1);

namespace App\Locale;

use function array_map;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Port\MetadataContributorInterface;
use function str_replace;

/**
 * OpenID Connect Discovery 1.0 §3 "ui_locales_supported", RFC 5646 §2.1
 */
final readonly class AnnouncedUiLocales implements MetadataContributorInterface
{
    public function __construct(
        private ServedLocales $served,
    ) {
    }

    public function metadata(Tenant $tenant): array
    {
        return [
            'ui_locales_supported' => array_map(static fn (string $locale): string => str_replace('_', '-', $locale), $this->served->all),
        ];
    }
}
