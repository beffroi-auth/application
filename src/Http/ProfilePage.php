<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Account\RecordedInformation;
use function count;
use App\Identity\WiredClaims;
use Beffroi\Core\Domain\Identity\User;
use Symfony\Component\HttpFoundation\Response;

final readonly class ProfilePage
{
    public const string NOT_OPEN = 'claim_not_open';

    public const string REFUSED = 'claim_refused';

    public function __construct(
        private WiredClaims $claims,
        private InteractionPages $pages,
    ) {
    }

    public function render(User $user, ?string $refusal = null, int $status = Response::HTTP_OK): Response
    {
        $changeable = [];
        foreach ($this->claims->openedTo($user) as $claim) {
            $held = $claim->of($user);
            $changeable[$claim->name()] = [
                'held' => $held,
                // The claim's field, unless a browser would render the value held empty in it and a save erase it.
                'field' => $claim->field()->forHolding($held),
            ];
        }

        return $this->pages->render(
            'account/profile.html.twig',
            [
                'user' => $user,
                'identity' => RecordedInformation::identityOf($user),
                'claims' => RecordedInformation::everyClaimOf($user),
                'held' => count(RecordedInformation::claimsOf($user)),
                'changeable' => $changeable,
                'refusal' => $refusal,
            ],
            $status,
        );
    }
}
