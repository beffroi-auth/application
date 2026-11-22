<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\AskedDocument;
use App\Http\RequestContextFactory;
use Beffroi\Core\Domain\Tenant\TenantContact;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Oidc\Application\Administration\AdministeredTenantContact;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sets the contact the tenant gives its people in what tells them of a change to their account (NIST SP 800-63B-4
 * §4.6, decision 0038), from a JSON object naming it, "contact": the act names the administrator, and the answer writes
 * the contact the tenant now gives.
 */
#[AsController]
final readonly class TenantContactController
{
    private const string ASKED = 'A contact is set from a JSON object naming it, "contact".';

    public function __construct(
        private AdministeredTenantContact $contacts,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route('/tenant/contact', name: 'beffroi_administration_tenant_contact', methods: ['PUT'])]
    public function __invoke(ActingAdministrator $administrator, Request $request): Response
    {
        try {
            $contact = AskedDocument::of($request, 'contact asked for')?->nullableString('contact');
            if ($contact === null) {
                return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', self::ASKED);
            }
            $adopted = $this->contacts->adopt($administrator, new TenantContact($contact), $this->contexts->fromRequest($request));
        } catch (InvalidArgumentException $exception) {
            return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', $exception->getMessage());
        }

        return AdministrationAnswer::of(['contact' => $adopted?->value]);
    }
}
