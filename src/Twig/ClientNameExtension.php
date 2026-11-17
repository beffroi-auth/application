<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFilter;
use function array_map;
use function htmlspecialchars;
use function is_string;
use function sprintf;
use function str_replace;
use const ENT_HTML5;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * The name a client gave itself (OIDC Registration §2) set apart in a sentence of the provider, so that a reader
 * tells what the client says of itself from what the provider says.
 */
final readonly class ClientNameExtension
{
    /**
     * Stands for the name while the sentence is escaped: a character of the private use area, which no message of
     * the provider writes, and which a parameter loses, a client writing what it sends as it likes.
     */
    private const string NAME = "\u{E000}";

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, string|int> $parameters
     */
    #[AsTwigFilter(name: 'trans_with_client', isSafe: ['html'])]
    public function translate(string $message, string $client, array $parameters = []): string
    {
        $parameters = array_map(
            static fn (string|int $value): string|int => is_string($value) ? str_replace(self::NAME, '', $value) : $value,
            $parameters,
        );
        $sentence = $this->translator->trans($message, [...$parameters, 'client' => self::NAME], 'beffroi');

        return str_replace(
            self::NAME,
            sprintf('<b data-client-name>%s</b>', self::escaped($client)),
            self::escaped($sentence),
        );
    }

    private static function escaped(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
