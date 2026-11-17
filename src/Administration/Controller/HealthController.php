<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Doctor\CheckInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Whether the instance holds together (decision 0040): every point `beffroi:doctor` looks at, in the order it is wired,
 * green or red with what it found. It reads and writes nothing, as the doctor does, so it is safe on an instance
 * serving traffic.
 */
#[AsController]
final readonly class HealthController
{
    /**
     * @param iterable<CheckInterface> $checks what the doctor looks at, in the order it is wired
     */
    public function __construct(
        private iterable $checks,
    ) {
    }

    #[Route('/health', name: 'beffroi_administration_health', methods: ['GET'])]
    public function __invoke(): Response
    {
        $checks = [];
        foreach ($this->checks as $check) {
            $finding = $check->finding();
            $checks[] = [
                'about' => $finding->about,
                'green' => $finding->green,
                'lines' => $finding->lines,
            ];
        }
        return AdministrationAnswer::of(['checks' => $checks]);
    }
}
