<?php

declare(strict_types=1);

namespace App\Doctor;

use Beffroi\Core\Application\Key\HeldSigningAlgorithms;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\ExceptionTrail;
use Beffroi\Core\Port\AmbientTenantInterface;
use Beffroi\Core\Port\SignatureAlgorithmsInterface;
use Beffroi\Oidc\Domain\Client\SignedDocument;
use Beffroi\Oidc\Port\ClientSourceInterface;
use function sprintf;
use Throwable;

/**
 * Whether the tenant holds an active key for every algorithm it signs a document of one of its clients with. A client
 * the configuration declares is read by no registration, so nothing refuses one that names an algorithm the tenant
 * holds no key for, and the default algorithm is the deployment's to hold a key for: either shows here, or at the
 * first sign-in of that client as a server error.
 */
final readonly class ClientsSignedWithHeldKeys implements CheckInterface
{
    public const string ABOUT = 'Clients: the keys their documents are signed with';

    public const string WHAT_TO_DO = 'What makes it green: "bin/console beffroi:keys:generate --algorithm=<algorithm>" draws the missing key, or the client names an algorithm the tenant holds a key for.';

    /**
     * @param iterable<ClientSourceInterface> $sources
     */
    public function __construct(
        private AmbientTenantInterface $tenants,
        private iterable $sources,
        private HeldSigningAlgorithms $held,
        private SignatureAlgorithmsInterface $algorithms,
    ) {
    }

    public function finding(): Finding
    {
        try {
            $tenant = $this->tenants->provide();
            $found = $this->documentsSignedWithoutAKey($tenant);
        } catch (Throwable $exception) {
            return Finding::red(self::ABOUT, [
                sprintf('The clients of the tenant and its keys cannot be read: %s', ExceptionTrail::of($exception)),
            ]);
        }

        if ($found !== []) {
            return Finding::red(self::ABOUT, [...$found, self::WHAT_TO_DO]);
        }

        return Finding::green(self::ABOUT, sprintf(
            'The tenant "%s" holds an active key for every algorithm it signs the documents of its clients with.',
            $tenant->slug,
        ));
    }

    /**
     * @return list<string>
     */
    private function documentsSignedWithoutAKey(Tenant $tenant): array
    {
        $found = [];
        foreach ($this->sources as $source) {
            foreach ($source->registrationsIn($tenant) as $registration) {
                foreach (SignedDocument::of($registration) as $document) {
                    if (! $document->format->isSigned) {
                        continue;
                    }
                    $algorithm = $document->format->signature ?? $this->algorithms->defaultAlgorithm();
                    if ($this->held->holds($tenant, $algorithm)) {
                        continue;
                    }
                    $found[] = sprintf(
                        'The client "%s" receives its %s signed with %s, which the tenant "%s" holds no active key for: the first one it asks for fails with a server error.',
                        $registration->clientId,
                        $document->documents,
                        $algorithm->name(),
                        $tenant->slug,
                    );
                }
            }
        }

        return $found;
    }
}
