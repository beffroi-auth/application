<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Http\RequestChannelLogger;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Decision 0026: the channel the router of Symfony writes each request to, with its route parameters and its URI,
 * keeps no value of a ticket and no value of a query parameter.
 */
final class TheLogOfARequestTest extends KernelTestCase
{
    #[Test]
    public function theRouterWritesToTheLoggerThatWithholdsTheValues(): void
    {
        // When
        $logger = self::getContainer()->get('monolog.logger.request');

        // Then
        static::assertInstanceOf(RequestChannelLogger::class, $logger);
    }
}
