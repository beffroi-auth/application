<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\InitialAccessTokenDocument;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Beffroi\Oidc\Port\IssuedInitialAccessTokensInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The initial access tokens of the tenant, the most recently issued first, what `beffroi:client:token:list` reads
 * (decision 0038): what each has served, what it gives the clients it registers, and whether it still authorises a
 * registration.
 */
#[AsController]
final readonly class InitialAccessTokensController
{
    public function __construct(
        private IssuedInitialAccessTokensInterface $tokens,
        private ClockInterface $clock,
    ) {
    }

    #[Route('/initial-access-tokens', name: 'beffroi_administration_initial_access_tokens', methods: ['GET'])]
    public function __invoke(ActingAdministrator $administrator): Response
    {
        $now = $this->clock->now();
        $tokens = [];
        foreach ($this->tokens->issuedIn($administrator->tenant) as $token) {
            $tokens[] = InitialAccessTokenDocument::of($token, $now);
        }

        return AdministrationAnswer::of(['initial_access_tokens' => $tokens]);
    }
}
