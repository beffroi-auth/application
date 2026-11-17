<?php

declare(strict_types=1);

namespace App\Doctrine\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use function implode;
use function is_numeric;
use function sprintf;

/**
 * Not beside the migrations: apps/beffroi/migrations/ is in the autoload of no package, only in the autoload-dev of the
 * monorepo, so a class placed there would not resolve in a deployment.
 */
abstract class SealingMigration extends AbstractMigration
{
    public function preUp(Schema $schema): void
    {
        $this->refuseRowsIn('seals');
        $this->sayToRestartWhatReadsTheColumns();
    }

    /**
     * Rows are refused going back too: nothing here unseals a value.
     */
    public function preDown(Schema $schema): void
    {
        $this->refuseRowsIn('unseals');
        $this->sayToRestartWhatReadsTheColumns();
    }

    /**
     * @return list<string>
     */
    abstract protected function tablesToSeal(): array;

    private function sayToRestartWhatReadsTheColumns(): void
    {
        $this->write(sprintf(
            'A process that booted before this migration reads the columns of %s with the type they had, so restart what serves requests and what consumes the bus once it has run. A column typed "string" throws, a blob coming back as a stream; a column typed "text" does not, and returns the ciphertext as a string for its caller to act on, which nothing reports.',
            implode(', ', $this->tablesToSeal()),
        ));
    }

    private function refuseRowsIn(string $what): void
    {
        foreach ($this->tablesToSeal() as $table) {
            // A count that is not a number is left to the server rather than guessed at.
            $count = $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));
            $rows = is_numeric($count) ? (int) $count : 0;

            $this->abortIf($rows > 0, sprintf(
                '%s holds %d row(s) and this migration %s its columns, which recreates them: a sealed column is binary and accepts no null, so there is nothing to put in the rows that are there. There is no migration path for cleartext rows and no deployment that would need one (decision 0011). Seal them with a script of your own, or empty the table ("TRUNCATE TABLE %s CASCADE" on a development stack, which "castor provision" then fills again).',
                $table,
                $rows,
                $what,
                $table,
            ));
        }
    }
}
