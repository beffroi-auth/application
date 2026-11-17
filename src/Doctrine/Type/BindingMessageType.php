<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Oidc\Domain\Ciba\BindingMessage;

/**
 * @extends StringObjectType<BindingMessage>
 */
final class BindingMessageType extends StringObjectType
{
    public const string NAME = 'beffroi_binding_message';

    protected function encode(object $value): string
    {
        return $value->value;
    }

    protected function decode(string $value): object
    {
        return new BindingMessage($value);
    }

    protected function expects(): string
    {
        return BindingMessage::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
