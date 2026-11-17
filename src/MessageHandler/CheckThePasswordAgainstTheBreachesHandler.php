<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CheckThePasswordAgainstTheBreaches;
use Beffroi\Core\Application\Credential\BreachedPasswordLookup;
use Beffroi\Core\Domain\Credential\PasswordCredential;
use Beffroi\Core\Domain\Credential\PasswordDigest;
use Beffroi\Core\Port\CredentialRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * What the list could not answer is not caught: the transport retries it. The digest is not compared with the hash,
 * changePassword() dropping the mark of a password replaced in between.
 */
#[AsMessageHandler]
final readonly class CheckThePasswordAgainstTheBreachesHandler
{
    public function __construct(
        private CredentialRepositoryInterface $credentials,
        private BreachedPasswordLookup $lookup,
    ) {
    }

    public function __invoke(CheckThePasswordAgainstTheBreaches $command): void
    {
        $credential = $this->credentials->find($command->credential);
        if (! $credential instanceof PasswordCredential) {
            return;
        }
        $this->lookup->lookUp($credential, new PasswordDigest($command->digest));
    }
}
