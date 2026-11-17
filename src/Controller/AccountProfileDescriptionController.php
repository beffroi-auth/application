<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ClaimForms;
use App\Http\AccountAccess;
use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\InteractionPages;
use App\Http\ProfilePage;
use App\Http\RequestContextFactory;
use App\Http\WhatWasRefused;
use App\Identity\WiredClaims;
use Beffroi\Core\Application\Identity\SelfDescription;
use Beffroi\Core\Domain\Identity\Claim\ClaimInterface;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Exception\InvalidArgumentException;
use function is_string;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;

#[AsController]
final readonly class AccountProfileDescriptionController implements ReachedOnTheSessionAloneInterface
{
    /**
     * The route name of the provider's form, which this replaces so that every link naming it lands here.
     */
    public const string ROUTE = 'beffroi_interaction_account_profile_describe';

    public function __construct(
        private AccountAccess $account,
        private WiredClaims $claims,
        private ClaimForms $claimForms,
        private SelfDescription $description,
        private RequestContextFactory $contexts,
        private WhatWasRefused $refusals,
        private InteractionPages $pages,
    ) {
    }

    #[Route(
        '/account/profile/{claim}',
        name: self::ROUTE,
        methods: ['POST'],
        requirements: [
            'claim' => '[a-z_]+',
        ],
    )]
    public function __invoke(Request $request, string $claim): Response
    {
        $session = $this->account->granted($request);
        if ($session instanceof Response) {
            return $session;
        }
        $user = $session->user;
        $named = $this->claims->named($claim);
        if ($named === null || ! $user->tenant->selfServedClaims->opens($named->name())) {
            throw new NotFoundHttpException('No such claim.');
        }
        $form = $this->claimForms->of($named, $user);
        $form->handleRequest($request);
        // A body that carries nothing of this form carries no token either.
        if (! $form->isSubmitted() || self::theTokenDidNotHold($form)) {
            return $this->pages->error(Response::HTTP_FORBIDDEN, InteractionPages::INVALID_CSRF_TOKEN);
        }
        $refusal = $this->describe($request, $form, $named, $user);
        if ($refusal !== null) {
            $this->refusals->keep($request, $refusal, $named->name());
        }

        return $this->pages->afterTheForm(AccountProfileController::ROUTE);
    }

    /**
     * The code of what was refused, null once written; never the refused value, which would land in the session.
     *
     * @param FormInterface<mixed> $form
     */
    private function describe(Request $request, FormInterface $form, ClaimInterface $claim, User $user): ?string
    {
        if (! $form->isValid()) {
            return ProfilePage::REFUSED;
        }
        $said = $form->get('value')->getData();
        try {
            $this->description->describe($user, $claim, is_string($said) && $said !== '' ? $said : null, $this->contexts->fromRequest($request));
        } catch (InvalidArgumentException) {
            return ProfilePage::REFUSED;
        }

        return null;
    }

    /**
     * The form component puts its CSRF error on the form, the refused token as its cause: that is what this reads.
     *
     * @param FormInterface<mixed> $form
     */
    private static function theTokenDidNotHold(FormInterface $form): bool
    {
        foreach ($form->getErrors() as $error) {
            if ($error->getCause() instanceof CsrfToken) {
                return true;
            }
        }

        return false;
    }
}
