<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Exception\ExceptionTrail;
use function sprintf;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * CIBA Core §10.2
 */
final readonly class ClientNotificationSender
{
    public const string PARAMETER = 'auth_req_id';

    public function __construct(
        private HttpClientInterface $http,
        public Timeout $timeout,
    ) {
    }

    public function send(string $notificationEndpoint, string $notificationToken, string $authRequestId): OutboundDelivery
    {
        try {
            $response = $this->http->request('POST', $notificationEndpoint, [
                'json' => [
                    self::PARAMETER => $authRequestId,
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $notificationToken,
                    'Accept' => '*/*',
                ],
                'max_redirects' => 0,
                ...$this->timeout->options(),
            ]);

            return self::outcomeOf($response->getStatusCode(), $response->getHeaders(false)['location'][0] ?? null);
        } catch (Throwable $exception) {
            return OutboundDelivery::unreachable(sprintf('the client cannot be reached: %s', ExceptionTrail::of($exception)));
        }
    }

    private static function outcomeOf(int $status, ?string $location): OutboundDelivery
    {
        if ($status >= 200 && $status < 300) {
            return OutboundDelivery::delivered($status);
        }
        if ($status >= 300 && $status < 400) {
            return OutboundDelivery::refused(sprintf(
                'the client redirects to %s, and no outbound request follows a redirect; register that URL instead',
                $location ?? '?',
            ));
        }
        if ($status === 400 || $status === 401 || $status === 403 || $status === 404 || $status === 405 || $status === 501) {
            return OutboundDelivery::refused(sprintf(
                'the client answered %d, which says it read the notification and will not take it; the same answer would come back on every retry',
                $status,
            ));
        }

        return OutboundDelivery::unreachable(sprintf('the client answered %d', $status));
    }
}
