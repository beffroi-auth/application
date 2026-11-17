<?php

declare(strict_types=1);

namespace App\Profiler;

use function array_map;
use function count;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

final class OpCollector extends DataCollector
{
    public const string NAME = 'beffroi';

    public function __construct(
        private readonly OpProfile $profile,
    ) {
        $this->data = [];
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        if (! $this->profile->hasTouchedTheProvider()) {
            return;
        }
        $this->data = [
            'observations' => array_map(static fn (Observation $observation): array => [
                'tab' => $observation->tab,
                'heading' => $observation->heading,
                'entries' => $observation->entries,
            ], $this->profile->observations()),
        ];
    }

    /**
     * @return list<array{tab: string, heading: string, entries: array<string, string>}>
     */
    public function getObservations(): array
    {
        /** @var list<array{tab: string, heading: string, entries: array<string, string>}> $observations */
        $observations = $this->data['observations'] ?? [];

        return $observations;
    }

    /**
     * @return array<string, list<array{heading: string, entries: array<string, string>}>>
     */
    public function getTabs(): array
    {
        $tabs = [];
        foreach ($this->getObservations() as $observation) {
            $tabs[$observation['tab']][] = [
                'heading' => $observation['heading'],
                'entries' => $observation['entries'],
            ];
        }

        return $tabs;
    }

    public function getCount(): int
    {
        return count($this->getObservations());
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
