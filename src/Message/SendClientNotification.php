<?php

declare(strict_types=1);

namespace App\Message;

use Beffroi\Core\Domain\Session\RequestContext;
use Beffroi\Core\Domain\Tenant\Tenant;
use SensitiveParameter;

/**
 * CIBA Core §10.2
 */
final readonly class SendClientNotification implements Notification
{
    public function __construct(
        public Tenant $tenant,
        public string $clientId,
        public string $notificationEndpoint,
        #[SensitiveParameter]
        public string $notificationToken,
        #[SensitiveParameter]
        public string $authRequestId,
        public RequestContext $context,
    ) {
    }
}
