<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ClaimType;
use function array_map;
use function is_string;
use Beffroi\Bundle\Http\AccountAccess;
use Beffroi\Bundle\Http\InteractionPages;
use Beffroi\Bundle\Http\RequestContextFactory;
use Beffroi\Bundle\Http\Account\RecordedInformation;
use Beffroi\Bundle\Identity\WiredClaims;
use Beffroi\Core\Application\Identity\SelfDescription;
use Beffroi\Core\Domain\Identity\Claim\ClaimInterface;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Domain\Identity\User;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What this provider holds of the signed-in person, and what they may correct of it, edited in place
 * (issue beffroi-php/op#17).
 *
 * **This page is the application's and not the bundle's**, which is why it takes the two route names of
 * the bundle rather than adding two of its own: a deployment that installs the bundle is served bare HTML
 * with a form token, and this one, which is the reference deployment, shows what the same thing looks like
 * written with symfony/form. The rule of what a person may change is not here either way: it is read by
 * the use case of the core (SelfDescription), on the server, for every caller.
 *
 * One form per claim, because what this page does is let somebody correct one thing where they read it,
 * and a single form would make them save thirteen others to do it. Which form was submitted is the claim
 * named in the URL, so nothing is read back out of a body to know which.
 *
 * It renders no script. The value a person reads is the field they type into, which is a stylesheet and
 * not a behaviour (assets/styles/app.css).
 */
#[AsController]
final readonly class AccountProfileController
{
    public function __construct(
        private AccountAccess $account,
        private WiredClaims $claims,
        private SelfDescription $description,
        private RequestContextFactory $contexts,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $router,
        private TranslatorInterface $translator,
        private InteractionPages $pages,
    ) {
    }

    #[Route('/account/profile', name: 'beffroi_interaction_account_profile', methods: ['GET'])]
    #[Route('/account/profile/{claim}', name: 'beffroi_interaction_account_profile_describe', methods: ['POST'], requirements: [
        'claim' => '[a-z_]+',
    ])]
    public function __invoke(Request $request, ?string $claim = null): Response
    {
        $session = $this->account->granted($request);
        if ($session instanceof Response) {
            return $session;
        }
        $user = $session->user;
        $forms = [];
        $status = Response::HTTP_OK;
        foreach ($this->claims->openedBy($user->tenant) as $open) {
            $forms[$open->name()] = $this->forms->create(ClaimType::class, [
                'value' => $open->of($user),
            ], [
                'claim' => $open,
                'action' => $this->router->generate('beffroi_interaction_account_profile_describe', ['claim' => $open->name()]),
            ]);
        }
        if ($claim !== null) {
            $submitted = $forms[$claim] ?? throw new NotFoundHttpException('No such claim.');
            $status = $this->describe($request, $submitted, $this->claims->named($claim) ?? throw new NotFoundHttpException('No such claim.'), $user);
        }

        return $this->pages->render('account/profile.html.twig', [
            'user' => $user,
            'identity' => RecordedInformation::identityOf($user),
            'claims' => RecordedInformation::claimsOf($user),
            // the views are built after the submission was handled, so what was refused is on the field
            'forms' => array_map(static fn (FormInterface $form): FormView => $form->createView(), $forms),
        ], $status);
    }

    /**
     * What the use case refuses is said on the field that was refused, which is what a form is for: the
     * page of the bundle has one sentence for the whole page, and this one has a message under the line.
     */
    private function describe(Request $request, FormInterface $form, ClaimInterface $claim, User $user): int
    {
        $form->handleRequest($request);
        if (! $form->isSubmitted() || ! $form->isValid()) {
            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }
        $said = $form->get('value')->getData();
        try {
            $this->description->describe($user, $claim, is_string($said) && $said !== '' ? $said : null, $this->contexts->fromRequest($request));
        } catch (InvalidArgumentException) {
            // which of the two it is, the tenant answers; what the message says is a word of the catalogue
            // of the bundle and never the sentence of an exception, which is written for whoever reads logs
            $refusal = $user->tenant->selfServedClaims->opens($claim->name()) ? 'profile.claim_refused' : 'profile.claim_not_open';
            $form->get('value')->addError(new FormError($this->translator->trans($refusal, [], 'beffroi')));

            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return Response::HTTP_OK;
    }
}
