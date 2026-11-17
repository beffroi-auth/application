<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Exception\ExceptionTrail;
use Beffroi\Oidc\Domain\EndSession\LogoutToken;
use function sprintf;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Back-Channel Logout 1.0 §2.5
 *
 * No redirect is followed (decision 0006).
 */
final readonly class LogoutTokenSender
{
    public const string PARAMETER = 'logout_token';

    public function __construct(
        private HttpClientInterface $http,
        public Timeout $timeout,
    ) {
    }

    public function send(string $logoutUri, string $logoutToken): OutboundDelivery
    {
        try {
            $response = $this->http->request('POST', $logoutUri, [
                'body' => [
                    self::PARAMETER => $logoutToken,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
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

    /**
     * Back-Channel Logout 1.0 §2.8
     *
     * A redirect is final too: it is not followed, so a retry gets the same answer.
     */
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
        if ($status === 400 || $status === 501) {
            return OutboundDelivery::refused(sprintf(
                'the client answered %d, which Back-Channel Logout 1.0 §2.8 makes final: it read the %s and refused it',
                $status,
                LogoutToken::TYPE,
            ));
        }

        return OutboundDelivery::unreachable(sprintf('the client answered %d', $status));
    }
}
