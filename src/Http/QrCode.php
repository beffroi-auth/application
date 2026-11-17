<?php

declare(strict_types=1);

namespace App\Http;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use function preg_replace;
use SensitiveParameter;

/**
 * Drawn here, never by a service, which would be handed the secret an otpauth URI carries.
 */
final readonly class QrCode
{
    /**
     * In CSS pixels.
     */
    public const int SIZE = 240;

    public function svg(#[SensitiveParameter] string $contents): string
    {
        $svg = new Writer(new ImageRenderer(new RendererStyle(self::SIZE, 0), new SvgImageBackEnd()))->writeString($contents);

        return (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }
}
