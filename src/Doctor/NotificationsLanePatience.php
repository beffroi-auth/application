<?php

declare(strict_types=1);

namespace App\Doctor;

use function array_keys;
use function array_map;
use App\Message\SendLogoutToken;
use App\Messenger\NotificationRetryPolicy;
use Beffroi\Core\Exception\ExceptionTrail;
use DateInterval;
use DateTimeImmutable;
use function implode;
use function intdiv;
use function iterator_to_array;
use Beffroi\Oidc\Port\LogoutTokenDeliveryInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use function sprintf;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Throwable;

/**
 * Whether a logout token is retried for as long as it stays verifiable, and no longer.
 *
 * A lane must be retried by the very NotificationRetryPolicy of the container: another instance is another schedule.
 */
final readonly class NotificationsLanePatience implements CheckInterface
{
    public const string ABOUT = 'Back-channel logout: how long a logout token is retried';

    /**
     * Past this many retries, a strategy is taken as unbounded.
     */
    private const int RETRIES_WALKED = 1000;

    /**
     * @param ContainerInterface $strategies messenger.retry_strategy_locator, keyed by transport name
     */
    public function __construct(
        private SendersLocatorInterface $senders,
        private ContainerInterface $strategies,
        private NotificationRetryPolicy $policy,
        private LogoutTokenDeliveryInterface $verifiable,
    ) {
    }

    public function finding(): Finding
    {
        $verifiable = self::duration(self::secondsOf($this->verifiable->deliveryBudget()));

        try {
            $lanes = $this->lanesOfALogoutToken();
        } catch (Throwable $exception) {
            return Finding::red(self::ABOUT, [
                sprintf('What carries a logout token cannot be read from the container: %s', ExceptionTrail::of($exception)),
                self::whatItCosts($verifiable),
            ]);
        }

        if ($lanes === []) {
            return Finding::red(self::ABOUT, [
                sprintf(
                    'Nothing carries a logout token: "%s" is routed to no transport, so a client is told inside the request that asked for the logout, and a client that does not answer at that moment is never told at all.',
                    SendLogoutToken::class,
                ),
                self::whatItCosts($verifiable),
                self::WHAT_TO_DO,
            ]);
        }

        $found = [];
        foreach ($lanes as $lane) {
            $strategy = $this->strategyOf($lane);
            if ($strategy === $this->policy) {
                continue;
            }
            $found[] = $strategy === null
                ? sprintf(
                    'This deployment sends a logout token to the transport "%s" and nothing retries it there, so one failed send is a client never told, and the %s a token stays verifiable are waited by nobody.',
                    $lane,
                    $verifiable,
                )
                : sprintf(
                    'This deployment retries a logout token on the transport "%s" for %s. One stops being verifiable after %s, which is how far ahead its "exp" is set and how long the key that signed it stays in the JWK Set, both read from the schedule this provider publishes (%s); that transport is retried by %s instead.',
                    $lane,
                    self::retried(self::waitingOf($strategy)),
                    $verifiable,
                    NotificationRetryPolicy::class,
                    $strategy::class,
                );
        }

        if ($found !== []) {
            $found[] = self::whatItCosts($verifiable);
            $found[] = self::WHAT_TO_DO;

            return Finding::red(self::ABOUT, $found);
        }

        return Finding::green(self::ABOUT, sprintf(
            'A logout token is retried on %s for %s and stops being verifiable after %s, both read from the schedule this provider publishes (%s): a client that is restarting is reached, and nothing arrives after the "exp" of the token has passed or after the key that signed it has left the JWK Set.',
            implode(', ', array_map(static fn (string $lane): string => sprintf('"%s"', $lane), $lanes)),
            self::duration($this->policy->waiting()),
            $verifiable,
            NotificationRetryPolicy::class,
        ));
    }

    private static function whatItCosts(string $verifiable): string
    {
        return sprintf(
            'What it costs: a user who signs out stays signed in on a client that was being retried, that client refusing a token whose "exp" has passed or whose signing key its JWK Set no longer publishes after %s. It shows after a key rotation or after a client restarts, it is never visible in a browser, and no test of a deployment covers it, every lane being synchronous in a test environment.',
            $verifiable,
        );
    }

    public const string WHAT_TO_DO = 'What to do: route the notifications back to the lane this provider declares ("async_notifications"), or give the transport this deployment chose the same schedule, "retry_strategy: { service: App\Messenger\NotificationRetryPolicy }". Writing the four numbers of a retry strategy on the lane the provider declares does nothing: the service it prepends survives the merge of a configuration that names no service, and the component prefers a service to its own numbers.';

    /**
     * @return list<string>
     */
    private function lanesOfALogoutToken(): array
    {
        /** @var array<string, SenderInterface> $senders */
        $senders = iterator_to_array($this->senders->getSenders(new Envelope(self::aLogoutToken())));

        return array_keys($senders);
    }

    private function strategyOf(string $lane): ?RetryStrategyInterface
    {
        if (! $this->strategies->has($lane)) {
            return null;
        }
        $strategy = $this->strategies->get($lane);

        return $strategy instanceof RetryStrategyInterface ? $strategy : null;
    }

    /**
     * In seconds, from the first send to the last; null when the strategy never gives up.
     */
    private static function waitingOf(RetryStrategyInterface $strategy): ?int
    {
        $message = self::aLogoutToken();
        $milliseconds = 0;
        for ($retry = 0; $retry < self::RETRIES_WALKED; ++$retry) {
            $envelope = new Envelope($message, [new RedeliveryStamp($retry)]);
            if (! $strategy->isRetryable($envelope)) {
                return intdiv($milliseconds, 1000);
            }
            $milliseconds += $strategy->getWaitingTime($envelope);
        }

        return null;
    }

    private static function retried(?int $seconds): string
    {
        return $seconds === null ? 'as long as it takes' : self::duration($seconds);
    }

    private static function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%d second%s', $seconds, $seconds === 1 ? '' : 's');
        }
        $minutes = sprintf('%d minute%s', intdiv($seconds, 60), intdiv($seconds, 60) === 1 ? '' : 's');
        $rest = $seconds % 60;

        return $rest === 0 ? $minutes : sprintf('%s %d second%s', $minutes, $rest, $rest === 1 ? '' : 's');
    }

    private static function secondsOf(DateInterval $interval): int
    {
        $epoch = new DateTimeImmutable('@0');

        return $epoch->add($interval)->getTimestamp();
    }

    /**
     * Built without its constructor: the locators route by class, and minting a token would ask the KMS to sign.
     */
    private static function aLogoutToken(): SendLogoutToken
    {
        return new ReflectionClass(SendLogoutToken::class)->newInstanceWithoutConstructor();
    }
}
