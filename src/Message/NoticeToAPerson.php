<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Worth delivering for as long as the journal keeps the act it tells of: the lane retries it on the schedule of the
 * notices, and not on the one of a logout token, which stops being verifiable within minutes.
 */
interface NoticeToAPerson
{
}
