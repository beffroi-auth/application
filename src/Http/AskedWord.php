<?php

declare(strict_types=1);

namespace App\Http;

use function is_string;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use function trim;

/**
 * A word of the query string a table of the account is narrowed by. A word that is not one the table offers
 * narrows nothing, as a period that ends before it starts does (AskedPeriod).
 */
final readonly class AskedWord
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    /**
     * @template T of string
     *
     * @param list<T> $offered
     *
     * @return T|null
     */
    public function among(string $name, array $offered): ?string
    {
        $asked = $this->asked($name);
        foreach ($offered as $word) {
            if ($asked === $word) {
                return $word;
            }
        }

        return null;
    }

    /**
     * @return bool|null true for the first word, false for the second, null for any other
     */
    public function either(string $name, string $yes, string $no): ?bool
    {
        $asked = $this->among($name, [$yes, $no]);

        return $asked === null ? null : $asked === $yes;
    }

    /**
     * @return non-empty-string|null what was typed, without the spaces around it
     */
    public function text(string $name): ?string
    {
        $asked = trim($this->asked($name) ?? '');

        return $asked === '' ? null : $asked;
    }

    public function uuid(string $name): ?Uuid
    {
        $asked = $this->asked($name);

        return $asked !== null && Uuid::isValid($asked) ? Uuid::fromString($asked) : null;
    }

    /**
     * Read whole, InputBag::get() refusing a list with an exception: a list narrows nothing.
     */
    private function asked(string $name): ?string
    {
        $asked = $this->requests->getCurrentRequest()?->query->all()[$name] ?? null;

        return is_string($asked) ? $asked : null;
    }
}
