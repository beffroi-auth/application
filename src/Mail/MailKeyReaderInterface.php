<?php

declare(strict_types=1);

namespace App\Mail;

use Beffroi\Core\Domain\Identity\MailEncryptionKey;
use Beffroi\Core\Exception\MailEncryptionRefusedException;

/**
 * Reads what a person gives for the mail of the provider to be encrypted to them, in one format.
 */
interface MailKeyReaderInterface
{
    /**
     * A lowercase word that names the format where it is stored and in what the person reads.
     */
    public function kind(): string;

    /**
     * @throws MailEncryptionRefusedException when it is not one certificate or one key a mail may be encrypted to
     */
    public function read(string $material): MailEncryptionKey;
}
