<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Oidc\Domain\Client\RedirectUri;

/**
 * @extends StringObjectType<RedirectUri>
 */
final class RedirectUriType extends StringObjectType
{
    public const string NAME = 'beffroi_redirect_uri';

    protected function encode(object $value): string
    {
        return $value->value;
    }

    protected function decode(string $value): object
    {
        return new RedirectUri($value);
    }

    protected function expects(): string
    {
        return RedirectUri::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
