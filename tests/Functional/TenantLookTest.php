<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;

final class TenantLookTest extends BrowserTestCase
{
    #[Test]
    public function aPageShowsThePlaceholderMarkOfTheImageUnderTheHostOfTheIssuer(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $logo = $this->accountHome()->filter('img[data-tenant="logo"]');

        // Then
        static::assertSame('https://op.test/tenant-logo.svg', $logo->attr('src'));
        static::assertSame('op.test', $logo->attr('alt'));
    }

    #[Test]
    public function aPageIsDrawnInTheColoursOfTheImage(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $style = $this->accountHome()->filter('head style')->text();

        // Then
        static::assertStringContainsString('--color-primary: #1f6feb; --color-primary-content: #ffffff;', $style);
    }
}
