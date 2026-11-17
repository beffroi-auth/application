<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;

final class AccountHomeTest extends BrowserTestCase
{
    #[Test]
    public function aCardSaysThePageItOpensWhatThatPageIsForAndWhatTheAccountHoldsThere(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $card = $this->accountHome()->filter('li[data-section="beffroi_interaction_account_passkeys"]');

        // Then
        static::assertCount(1, $card->filter('div[data-section-head] span[data-section-icon] svg'));
        static::assertSame('/account/passkeys', $card->filter('a')->attr('href'));
        static::assertSame('Your passkeys', $card->filter('a')->text());
        static::assertSame(
            'Signing in with your device rather than a password.',
            $card->filter('p[data-section-about]')->text(),
        );
        static::assertSame('No passkey', $card->filter('span[data-state]')->text());
    }

    #[Test]
    public function everyCardCarriesTheIconOfItsPageAndTheLineThatSaysWhatItIsFor(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $page = $this->accountHome();

        // Then
        $cards = $page->filter('ul[data-sections] li[data-section]')->count();
        static::assertGreaterThan(0, $cards);
        static::assertSame($cards, $page->filter('li[data-section] span[data-section-icon] svg')->count());
        static::assertSame($cards, $page->filter('li[data-section] p[data-section-about]')->count());
        static::assertSame($cards, $page->filter('li[data-section] span[data-state]')->count());
    }
}
