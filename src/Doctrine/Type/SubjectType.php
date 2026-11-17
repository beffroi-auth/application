<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Identity\Subject;

/**
 * @extends StringObjectType<Subject>
 */
final class SubjectType extends StringObjectType
{
    public const string NAME = 'beffroi_subject';

    protected function encode(object $value): string
    {
        return $value->value;
    }

    protected function decode(string $value): object
    {
        return new Subject($value);
    }

    protected function expects(): string
    {
        return Subject::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
