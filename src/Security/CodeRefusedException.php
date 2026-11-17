<?php

declare(strict_types=1);

namespace App\Security;

use function is_array;
use LogicException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Throwable;

/**
 * Carries the CodeRefusal and nothing of the reason of the core: the security component keeps it in the HTTP
 * session.
 */
final class CodeRefusedException extends BadCredentialsException
{
    public function __construct(
        public readonly CodeRefusal $refusal,
        ?Throwable $previous = null,
    ) {
        parent::__construct('The code was refused.', 0, $previous);
    }

    /**
     * @return array{CodeRefusal, array<array-key, mixed>}
     */
    public function __serialize(): array
    {
        return [$this->refusal, parent::__serialize()];
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws LogicException
     */
    public function __unserialize(array $data): void
    {
        $refusal = $data[0] ?? null;
        $parentData = $data[1] ?? null;
        if (! $refusal instanceof CodeRefusal || ! is_array($parentData)) {
            throw new LogicException('The serialised refusal is not one this class wrote.');
        }
        $this->refusal = $refusal;
        parent::__unserialize($parentData);
    }
}
