<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Uid\Uuid;

final readonly class RememberTheChosenLanguage implements Command
{
    public function __construct(
        public Uuid $user,
        public string $locale,
    ) {
    }
}
