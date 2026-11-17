<?php

declare(strict_types=1);

namespace App\Configuration;

use Generator;
use IteratorAggregate;

/**
 * @template T of object
 *
 * @implements IteratorAggregate<int, T>
 */
final readonly class SwitchedServices implements IteratorAggregate
{
    /**
     * @var iterable<T>
     */
    private iterable $brought;

    /**
     * @param iterable<T> $wired
     * @param iterable<T> $brought a lazy iterator, so that nothing of it is built while the role is off
     */
    public function __construct(
        private iterable $wired,
        RoleSwitch $role,
        iterable $brought,
    ) {
        $this->brought = $role->on ? $brought : [];
    }

    /**
     * @return Generator<int, T>
     */
    public function getIterator(): Generator
    {
        foreach ($this->wired as $service) {
            yield $service;
        }
        foreach ($this->brought as $service) {
            yield $service;
        }
    }
}
