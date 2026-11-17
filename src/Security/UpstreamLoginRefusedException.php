<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Throwable;

final class UpstreamLoginRefusedException extends AuthenticationException
{
    public function __construct(
        public readonly Response $answer,
        string $message = 'The response of the upstream identity provider signs nobody in.',
        ?Throwable $previous = null,
        public readonly ?LoginFailure $failure = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
