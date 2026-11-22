<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Http\RequestContextFactory;
use Beffroi\Oidc\Application\Administration\AdministeredTenantContact;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Takes back the contact the tenant gives its people (decision 0038): what tells them of a change to their account then
 * sends them to their account alone, and the act names the administrator. A tenant that gives none is answered as it is.
 */
#[AsController]
final readonly class TenantContactRemovalController
{
    public function __construct(
        private AdministeredTenantContact $contacts,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route('/tenant/contact', name: 'beffroi_administration_tenant_contact_removal', methods: ['DELETE'])]
    public function __invoke(ActingAdministrator $administrator, Request $request): Response
    {
        return AdministrationAnswer::of(['contact' => $this->contacts->adopt($administrator, null, $this->contexts->fromRequest($request))?->value]);
    }
}
