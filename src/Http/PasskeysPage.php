<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Application\Identity\FederatedDomains;
use Beffroi\Core\Domain\Credential\PasskeyCredential;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\CanPageCredentialsInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class PasskeysPage
{
    public function __construct(
        private CanPageCredentialsInterface $credentials,
        private AskedPage $asked,
        private AskedPeriod $period,
        private InteractionPages $pages,
        private FederatedDomains $federated,
    ) {
    }

    public function render(User $user, ?string $error = null, int $status = Response::HTTP_OK): Response
    {
        $period = $this->period->asked($user);

        return $this->pages->render(
            'account/passkeys.html.twig',
            [
                'user' => $user,
                'passkeys' => $this->credentials->pageOfCredentials($user, PasskeyCredential::class, $this->asked->asked(), $period),
                'period' => $period,
                'error' => $error,
                'federated' => ! $this->federated->admitsLocalCredentials($user),
            ],
            $status,
        );
    }
}
