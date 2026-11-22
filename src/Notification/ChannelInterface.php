<?php

declare(strict_types=1);

namespace App\Notification;

interface ChannelInterface
{
    /**
     * @return string the name of the channel it tells a person by, one name for every kind it tells of: the choice of
     *                the person holds it, and a notice waiting on the lane finds its channel by it, so it is kept from
     *                one release to the next
     */
    public function name(): string;
}
