<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ClaimForms;
use App\Http\AccountAccess;
use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\InteractionPages;
use App\Http\WhatWasRefused;
use App\Http\Account\RecordedInformation;
use App\Identity\WiredClaims;
use function count;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsController]
final readonly class AccountProfileController implements ReachedOnTheSessionAloneInterface
{
    /**
     * The route name of the provider's page, which this page replaces so that every link naming it lands here.
     */
    public const string ROUTE = 'beffroi_interaction_account_profile';

    public function __construct(
        private AccountAccess $account,
        private WiredClaims $claims,
        private ClaimForms $claimForms,
        private WhatWasRefused $refusals,
        private TranslatorInterface $translator,
        private InteractionPages $pages,
    ) {
    }

    #[Route('/account/profile', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request);
        if ($session instanceof Response) {
            return $session;
        }
        $user = $session->user;
        $refusal = $this->refusals->take($request);
        $forms = [];
        foreach ($this->claims->openedTo($user) as $open) {
            $form = $this->claimForms->of($open, $user);
            if ($refusal?->field === $open->name()) {
                $form->get('value')->addError(new FormError($this->translator->trans('profile.' . $refusal->code, [], 'beffroi')));
            }
            $forms[$open->name()] = $form->createView();
        }

        return $this->pages->render('account/profile.html.twig', [
            'user' => $user,
            'identity' => RecordedInformation::identityOf($user),
            'claims' => RecordedInformation::everyClaimOf($user),
            'held' => count(RecordedInformation::claimsOf($user)),
            'forms' => $forms,
        ]);
    }
}
