<?php

declare(strict_types=1);

namespace App\Messenger;

use Beffroi\Core\Bridge\JwtLibrary\SealedMessageBody;
use Beffroi\Core\Domain\Key\MessageSealingAlgorithmInterface;
use Beffroi\Core\Domain\Key\TenantKey;
use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Core\Port\RetirementHoldInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use function is_string;
use function sprintf;

/**
 * Decision 0020
 *
 * A message nobody replays or removes waits in the failure transport for as long as an operator leaves it, which no
 * retirement window can count: the message key it is sealed to waits with it.
 */
final readonly class FailedMessagesSealedTo implements RetirementHoldInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function holds(TenantKey $key): bool
    {
        if (! $key->algorithm instanceof MessageSealingAlgorithmInterface) {
            return false;
        }
        try {
            // A migration creates the table: a database no migration built, as a suite's, holds none.
            if (! $this->connection->createSchemaManager()->tablesExist([MessengerContract::FAILURE_TABLE])) {
                return false;
            }
            $bodies = $this->connection->iterateColumn(
                sprintf('SELECT body FROM %s WHERE queue_name = ?', MessengerContract::FAILURE_TABLE),
                [MessengerContract::FAILURE_TRANSPORT],
            );
            foreach ($bodies as $body) {
                if (is_string($body) && SealedMessageBody::keyOf($key->tenant, $body) === $key->kid) {
                    return true;
                }
            }
        } catch (DbalException $exception) {
            throw new RuntimeException('The failure transport cannot be read: whether a message waiting there is sealed to the key is unknown.', 0, $exception);
        }

        return false;
    }

    public function name(): string
    {
        return 'a message waiting in the failure transport, which "messenger:failed:retry" replays and "messenger:failed:remove" removes';
    }
}
