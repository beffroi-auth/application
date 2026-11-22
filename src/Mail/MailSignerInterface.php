<?php

declare(strict_types=1);

namespace App\Mail;

use Beffroi\Core\Domain\Key\MailSigningKey;
use SensitiveParameter;
use Symfony\Component\Mime\Exception\ExceptionInterface;
use Symfony\Component\Mime\Message;

interface MailSignerInterface
{
    public function kind(): string;

    /**
     * @param array<string, string> $privateJwk the private half of that key, opened by the KMS
     *
     * @throws ExceptionInterface when the mail cannot be signed with it
     */
    public function sign(Message $mail, MailSigningKey $key, #[SensitiveParameter] array $privateJwk): Message;
}
