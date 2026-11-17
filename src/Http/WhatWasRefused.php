<?php

declare(strict_types=1);

namespace App\Http;

use function is_array;
use function is_string;
use Symfony\Component\HttpFoundation\Request;

/**
 * Carries a refusal across the redirection a form answers with, in the session, for the one read that follows.
 */
final readonly class WhatWasRefused
{
    public const string KEY = 'beffroi.refusal';

    public function keep(Request $request, string $code, ?string $field = null): void
    {
        $request->getSession()->set(self::KEY, [
            'code' => $code,
            'field' => $field,
        ]);
    }

    public function take(Request $request): ?Refusal
    {
        if (! $request->hasSession()) {
            return null;
        }
        $session = $request->getSession();
        $kept = $session->get(self::KEY);
        $session->remove(self::KEY);
        if (! is_array($kept) || ! is_string($kept['code'] ?? null)) {
            return null;
        }
        $field = $kept['field'] ?? null;

        return new Refusal($kept['code'], is_string($field) ? $field : null);
    }
}
