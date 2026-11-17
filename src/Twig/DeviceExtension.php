<?php

declare(strict_types=1);

namespace App\Twig;

use Beffroi\Core\Domain\Session\Device;
use Beffroi\Core\Domain\Session\RequestContext;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFilter;

final readonly class DeviceExtension
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    #[AsTwigFilter(name: 'beffroi_device')]
    public function device(?RequestContext $provenance): ?string
    {
        if ($provenance === null) {
            return null;
        }
        $device = $provenance->device ?? Device::readFrom($provenance->userAgent);
        if ($device === null) {
            return $provenance->userAgent;
        }
        if ($device->browser !== null && $device->platform !== null) {
            return $this->translator->trans(
                'provenance.device',
                [
                    'browser' => $device->browser,
                    'platform' => $device->platform,
                ],
                'beffroi',
            );
        }

        return $device->browser ?? $device->platform ?? $provenance->userAgent;
    }
}
