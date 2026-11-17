<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use App\Http\PasskeyOptionsStore;
use Beffroi\Core\Application\Passkey\PasskeyEnrolment;
use Beffroi\Core\Bridge\Webauthn\WebauthnCeremonies;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * WebAuthn Level 3 §5.4
 *
 * Reached on the session alone: the proof is asked where the attestation is posted (PasskeyEnrolmentController).
 */
#[AsController]
final readonly class PasskeyEnrolmentOptionsController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_passkeys_options';

    public function __construct(
        private AccountAccess $account,
        private PasskeyEnrolment $enrolment,
        private WebauthnCeremonies $ceremonies,
        private PasskeyOptionsStore $options,
    ) {
    }

    #[Route('/account/passkeys/options', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->signedIn($request);
        if ($session === null) {
            return new JsonResponse(
                [
                    'error' => InteractionPages::LOGIN_REQUIRED,
                ],
                Response::HTTP_UNAUTHORIZED,
                InteractionPages::HEADERS,
            );
        }
        $options = $this->enrolment->begin($session->user);
        $this->options->storeCreation($request->getSession(), $session->user->id, $options);

        return new Response($this->ceremonies->optionsToJson($options), Response::HTTP_OK, [
            'Content-Type' => 'application/json',
            ...InteractionPages::HEADERS,
        ]);
    }
}
