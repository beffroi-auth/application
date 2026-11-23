<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

final class AccountNavigationTest extends BrowserTestCase
{
    #[Test]
    public function everyPageOfTheAccountIsInTheColumnOfPages(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $page = $this->accountPage('/account');

        // Then
        static::assertSame(
            [
                '/account',
                '/account/approvals',
                '/account/profile',
                '/account/archive',
                '/account/password',
                '/account/passkeys',
                '/account/two-factor',
                '/account/notifications',
                '/account/sessions',
                '/account/consents',
                '/account/acts',
            ],
            $page->filter('#layout-sidebar-pages a')->extract(['href']),
        );
    }

    #[Test]
    public function aGroupOfPagesNamesTheHeadingItIsReadUnder(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $page = $this->accountPage('/account');

        // Then
        static::assertSame(
            ['Personal data', 'Security', 'Activity'],
            $page->filter('#layout-sidebar-pages ul[aria-label]')->extract(['aria-label']),
        );
    }

    #[Test]
    public function thePageBeingReadSaysSoInTheColumnOfPages(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $page = $this->accountPage('/account/sessions');

        // Then
        static::assertSame(
            ['/account/sessions'],
            $page->filter('#layout-sidebar-pages a[aria-current="page"]')->extract(['href']),
        );
    }

    #[Test]
    public function theHeadOfAPageSaysWhereTheReaderIs(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $page = $this->accountPage('/account/sessions');

        // Then
        static::assertSame(
            ['Your account', 'Open sessions'],
            $page->filter('header[data-page-header] nav li')->each(static fn (Crawler $item): string => $item->text()),
        );
        static::assertCount(0, $page->filter('a[data-back]'));
    }

    #[Test]
    public function whoIsSignedInAndTheWayOutAreInTheBarAndNotOnThePage(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $page = $this->accountPage('/account');

        // Then
        static::assertCount(1, $page->filter('#layout-topbar div[data-control="user"] a[href="/logout"]'));
        static::assertCount(0, $page->filter('main a[href="/logout"]'));
    }

    #[Test]
    public function theControlsOfTheBarShareOneGroupAndOneVeil(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $page = $this->accountPage('/account');

        // Then
        $panels = $page->filter('#layout-topbar div[data-control] > div[data-control="panel"]')->count();
        static::assertSame($panels + 1, $page->filter('#layout-topbar input[name="layout-control"]')->count());
        static::assertCount(1, $page->filter('#layout-topbar input[name="layout-control"][checked]'));
        static::assertCount(1, $page->filter('#layout-topbar input#layout-control-none[checked]'));
        static::assertCount(1, $page->filter('#layout-topbar label[for="layout-control-none"][data-control="veil"]'));
    }

    #[Test]
    public function theActionOfAPageIsReadBesideItsTitle(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $page = $this->accountPage('/account/two-factor');

        // Then
        static::assertCount(1, $page->filter('header[data-page-header] form[data-two-factor="begin"]'));
    }

    #[Test]
    public function aRefusalIsNotServedInTheSkeletonOfTheAccount(): void
    {
        // When
        $page = $this->browser->request('GET', '/account/profile');

        // Then
        static::assertCount(1, $page->filter('#interaction-column p[data-error="login_required"]'));
        static::assertCount(0, $page->filter('#layout-sidebar'));
        static::assertCount(0, $page->filter('main[data-page="account"]'));
    }

    private function accountPage(string $path): Crawler
    {
        $page = $this->browser->request('GET', $path);
        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return $page;
    }
}
