<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use Beffroi\Core\Application\Journal\JournalRetention;
use Beffroi\Core\Port\AdministratorGroupsInterface;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Beffroi\Oidc\Port\AdministrationResourceInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The tenant an administrator administers, as the provider holds it (decision 0038): its issuer and its name, the
 * floor of its logins and the classes of authentication its policy names, by the predicate each one stands on
 * (decisions 0010 and 0025), the groups its deployment names to administer it and the identifier of this API, and how
 * many days its journal keeps an act.
 */
#[AsController]
final readonly class TenantController
{
    public function __construct(
        private AdministratorGroupsInterface $administrators,
        private AdministrationResourceInterface $resources,
        private JournalRetention $retention,
    ) {
    }

    #[Route('/tenant', name: 'beffroi_administration_tenant', methods: ['GET'])]
    public function __invoke(ActingAdministrator $administrator): Response
    {
        $tenant = $administrator->tenant;
        $classes = [];
        foreach ($tenant->authenticationContextPolicy->names() as $name) {
            $class = $tenant->authenticationContextPolicy->named($name);
            if ($class !== null) {
                $classes[] = [
                    'acr' => $class->value,
                    'predicate' => $class->predicate->name(),
                ];
            }
        }

        return AdministrationAnswer::of([
            'slug' => $tenant->slug,
            'issuer' => (string) $tenant->issuer,
            'name' => $tenant->name,
            'created_at' => AdministrationAnswer::moment($tenant->createdAt),
            'login_floor' => $tenant->loginFloor->name(),
            'authentication_classes' => $classes,
            'administrators' => $this->administrators->groupsAdministering($tenant),
            'administration_api' => $this->resources->administrationResourceOf($tenant)?->value,
            'journal_retention_days' => $this->retention->window->days,
        ]);
    }
}
