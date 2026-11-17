<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Closure;
use const E_DEPRECATED;
use const E_USER_DEPRECATED;
use PHPUnit\Framework\Attributes\Test;
use function restore_error_handler;
use function set_error_handler;
use Symfony\Component\HttpFoundation\Response;

final class RootOfTheApplicationTest extends BrowserTestCase
{
    /**
     * RFC 9110 §15.4.3, §15.4.2
     */
    #[Test]
    public function theRootSendsTheBrowserToTheHomeOfTheAccount(): void
    {
        // When
        $this->browser->request('GET', '/');

        // Then
        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('https://localhost/account', $response->headers->get('Location'));
    }

    #[Test]
    public function aRequestToTheRootRaisesNoDeprecation(): void
    {
        // When
        $deprecations = self::deprecationsRaisedWhile(fn (): object => $this->browser->request('GET', '/'));

        // Then
        static::assertSame([], $deprecations);
    }

    /**
     * @param Closure(): mixed $call
     *
     * @return list<string>
     */
    private static function deprecationsRaisedWhile(Closure $call): array
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_DEPRECATED | E_USER_DEPRECATED);
        try {
            $call();
        } finally {
            restore_error_handler();
        }

        return $deprecations;
    }
}
