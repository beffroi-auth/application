<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Configuration\BoundedNumber;
use function min;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Exact waits, without the jitter of Symfony's own strategy: the retirement of a signing key reads the schedule of a
 * logout token to know how long one may still be on its way.
 */
final readonly class RetrySchedule
{
    /**
     * The floors of the configuration tree, checked again: a value from the environment is only a number here.
     *
     * @param string $setting the node of the "beffroi" configuration the four numbers are read from
     *
     * @throws InvalidConfigurationException
     */
    public function __construct(
        public int $retries,
        public int $delay,
        public int $multiplier,
        public int $maxDelay,
        string $setting,
    ) {
        BoundedNumber::assertAtLeast($retries, 0, $setting . '.retries', 'retries');
        BoundedNumber::assertAtLeast($delay, 1, $setting . '.retry_delay', 'seconds');
        BoundedNumber::assertAtLeast($multiplier, 1, $setting . '.retry_multiplier', 'times');
        BoundedNumber::assertAtLeast($maxDelay, 1, $setting . '.retry_max_delay', 'seconds');
    }

    /**
     * @param int $retry how many times the message came back already
     */
    public function allows(int $retry): bool
    {
        return $retry < $this->retries;
    }

    /**
     * Seconds, the ceiling applied at each step so that a long schedule cannot overflow.
     *
     * @param int $retry how many times the message came back already
     */
    public function waitBefore(int $retry): int
    {
        $wait = $this->delay;
        for ($step = 0; $step < $retry; ++$step) {
            $wait = min($this->maxDelay, $wait * $this->multiplier);
        }

        return $wait;
    }

    public function attempts(): int
    {
        return $this->retries + 1;
    }

    /**
     * Seconds between the first attempt and the last.
     */
    public function waiting(): int
    {
        $waiting = 0;
        for ($retry = 0; $retry < $this->retries; ++$retry) {
            $waiting += $this->waitBefore($retry);
        }

        return $waiting;
    }
}
