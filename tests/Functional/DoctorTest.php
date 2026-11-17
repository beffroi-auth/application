<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Command\DoctorCommand;
use function is_string;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class DoctorTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        $cacheDirectory = self::$booted ? self::getContainer()->getParameter('kernel.cache_dir') : null;
        if (is_string($cacheDirectory)) {
            new Filesystem()->remove($cacheDirectory . '/keys');
        }
        parent::tearDown();
    }

    #[Test]
    public function theDoctorPassesOnThisApplicationAsItIsWired(): void
    {
        // Given
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);
        $keys = new CommandTester($application->find('beffroi:keys:generate'));
        $keys->execute([]);
        static::assertSame(Command::SUCCESS, $keys->getStatusCode(), $keys->getDisplay());
        $doctor = new CommandTester($application->find(DoctorCommand::NAME));

        // When
        $doctor->execute([]);

        // Then
        static::assertSame(Command::SUCCESS, $doctor->getStatusCode(), $doctor->getDisplay());
    }
}
