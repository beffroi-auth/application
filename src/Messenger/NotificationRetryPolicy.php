<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Configuration\BoundedNumber;
use function min;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Throwable;

/**
 * Exact waits, without the jitter of Symfony's own strategy: the retirement of a signing key reads this schedule
 * to know how long a logout token may still be on its way.
 */
final readonly class NotificationRetryPolicy implements RetryStrategyInterface
{
    /**
     * The floors of BackChannelLogoutSection, checked again: a value from the environment is only a number here.
     *
     * @throws InvalidConfigurationException
     */
    public function __construct(
        public int $retries = 6,
        public int $delay = 2,
        public int $multiplier = 3,
        public int $maxDelay = 60,
    ) {
        BoundedNumber::assertAtLeast($retries, 0, 'back_channel_logout.retries', 'retries');
        BoundedNumber::assertAtLeast($delay, 1, 'back_channel_logout.retry_delay', 'seconds');
        BoundedNumber::assertAtLeast($multiplier, 1, 'back_channel_logout.retry_multiplier', 'times');
        BoundedNumber::assertAtLeast($maxDelay, 1, 'back_channel_logout.retry_max_delay', 'seconds');
    }

    public function isRetryable(Envelope $message, ?Throwable $throwable = null): bool
    {
        return RedeliveryStamp::getRetryCountFromEnvelope($message) < $this->retries;
    }

    /**
     * @return int milliseconds
     */
    public function getWaitingTime(Envelope $message, ?Throwable $throwable = null): int
    {
        return $this->waitBefore(RedeliveryStamp::getRetryCountFromEnvelope($message)) * 1000;
    }

    public function attempts(): int
    {
        return $this->retries + 1;
    }

    /**
     * Seconds between the first send and the last.
     */
    public function waiting(): int
    {
        $waiting = 0;
        for ($retry = 0; $retry < $this->retries; ++$retry) {
            $waiting += $this->waitBefore($retry);
        }

        return $waiting;
    }

    /**
     * Seconds, the ceiling applied at each step so that a long schedule cannot overflow.
     */
    private function waitBefore(int $retry): int
    {
        $wait = $this->delay;
        for ($step = 0; $step < $retry; ++$step) {
            $wait = min($this->maxDelay, $wait * $this->multiplier);
        }

        return $wait;
    }
}
