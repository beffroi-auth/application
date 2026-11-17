<?php

declare(strict_types=1);

namespace App\Http;

use function sprintf;

/**
 * What a bounded read says of the document it was asked for, the only two sentences that differ from one document to
 * another: where the document is published when the address redirects, and how much is too much for a document of
 * its kind.
 */
final readonly class ReadWords
{
    /**
     * @param string $redirectAdvice the end of the sentence that follows "redirects, and no outbound request follows a redirect; "
     * @param string $excess         the sentence for a body that is too long, with the address as its first
     *                               placeholder and the limit in bytes as its second
     */
    public function __construct(
        private string $redirectAdvice,
        private string $excess,
    ) {
    }

    public function redirected(string $url): string
    {
        return sprintf('%s redirects, and no outbound request follows a redirect; %s', $url, $this->redirectAdvice);
    }

    public function exceeded(string $url, ByteLimit $limit): string
    {
        return sprintf($this->excess, $url, $limit->bytes);
    }
}
