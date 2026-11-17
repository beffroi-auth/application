<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\Timeout;
use Beffroi\Core\Domain\Credential\BreachCount;
use Beffroi\Core\Domain\Credential\PasswordDigest;
use Beffroi\Core\Exception\BreachLookupFailedException;
use Beffroi\Core\Port\BreachedPasswordsInterface;
use function explode;
use function hash_equals;
use function preg_match;
use function rtrim;
use SensitiveParameter;
use function sprintf;
use function strtoupper;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function trim;

/**
 * A timeout, a failed status or an unreadable body is the exception of the port, never a count of zero.
 */
final readonly class PwnedPasswords implements BreachedPasswordsInterface
{
    public const string RANGE_ENDPOINT = 'https://api.pwnedpasswords.com/range/';

    private const string COUNTED_SUFFIX = '/^([0-9A-F]{35}):([0-9]+)$/';

    public function __construct(
        private HttpClientInterface $http,
        private string $endpoint = '',
        private Timeout $timeout = new Timeout(2.0),
    ) {
    }

    public function looksUp(): bool
    {
        return $this->endpoint !== '';
    }

    public function timesSeen(#[SensitiveParameter] PasswordDigest $digest): BreachCount
    {
        if (! $this->looksUp()) {
            return BreachCount::nothingAsked();
        }

        return BreachCount::times(self::countIn($this->answer($digest->prefix()), $digest->suffix()));
    }

    /**
     * @throws BreachLookupFailedException
     */
    private function answer(string $prefix): string
    {
        try {
            $response = $this->http->request('GET', rtrim($this->endpoint, '/') . '/' . $prefix, [
                'headers' => [
                    // Padded, so that the length of the answer says nothing of the bucket.
                    'Add-Padding' => 'true',
                    'Accept' => 'text/plain',
                ],
                'max_redirects' => 0,
                ...$this->timeout->options(),
            ]);
            $status = $response->getStatusCode();
            if ($status !== 200) {
                throw new BreachLookupFailedException(sprintf('The list of compromised passwords answered %d.', $status));
            }

            return $response->getContent(false);
        } catch (HttpExceptionInterface $exception) {
            throw new BreachLookupFailedException('The list of compromised passwords could not be reached.', 0, $exception);
        }
    }

    /**
     * @throws BreachLookupFailedException
     */
    private static function countIn(string $body, #[SensitiveParameter] string $suffix): int
    {
        $counted = 0;
        $lines = 0;
        foreach (explode("\n", $body) as $line) {
            $line = strtoupper(trim($line));
            if ($line === '') {
                continue;
            }
            if (preg_match(self::COUNTED_SUFFIX, $line, $matches) !== 1) {
                throw new BreachLookupFailedException('The list of compromised passwords answered a body that is not a range.');
            }
            ++$lines;
            if (hash_equals($suffix, $matches[1])) {
                $counted = (int) $matches[2];
            }
        }
        if ($lines === 0) {
            throw new BreachLookupFailedException('The list of compromised passwords answered an empty body.');
        }

        return $counted;
    }
}
