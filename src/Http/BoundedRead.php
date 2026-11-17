<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Exception\ExceptionTrail;
use function sprintf;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * The one read of a document a third party publishes at an address the provider was told: one GET asking for a media
 * type, no redirect followed (decision 0006), the timeout and the size of the document the caller is given, the body
 * read in chunks and the read stopped as soon as it is longer than the limit. A redirect, an answer outside 2xx, a body
 * too long and a call that fails are refused alike, with a sentence naming the address.
 *
 * What the document is, and the exception that says it cannot be had, are the caller's.
 */
final readonly class BoundedRead
{
    public function __construct(
        private HttpClientInterface $http,
        private Timeout $timeout,
        private ByteLimit $limit,
    ) {
    }

    /**
     * @throws ReadRefusedException
     */
    public function read(string $url, string $accept, ReadWords $words): string
    {
        try {
            $response = $this->http->request('GET', $url, [
                'headers' => [
                    'Accept' => $accept,
                ],
                'max_redirects' => 0,
                ...$this->timeout->options(),
            ]);
            $status = $response->getStatusCode();
            if ($status >= 300 && $status < 400) {
                throw new ReadRefusedException($words->redirected($url));
            }
            if ($status < 200 || $status >= 300) {
                throw new ReadRefusedException(sprintf('%s answered %d.', $url, $status));
            }
            $body = '';
            foreach ($this->http->stream($response) as $chunk) {
                $body .= $chunk->getContent();
                if ($this->limit->isExceededBy($body)) {
                    throw new ReadRefusedException($words->exceeded($url, $this->limit));
                }
            }

            return $body;
        } catch (ReadRefusedException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ReadRefusedException(sprintf('%s cannot be reached: %s', $url, ExceptionTrail::of($exception)), 0, $exception);
        }
    }
}
