<?php

declare(strict_types=1);

namespace App\Administration;

use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * The administrator a request of the administration API comes from, as an argument of its controller: the one the
 * guard found it to be (`AdministrationGuard`). A controller of the API reached without it is a route the guard did
 * not hold, which is a defect of this provider and never a request to serve.
 */
final readonly class ActingAdministratorResolver implements ValueResolverInterface
{
    /**
     * @return iterable<ActingAdministrator>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== ActingAdministrator::class) {
            return [];
        }
        $administrator = $request->attributes->get(AdministrationGuard::ATTRIBUTE);
        if (! $administrator instanceof ActingAdministrator) {
            throw new LogicException('A controller of the administration API was reached by a request its guard did not let in.');
        }

        return [$administrator];
    }
}
