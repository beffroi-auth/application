<?php

declare(strict_types=1);

namespace App\Http\Account;

interface AsksForARecentProofInterface
{
    /**
     * A route: this page when it is read, and the page that holds the form when the form is posted.
     */
    public function comesBackTo(): string;
}
