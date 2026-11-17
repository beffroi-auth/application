<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Oidc\Application\Authorization\StoredCodeChallenge;
use Beffroi\Oidc\Domain\Authorization\CodeChallenge;
use Beffroi\Oidc\Port\CodeChallengeMethodsInterface;
use LogicException;

/**
 * One column and not an embeddable: the challenge may be absent, and an embeddable cannot be null.
 *
 * @extends StringObjectType<CodeChallenge>
 */
final class CodeChallengeType extends StringObjectType implements NeedsCodeChallengeMethodsInterface
{
    public const string NAME = 'beffroi_code_challenge';

    private static ?CodeChallengeMethodsInterface $methods = null;

    public static function resolveWith(CodeChallengeMethodsInterface $methods): void
    {
        self::$methods = $methods;
    }

    protected function encode(object $value): string
    {
        return self::challenges()->document($value);
    }

    protected function decode(string $value): object
    {
        return self::challenges()->of($value);
    }

    protected function expects(): string
    {
        return CodeChallenge::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }

    private static function challenges(): StoredCodeChallenge
    {
        return new StoredCodeChallenge(
            self::$methods ?? throw new LogicException('The code challenge methods have not been handed to the DBAL type; the kernel does it when it boots.'),
        );
    }
}
