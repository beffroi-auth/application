<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Session\RequestContext;

/**
 * One column, not an embeddable: sealing needs a type per table, and an embeddable fixes one for the whole model.
 *
 * @extends JsonObjectType<RequestContext>
 */
final class RequestContextType extends JsonObjectType
{
    public const string NAME = 'beffroi_request_context';

    protected function encode(object $value): array
    {
        return $value->toDocument();
    }

    protected function decode(array $data): object
    {
        return RequestContext::fromDocument($data);
    }

    protected function expects(): string
    {
        return RequestContext::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
