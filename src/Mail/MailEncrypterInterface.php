<?php

declare(strict_types=1);

namespace App\Mail;

use Beffroi\Core\Domain\Identity\MailEncryptionKey;
use Symfony\Component\Mime\Exception\ExceptionInterface;
use Symfony\Component\Mime\Message;

interface MailEncrypterInterface
{
    public function kind(): string;

    /**
     * @throws ExceptionInterface when the mail cannot be encrypted to that key
     */
    public function encrypt(Message $mail, MailEncryptionKey $key): Message;
}
