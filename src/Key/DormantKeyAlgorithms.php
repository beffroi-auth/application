<?php

declare(strict_types=1);

namespace App\Key;

use Beffroi\Core\Bridge\Algorithm\WiredAlgorithms;
use Beffroi\Core\Domain\Key\KeyAlgorithmInterface;
use function str_starts_with;

/**
 * The algorithms of the keys a capability a deployment may turn off draws: a key file naming one while the
 * capability is off is read past, neither served nor rotated, and back in service once the capability is on again.
 */
final readonly class DormantKeyAlgorithms
{
    public const string TAG = 'beffroi.dormant_key_algorithm';

    /**
     * @var WiredAlgorithms<KeyAlgorithmInterface>
     */
    private WiredAlgorithms $dormant;

    /**
     * @param iterable<KeyAlgorithmInterface> $algorithms
     * @param list<string> $families what the names of the algorithms of a family begin with, one algorithm per holder
     */
    public function __construct(
        iterable $algorithms = [],
        private array $families = [],
    ) {
        $this->dormant = new WiredAlgorithms($algorithms);
    }

    public function holds(string $name): bool
    {
        foreach ($this->families as $family) {
            if (str_starts_with($name, $family)) {
                return true;
            }
        }

        return $this->dormant->named($name) !== null;
    }
}
