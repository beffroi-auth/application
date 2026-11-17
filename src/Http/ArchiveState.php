<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Archive\PersonalArchive;
use DateTimeImmutable;

/**
 * Expiry is read first, then the document, then the delay a build is given: a row past its date is nothing.
 */
final readonly class ArchiveState
{
    public const string NEVER = 'never';

    public const string BEING_BUILT = 'being_built';

    public const string STALLED = 'stalled';

    public const string READY = 'ready';

    public const string EXPIRED = 'expired';

    public static function of(?PersonalArchive $archive, DateTimeImmutable $now): string
    {
        if ($archive === null) {
            return self::NEVER;
        }
        if ($archive->hasExpired($now)) {
            return self::EXPIRED;
        }
        if ($archive->isReady()) {
            return self::READY;
        }

        return $archive->hasStalled($now) ? self::STALLED : self::BEING_BUILT;
    }
}
