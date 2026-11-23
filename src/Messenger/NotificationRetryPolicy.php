<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Configuration\NoticesSection;
use App\Message\NoticeToAPerson;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Throwable;

/**
 * A notice to a person is retried on the schedule of the notices, every other message of the lane on the schedule of
 * a logout token, which `attempts()` and `waiting()` read.
 */
final readonly class NotificationRetryPolicy implements RetryStrategyInterface
{
    public RetrySchedule $schedule;

    /**
     * @throws InvalidConfigurationException
     */
    public function __construct(
        int $retries = 6,
        int $delay = 2,
        int $multiplier = 3,
        int $maxDelay = 60,
        public RetrySchedule $notices = new RetrySchedule(
            NoticesSection::DEFAULT_RETRIES,
            NoticesSection::DEFAULT_RETRY_DELAY,
            NoticesSection::DEFAULT_RETRY_MULTIPLIER,
            NoticesSection::DEFAULT_RETRY_MAX_DELAY,
            NoticesSection::NAME,
        ),
    ) {
        $this->schedule = new RetrySchedule($retries, $delay, $multiplier, $maxDelay, 'back_channel_logout');
    }

    public function isRetryable(Envelope $message, ?Throwable $throwable = null): bool
    {
        return $this->scheduleOf($message)->allows(RedeliveryStamp::getRetryCountFromEnvelope($message));
    }

    /**
     * @return int milliseconds
     */
    public function getWaitingTime(Envelope $message, ?Throwable $throwable = null): int
    {
        return $this->scheduleOf($message)->waitBefore(RedeliveryStamp::getRetryCountFromEnvelope($message)) * 1000;
    }

    public function attempts(): int
    {
        return $this->schedule->attempts();
    }

    /**
     * Seconds between the first send of a logout token and the last.
     */
    public function waiting(): int
    {
        return $this->schedule->waiting();
    }

    private function scheduleOf(Envelope $envelope): RetrySchedule
    {
        return $envelope->getMessage() instanceof NoticeToAPerson ? $this->notices : $this->schedule;
    }
}
