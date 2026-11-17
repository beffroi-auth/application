<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Application\Identity\FederatedDomains;
use App\Http\Account\BreachedPasswordDemand;
use Beffroi\Core\Application\Credential\ActivePassword;
use Beffroi\Core\Domain\Credential\PasswordLength;
use Beffroi\Core\Domain\Credential\PlainPassword;
use Beffroi\Core\Domain\Identity\User;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final readonly class PasswordPage
{
    /**
     * @var list<string> lowest rung first
     */
    public const array RUNG_KEYS = [
        'password.strength.nothing',
        'password.strength.weak',
        'password.strength.medium',
        'password.strength.strong',
        'password.strength.very_strong',
    ];

    public function __construct(
        private ActivePassword $activePassword,
        private BreachedPasswordDemand $demands,
        private InteractionPages $pages,
        private FederatedDomains $federated,
    ) {
    }

    public function render(SessionInterface $httpSession, User $user, ?string $refusal = null): Response
    {
        $password = $this->activePassword->of($user);

        return $this->pages->render('account/password.html.twig', [
            'user' => $user,
            'password' => $password,
            'demanded' => $this->demands->isMade($httpSession, $password),
            'refusal' => $refusal,
            'federated' => ! $this->federated->admitsLocalCredentials($user),
            'minimum' => PasswordLength::under($user->tenant->loginFloor)->minimum,
            'floor' => PlainPassword::MINIMUM_STRENGTH,
            'rungs' => self::RUNG_KEYS,
        ]);
    }
}
