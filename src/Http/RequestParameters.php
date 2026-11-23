<?php

declare(strict_types=1);

namespace App\Http;

use function array_pad;
use function count;
use function explode;
use function is_string;
use function ltrim;
use function str_contains;
use function str_starts_with;
use function strtr;
use Symfony\Component\HttpFoundation\Request;
use function urldecode;

/**
 * The parameters the module reads: the query or the form body at a browser endpoint, the form body alone at a
 * server-to-server one (RFC 6750 §2.3, RFC 9700 §4.3.2). PHP keeps the last of a repeated name, so repetitions are
 * counted on the encoded source and handed over as a list.
 */
final readonly class RequestParameters
{
    /**
     * @return array<string, mixed>
     */
    public static function ofBrowserRequest(Request $request): array
    {
        return $request->isMethod('POST')
            ? self::named($request->request->all(), self::body($request))
            : self::named($request->query->all(), self::queryString($request));
    }

    /**
     * @return array<string, mixed>
     */
    public static function ofForm(Request $request): array
    {
        return self::named($request->request->all(), self::body($request));
    }

    /**
     * @param array<array-key, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private static function named(array $parameters, string $source): array
    {
        $named = [];
        foreach ($parameters as $name => $value) {
            if (is_string($name)) {
                $named[$name] = $value;
            }
        }
        foreach (self::repeated($source) as $name => $values) {
            $named[$name] = $values;
        }

        return $named;
    }

    /**
     * A name PHP decodes into a structure ("a[b]") is left to PHP.
     *
     * @return array<string, list<string>> by name, the values in order
     */
    private static function repeated(string $source): array
    {
        $occurrences = [];
        foreach (explode('&', $source) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $name = urldecode($name);
            if ($name === '' || str_contains($name, '[')) {
                continue;
            }
            $occurrences[self::asPhpFilesIt($name)][] = urldecode($value);
        }

        $repeated = [];
        foreach ($occurrences as $name => $values) {
            if (count($values) > 1) {
                $repeated[$name] = $values;
            }
        }

        return $repeated;
    }

    /**
     * PHP drops the leading spaces of a name and reads a space or a dot as "_", so two spellings fill one parameter.
     */
    private static function asPhpFilesIt(string $name): string
    {
        return strtr(ltrim($name, ' '), [
            ' ' => '_',
            '.' => '_',
        ]);
    }

    private static function queryString(Request $request): string
    {
        $query = $request->server->get('QUERY_STRING');

        return is_string($query) ? $query : '';
    }

    private static function body(Request $request): string
    {
        $contentType = $request->headers->get('Content-Type');
        if ($contentType === null || ! str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
            return '';
        }
        $body = $request->getContent();

        return is_string($body) ? $body : '';
    }
}
