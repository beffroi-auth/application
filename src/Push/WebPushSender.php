<?php

declare(strict_types=1);

namespace App\Push;

use App\Http\Timeout;
use DateTimeImmutable;
use Beffroi\Core\Domain\Identity\PushSubscription;
use Beffroi\Core\Exception\ExceptionTrail;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Core\Port\ApplicationServerKeyProviderInterface;
use function is_string;
use Psr\Clock\ClockInterface;
use function sprintf;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use WebPush\ExtensionManager;
use WebPush\Notification;
use WebPush\Payload\AES128GCM;
use WebPush\Payload\PayloadExtension;
use WebPush\Subscription;
use WebPush\TTLExtension;
use WebPush\UrgencyExtension;
use WebPush\VAPID\VAPIDExtension;
use WebPush\VAPID\WebTokenProvider;
use WebPush\WebPush;

/**
 * RFC 8030, RFC 8291, RFC 8292
 *
 * Signs with the key whose "kid" the subscription carries, never the active one: a rotation never moves what an
 * existing subscription is bound to.
 */
final readonly class WebPushSender
{
    /**
     * RFC 8030 §5.3
     */
    private const string URGENCY = 'high';

    /**
     * RFC 8291 §4
     */
    private const string CONTENT_ENCODING = 'aes128gcm';

    /**
     * RFC 8291 §4: one length for every notification, so that its size does not tell a push service how long a
     * binding message is. Below the library's 3052, which leaves under a kilobyte of the 4096 octets allowed to
     * text a client wrote.
     */
    private const int PADDED_TO = 1024;

    /**
     * @param string $subject a "mailto:" or "https:" URI (RFC 8292 §2.1)
     *
     * @throws InvalidConfigurationException when the subject is empty
     */
    public function __construct(
        private ApplicationServerKeyProviderInterface $keys,
        private HttpClientInterface $http,
        private ClockInterface $clock,
        public string $subject,
        public Timeout $timeout,
    ) {
        self::assertSubject($subject);
    }

    /**
     * @throws InvalidConfigurationException when the subject is empty
     */
    public static function assertSubject(string $subject): void
    {
        if ($subject === '') {
            throw new InvalidConfigurationException('The "beffroi" configuration key "web_push.subject" names the "mailto:" or "https:" URI a push service can reach the operator at (RFC 8292 section 2.1), and it is empty.');
        }
    }

    public function send(PushSubscription $subscription, PushMessage $message): PushDelivery
    {
        try {
            $unsealed = $this->keys->applicationServerKey($subscription->tenant, $subscription->applicationServerKid);
        } catch (KeyNotFoundException $exception) {
            return PushDelivery::gone($exception->getMessage());
        }
        $privateKey = $unsealed->privateJwk['d'] ?? null;
        if (! is_string($privateKey)) {
            return PushDelivery::unreachable('the application server key of the tenant carries no private half');
        }
        try {
            $report = $this->webPush(ApplicationServerKey::of($unsealed->key)->publicKey, $privateKey)->send(
                self::notification($message, $this->clock->now()),
                self::subscription($subscription),
            );
        } catch (Throwable $exception) {
            return PushDelivery::unreachable(sprintf('the push service cannot be reached: %s', ExceptionTrail::of($exception)));
        }
        if ($report->isSuccess()) {
            return PushDelivery::delivered($report->getStatusCode());
        }

        return $report->isSubscriptionExpired()
            ? PushDelivery::gone(sprintf('the push service answered %d: the subscription is gone', $report->getStatusCode()))
            : PushDelivery::unreachable(sprintf('the push service answered %d', $report->getStatusCode()));
    }

    private function webPush(string $publicKey, string $privateKey): WebPush
    {
        $extensions = ExtensionManager::create()
            ->add(TTLExtension::create())
            ->add(UrgencyExtension::create())
            ->add(PayloadExtension::create()->addContentEncoding(AES128GCM::create($this->clock)->customPadding(self::PADDED_TO)))
            ->add(VAPIDExtension::create($this->subject, new WebTokenProvider($publicKey, $privateKey), $this->clock));

        return WebPush::create($this->http->withOptions([
            ...$this->timeout->options(),
            'max_redirects' => 0,
        ]), $extensions);
    }

    private static function notification(PushMessage $message, DateTimeImmutable $now): Notification
    {
        return Notification::create()->withTTL($message->timeToLive($now))->withUrgency(self::URGENCY)->withPayload($message->payload());
    }

    private static function subscription(PushSubscription $subscription): Subscription
    {
        return Subscription::create($subscription->endpoint)
            ->withContentEncodings([self::CONTENT_ENCODING])
            ->setKey('p256dh', $subscription->publicKey)
            ->setKey('auth', $subscription->authSecret);
    }
}
