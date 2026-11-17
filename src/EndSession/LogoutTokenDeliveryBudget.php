<?php

declare(strict_types=1);

namespace App\EndSession;

use function ceil;
use DateInterval;
use App\Http\LogoutTokenSender;
use App\Messenger\NotificationRetryPolicy;
use Beffroi\Oidc\Port\LogoutTokenDeliveryInterface;

final readonly class LogoutTokenDeliveryBudget implements LogoutTokenDeliveryInterface
{
    public function __construct(
        private NotificationRetryPolicy $retries,
        private LogoutTokenSender $sender,
    ) {
    }

    public function deliveryBudget(): DateInterval
    {
        $seconds = $this->retries->waiting() + (int) ceil($this->retries->attempts() * $this->sender->timeout->seconds);

        return new DateInterval('PT' . $seconds . 'S');
    }
}
