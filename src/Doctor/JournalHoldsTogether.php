<?php

declare(strict_types=1);

namespace App\Doctor;

use Beffroi\Core\Application\Journal\JournalVerification;
use Beffroi\Core\Domain\Journal\VerifiedJournal;
use Beffroi\Core\Exception\BrokenJournalException;
use Beffroi\Core\Exception\ExceptionTrail;
use Beffroi\Core\Port\AmbientTenantInterface;
use const DATE_ATOM;
use function sprintf;
use Throwable;

/**
 * Decision 0058: every act the journal keeps, each computed again from the one before it, against the last signature.
 * Red at the first act that does not follow, named by its position and never by what it holds.
 */
final readonly class JournalHoldsTogether implements CheckInterface
{
    public const string ABOUT = 'Journal of acts: each act chained to the one before it, and the chain signed';

    public const string WHAT_IT_MEANS = 'What it means: an act was changed, added or removed in the database after the provider wrote it, or the signature kept beside the keys of the tenant is not the last one the provider wrote. Until it is resolved, the journal is neither signed nor forgotten past its retention.';

    public const string WHAT_TO_DO = 'What makes it green: the database and the key storage restored from the same backup. When only the signature is at fault, removing journal/checkpoint.jws from the key storage of the tenant has the next pass sign the chain as the database holds it.';

    public function __construct(
        private AmbientTenantInterface $tenants,
        private JournalVerification $verification,
    ) {
    }

    public function finding(): Finding
    {
        try {
            $journal = $this->verification->verify($this->tenants->provide());
        } catch (BrokenJournalException $broken) {
            return Finding::red(self::ABOUT, [$broken->getMessage(), self::WHAT_IT_MEANS, self::WHAT_TO_DO]);
        } catch (Throwable $exception) {
            return Finding::red(self::ABOUT, [sprintf('The journal of the tenant cannot be read: %s', ExceptionTrail::of($exception))]);
        }

        return Finding::green(self::ABOUT, self::says($journal));
    }

    private static function says(VerifiedJournal $journal): string
    {
        $kept = $journal->end->position - $journal->start->position;
        if ($kept === 0 && $journal->signed === null) {
            return 'The journal holds no act yet.';
        }
        if ($journal->signed === null) {
            return sprintf(
                'The %d act(s) the journal keeps, positions %d to %d, follow one another; none is signed yet, which the next pass does.',
                $kept,
                $journal->start->position + 1,
                $journal->end->position,
            );
        }

        return sprintf(
            'The %d act(s) the journal keeps, positions %d to %d, follow one another, and the signature of %s holds up to position %d; %d act(s) written since are signed at the next pass.',
            $kept,
            $journal->start->position + 1,
            $journal->end->position,
            $journal->signed->signedAt->format(DATE_ATOM),
            $journal->signed->head->position,
            $journal->unsigned(),
        );
    }
}
