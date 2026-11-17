<?php

declare(strict_types=1);

namespace App\Profiler;

use function array_any;
use Symfony\Contracts\Service\ResetInterface;

final class OpProfile implements ResetInterface
{
    /**
     * @var list<Observation>
     */
    private array $observations = [];

    private bool $touched = false;

    public function record(Observation $observation): void
    {
        if (array_any($this->observations, static fn (Observation $recorded): bool => $recorded->isSameAs($observation))) {
            return;
        }
        $this->observations[] = $observation;
    }

    public function touch(): void
    {
        $this->touched = true;
    }

    public function hasTouchedTheProvider(): bool
    {
        return $this->touched;
    }

    /**
     * @return list<Observation>
     */
    public function observations(): array
    {
        return $this->observations;
    }

    public function reset(): void
    {
        $this->observations = [];
        $this->touched = false;
    }
}
