<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Command\DoctorCommand;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DoctorTest extends KernelTestCase
{
    #[Test]
    public function theDoctorPassesOnThisApplicationAsItIsWired(): void
    {
        // Given
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);
        $doctor = new CommandTester($application->find(DoctorCommand::NAME));

        // When
        $doctor->execute([]);

        // Then
        static::assertSame(Command::SUCCESS, $doctor->getStatusCode(), $doctor->getDisplay());
    }
}
