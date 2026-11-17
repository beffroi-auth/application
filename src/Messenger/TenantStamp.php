<?php

declare(strict_types=1);

namespace App\Messenger;

use Beffroi\Core\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Never read to decide anything: the protected header of the sealed body decides the scope, and a swapped
 * stamp only makes the opening fail.
 */
final readonly class TenantStamp implements StampInterface
{
    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        public string $slug,
    ) {
        if ($slug === '') {
            throw new InvalidArgumentException('A message carries the slug of the tenant it is written for.');
        }
    }
}
