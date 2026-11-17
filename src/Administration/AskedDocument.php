<?php

declare(strict_types=1);

namespace App\Administration;

use Beffroi\Core\Domain\Encoding\Document;
use Beffroi\Core\Exception\InvalidArgumentException;
use function json_decode;
use const JSON_THROW_ON_ERROR;
use JsonException;
use Symfony\Component\HttpFoundation\Request;

/**
 * The JSON object a write of the administration API is asked with: null when the body is not one, or nests deeper than
 * the write reads.
 */
final class AskedDocument
{
    /**
     * @param int<1, max> $depth as json_decode() counts it, the default for a flat object
     */
    public static function of(Request $request, string $what, int $depth = 2): ?Document
    {
        if ($request->getContentTypeFormat() !== 'json') {
            return null;
        }
        try {
            return Document::of(json_decode($request->getContent(), true, $depth, JSON_THROW_ON_ERROR), $what);
        } catch (JsonException|InvalidArgumentException) {
            return null;
        }
    }
}
