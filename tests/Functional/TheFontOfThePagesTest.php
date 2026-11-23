<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use function array_map;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;

/**
 * Brand guidelines v1.0, p. 36: the font of the default templates is served by the provider itself.
 */
final class TheFontOfThePagesTest extends KernelTestCase
{
    #[Test]
    #[DataProvider('provideFonts')]
    public function theStylesheetLoadsTheFontFromTheAssetsOfTheProvider(string $font): void
    {
        // Given
        $assets = self::assets();

        // When
        $stylesheet = $assets->getAsset('styles/app.css');

        // Then
        static::assertNotNull($stylesheet);
        static::assertContains(
            $font,
            array_map(static fn(MappedAsset $loaded): string => $loaded->logicalPath, $stylesheet->getDependencies()),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideFonts(): iterable
    {
        yield 'Archivo, every European language in Latin script' => ['fonts/archivo.woff2'];
        yield 'Noto Sans, Greek and Cyrillic' => ['fonts/noto-sans-greek-cyrillic.woff2'];
    }

    private static function assets(): AssetMapperInterface
    {
        $assets = self::getContainer()->get(AssetMapperInterface::class);
        static::assertInstanceOf(AssetMapperInterface::class, $assets);

        return $assets;
    }
}
