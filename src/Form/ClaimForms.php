<?php

declare(strict_types=1);

namespace App\Form;

use App\Controller\AccountProfileDescriptionController;
use Beffroi\Core\Domain\Identity\Claim\ClaimInterface;
use Beffroi\Core\Domain\Identity\User;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The page and the request that saves a claim both build its form here, so what was rendered is read back.
 */
final readonly class ClaimForms
{
    public function __construct(
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $router,
    ) {
    }

    /**
     * @return FormInterface<mixed>
     */
    public function of(ClaimInterface $claim, User $user): FormInterface
    {
        $held = $claim->of($user);

        return $this->forms->create(
            ClaimType::class,
            [
                'value' => $held,
            ],
            [
                'claim' => $claim,
                'field' => $claim->field()->forHolding($held),
                'action' => $this->router->generate(AccountProfileDescriptionController::ROUTE, [
                    'claim' => $claim->name(),
                ]),
            ],
        );
    }
}
