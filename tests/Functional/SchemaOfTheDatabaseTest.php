<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;

final class SchemaOfTheDatabaseTest extends KernelTestCase
{
    use VerifyDeprecations;

    #[Test]
    public function theDatabaseTheMigrationsBuiltIsWhatTheMappingsDescribe(): void
    {
        // Given
        $console = self::console();

        // When
        $status = $console->run([
            'command' => 'doctrine:schema:validate',
            '-vv' => true,
        ]);

        // Then
        static::assertSame(Command::SUCCESS, $status, $console->getDisplay());
    }

    #[Test]
    public function validatingTheSchemaRaisesNoDeprecationOfDoctrine(): void
    {
        // Given
        $console = self::console();

        // Then
        $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6939');

        // When
        $console->run([
            'command' => 'doctrine:schema:validate',
        ]);
    }

    private static function console(): ApplicationTester
    {
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);

        return new ApplicationTester($application);
    }
}
