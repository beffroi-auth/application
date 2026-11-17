<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Http\BrowserPreferences;
use function array_map;
use function array_values;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

final class BrowserPreferencesTest extends BrowserTestCase
{
    #[Test]
    public function theFormOfTheThemeLeavesTheLanguageAsItWas(): void
    {
        // Given
        $this->chooseTheLanguage('fr');

        // When
        $this->browser->request('POST', '/theme', ['theme' => 'dark']);

        // Then
        static::assertSame('locale=fr&theme=dark', $this->preferences());
    }

    #[Test]
    public function theFormOfTheLanguageLeavesTheThemeAsItWas(): void
    {
        // Given
        $this->browser->request('POST', '/theme', ['theme' => 'dark']);

        // When
        $this->chooseTheLanguage('fr');

        // Then
        static::assertSame('locale=fr&theme=dark', $this->preferences());
    }

    #[Test]
    public function thePagesAreRenderedInTheThemeTheSlotNames(): void
    {
        // Given
        $this->browser->request('POST', '/theme', ['theme' => 'dark']);

        // When
        $page = $this->loginPage();

        // Then
        static::assertSame(['dark'], $page->filter('html')->extract(['data-theme']));
    }

    #[Test]
    public function aPageAndItsFormsSetTheSessionOfTheBrowserAndThePreferencesOfTheBrowserAndNothingElse(): void
    {
        // When
        $this->loginPage();
        $ofThePage = self::namesOf($this->browser->getResponse());
        $this->browser->request('POST', '/theme', ['theme' => 'dark']);

        // Then
        // The mock session storage of the test environment names the session MOCKSESSID.
        static::assertSame(['MOCKSESSID'], $ofThePage);
        static::assertSame([BrowserPreferences::COOKIE], self::namesOf($this->browser->getResponse()));
    }

    private function loginPage(): Crawler
    {
        return $this->browser->request('GET', '/login');
    }

    private function chooseTheLanguage(string $locale): void
    {
        $token = self::string($this->loginPage()->filter('form[data-locale="choice"] input[name="_token"]')->attr('value'));
        $this->browser->request('POST', '/locale', ['_token' => $token, 'locale' => $locale, 'target' => '/login']);
        static::assertSame(Response::HTTP_FOUND, $this->browser->getResponse()->getStatusCode());
    }

    private function preferences(): string
    {
        foreach ($this->browser->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === BrowserPreferences::COOKIE) {
                return $cookie->getValue() ?? '';
            }
        }

        static::fail('The answer carries no cookie of the preferences of a browser.');
    }

    /**
     * @return list<string>
     */
    private static function namesOf(Response $response): array
    {
        return array_values(array_map(static fn (Cookie $cookie): string => $cookie->getName(), $response->headers->getCookies()));
    }
}
