<?php

declare(strict_types=1);

namespace App\Doctor;

/**
 * A point of "beffroi:doctor": it writes nothing, and what it cannot read is a red finding, not an exception.
 */
interface CheckInterface
{
    public function finding(): Finding;
}
