<?php

declare(strict_types=1);

namespace App\Demonstration\Trace;

use function is_string;
use function rawurldecode;
use Symfony\Component\HttpFoundation\Request;

/**
 * RFC 6749 §2.3.1, §3.2.1
 */
final readonly class NamedClient
{
    public const string PARAMETER = 'client_id';

    public static function of(Request $request): ?string
    {
        $user = $request->getUser();
        if (is_string($user) && $user !== '') {
            return rawurldecode($user);
        }
        foreach ([$request->request, $request->query] as $parameters) {
            $clientId = $parameters->get(self::PARAMETER);
            if (is_string($clientId) && $clientId !== '') {
                return $clientId;
            }
        }

        return null;
    }
}
