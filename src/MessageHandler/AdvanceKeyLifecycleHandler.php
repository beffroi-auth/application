<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Doctrine\TenantNotSynchronizedException;
use App\Doctrine\TenantProvider;
use App\Http\RequestContextFactory;
use App\Message\AdvanceKeyLifecycle;
use App\Scheduler\BeffroiSchedule;
use Beffroi\Core\Application\Key\KeyRetirement;
use Beffroi\Core\Application\Key\KeySuccession;
use Beffroi\Core\Application\Key\KeyTakeOver;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The steps run in this order so that one tick finishes the rotation the previous one started.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class AdvanceKeyLifecycleHandler
{
    public function __construct(
        private TenantProvider $tenants,
        private KeySuccession $succession,
        private KeyTakeOver $takeOver,
        private KeyRetirement $retirement,
        private RequestContextFactory $contexts,
    ) {
    }

    public function __invoke(AdvanceKeyLifecycle $message): void
    {
        try {
            $tenant = $this->tenants->provide();
        } catch (TenantNotSynchronizedException) {
            // Nothing to do before the first provisioning, and a tick is not the place to say so.
            return;
        }
        $context = $this->contexts->fromSchedule(BeffroiSchedule::NAME);
        $this->succession->stageSuccessorsDue($tenant, $context);
        $this->takeOver->activateDue($tenant, $context);
        $this->retirement->retireDue($tenant, $context);
    }
}
