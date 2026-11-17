<?php

declare(strict_types=1);

namespace App\Fido\Conformance;

use function is_array;
use function is_bool;
use function is_string;
use function sprintf;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

final readonly class ServerRequest
{
    /**
     * @param array<string, mixed> $members
     */
    private function __construct(
        private array $members,
    ) {
    }

    /**
     * @throws ServerRequestException
     */
    public static function fromRequest(Request $request): self
    {
        try {
            $members = $request->toArray();
        } catch (Throwable $exception) {
            throw new ServerRequestException('The body is not a JSON object.', previous: $exception);
        }
        $named = [];
        foreach ($members as $name => $value) {
            $named[(string) $name] = $value;
        }

        return new self($named);
    }

    /**
     * @throws ServerRequestException
     */
    public function string(string $name): string
    {
        $value = $this->optionalString($name);
        if ($value === null) {
            throw new ServerRequestException(sprintf('"%s" is required.', $name));
        }

        return $value;
    }

    /**
     * @throws ServerRequestException
     */
    public function optionalString(string $name): ?string
    {
        $value = $this->members[$name] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || $value === '') {
            throw new ServerRequestException(sprintf('"%s" is not a non-empty string.', $name));
        }

        return $value;
    }

    /**
     * @throws ServerRequestException
     */
    public function optionalObject(string $name): ?self
    {
        $value = $this->members[$name] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_array($value)) {
            throw new ServerRequestException(sprintf('"%s" is not an object.', $name));
        }
        $named = [];
        foreach ($value as $member => $inner) {
            $named[(string) $member] = $inner;
        }

        return new self($named);
    }

    /**
     * @throws ServerRequestException
     */
    public function optionalBool(string $name): ?bool
    {
        $value = $this->members[$name] ?? null;
        if ($value !== null && ! is_bool($value)) {
            throw new ServerRequestException(sprintf('"%s" is not a boolean.', $name));
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function members(): array
    {
        return $this->members;
    }
}
