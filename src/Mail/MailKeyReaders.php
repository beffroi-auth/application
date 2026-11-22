<?php

declare(strict_types=1);

namespace App\Mail;

final readonly class MailKeyReaders
{
    /**
     * @param iterable<MailKeyReaderInterface> $readers in the order a person is offered them
     */
    public function __construct(
        private iterable $readers,
    ) {
    }

    /**
     * @return list<string>
     */
    public function kinds(): array
    {
        $kinds = [];
        foreach ($this->readers as $reader) {
            $kinds[] = $reader->kind();
        }

        return $kinds;
    }

    public function of(string $kind): ?MailKeyReaderInterface
    {
        foreach ($this->readers as $reader) {
            if ($reader->kind() === $kind) {
                return $reader;
            }
        }

        return null;
    }
}
