<?php

declare(strict_types=1);

namespace App\Twig;

use App\Doctrine\TenantNotSynchronizedException;
use App\Doctrine\TenantProvider;
use App\Http\ImageSource;
use App\Http\TenantPresentation;
use Beffroi\Core\Domain\Tenant\Tenant;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * Null when the tenant is not synchronized: the page that says so must still render.
 */
final readonly class TenantExtension
{
    public function __construct(
        private TenantProvider $tenants,
        private ImageSource $images,
        private RequestStack $requests,
    ) {
    }

    #[AsTwigFunction(name: 'beffroi_tenant')]
    public function tenant(): ?TenantPresentation
    {
        try {
            $tenant = $this->tenants->provide();
        } catch (TenantNotSynchronizedException) {
            return null;
        }
        $this->mayShowTheLogoOf($tenant);

        return TenantPresentation::of($tenant);
    }

    /**
     * The main request, whose answer carries the policy: an error page is rendered in a sub-request.
     */
    private function mayShowTheLogoOf(Tenant $tenant): void
    {
        $logo = $tenant->look->logoUri;
        $request = $this->requests->getMainRequest();
        if ($logo === null || $request === null) {
            return;
        }
        $this->images->mayLoadFrom($request, [$logo->origin]);
    }
}
