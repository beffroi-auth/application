<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use Beffroi\Core\Domain\Key\KeyStatus;
use Beffroi\Core\Port\RotationSchedulesInterface;
use Beffroi\Core\Port\TenantKeyRepositoryInterface;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The signing keys of the tenant in their four states, staged, active, retiring and retired, with their dates, and,
 * for an active key, whether it has worked past the age of a key (architecture §3.3): what `beffroi:keys:list --all`
 * reads (decision 0038). Nothing is opened with the KMS: whether a key opens is the business of the health of the
 * instance and of `beffroi:keys:list --check`.
 */
#[AsController]
final readonly class KeysController
{
    public function __construct(
        private TenantKeyRepositoryInterface $keys,
        private RotationSchedulesInterface $schedules,
        private ClockInterface $clock,
    ) {
    }

    #[Route('/keys', name: 'beffroi_administration_keys', methods: ['GET'])]
    public function __invoke(ActingAdministrator $administrator): Response
    {
        $now = $this->clock->now();
        $keys = [];
        foreach ($this->keys->findAll($administrator->tenant) as $key) {
            $keys[] = [
                'kid' => $key->kid,
                'algorithm' => $key->algorithm->name(),
                'use' => $key->publicJwk['use'] ?? null,
                'status' => $key->status->value,
                'created_at' => AdministrationAnswer::moment($key->createdAt),
                'activation_due_at' => AdministrationAnswer::moment($key->activationDueAt),
                'activated_at' => AdministrationAnswer::moment($key->activatedAt),
                'retiring_at' => AdministrationAnswer::moment($key->retiringAt),
                'retirement_due_at' => AdministrationAnswer::moment($key->retirementDueAt),
                'retired_at' => AdministrationAnswer::moment($key->retiredAt),
                'past_its_age' => $key->status === KeyStatus::Active
                    ? $key->hasReachedItsAge($now, $this->schedules->of($key->algorithm)->age)
                    : null,
            ];
        }

        return AdministrationAnswer::of(['keys' => $keys]);
    }
}
