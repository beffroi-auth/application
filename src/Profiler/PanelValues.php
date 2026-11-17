<?php

declare(strict_types=1);

namespace App\Profiler;

use Beffroi\Oidc\Port\ClaimsProviderInterface;
use function implode;
use function in_array;
use function json_encode;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Writes a named value for the panel, never giving a credential or a claim about a user.
 */
final readonly class PanelValues
{
    public function __construct(
        private ClaimsProviderInterface $claims,
    ) {
    }

    public function of(string $name, mixed $value): string
    {
        if (WithheldValues::withholds($name)) {
            return '(present, withheld)';
        }
        if (in_array($name, $this->claims->supportedClaims(), true)) {
            return '(a claim about the user, named only)';
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public function all(array $values, string $whenEmpty = 'none'): string
    {
        $written = [];
        foreach ($values as $name => $value) {
            $written[] = $name . '=' . $this->of((string) $name, $value);
        }

        return $written === [] ? $whenEmpty : implode("\n", $written);
    }
}
