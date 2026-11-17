<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use function is_a;
use App\Doctrine\Type\NeedsAuthenticationPredicatesInterface;
use App\Doctrine\Type\NeedsAuthorizationDetailTypesInterface;
use App\Doctrine\Type\NeedsCodeChallengeMethodsInterface;
use App\Doctrine\Type\NeedsConfirmationReaderInterface;
use App\Doctrine\Type\NeedsPasskeyAttestationsInterface;
use App\Doctrine\Type\NeedsPasswordAlgorithmsInterface;
use Beffroi\Core\Application\Passkey\PasskeyAttestations;
use Beffroi\Core\Port\AuthenticationPredicatesInterface;
use Beffroi\Core\Port\PasswordAlgorithmsInterface;
use Beffroi\Oidc\Port\AuthorizationDetailsReaderInterface;
use Beffroi\Oidc\Port\CodeChallengeMethodsInterface;
use Beffroi\Oidc\Port\ConfirmationReaderInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedTypes;

trait BeffroiKernelTrait
{
    protected function build(ContainerBuilder $container): void
    {
        $container->registerExtension(new BeffroiExtension());
        BeffroiExtension::addCompilerPasses($container);
    }

    /**
     * The types are wired through their class: Doctrine instantiates them itself, when a connection is first made.
     */
    public function boot(): void
    {
        $booted = $this->booted;
        parent::boot();
        if ($booted || $this->container === null) {
            return;
        }
        $container = $this->container;
        $algorithms = $container->has('beffroi.password_algorithms') ? $container->get('beffroi.password_algorithms') : null;
        $confirmations = $container->has('beffroi.confirmations') ? $container->get('beffroi.confirmations') : null;
        $authorizationDetails = $container->has('beffroi.authorization_detail_types') ? $container->get('beffroi.authorization_detail_types') : null;
        $codeChallengeMethods = $container->has('beffroi.code_challenge_methods') ? $container->get('beffroi.code_challenge_methods') : null;
        $predicates = $container->has('beffroi.authentication_predicates') ? $container->get('beffroi.authentication_predicates') : null;
        $passkeyAttestations = $container->has('beffroi.passkey_attestations') ? $container->get('beffroi.passkey_attestations') : null;
        foreach (BeffroiExtension::DBAL_TYPES as $class) {
            if ($algorithms instanceof PasswordAlgorithmsInterface && is_a($class, NeedsPasswordAlgorithmsInterface::class, true)) {
                $class::resolveWith($algorithms);
            }
            if ($confirmations instanceof ConfirmationReaderInterface && is_a($class, NeedsConfirmationReaderInterface::class, true)) {
                $class::resolveWith($confirmations);
            }
            if ($authorizationDetails instanceof AuthorizationDetailsReaderInterface && is_a($class, NeedsAuthorizationDetailTypesInterface::class, true)) {
                $class::resolveWith($authorizationDetails);
            }
            if ($codeChallengeMethods instanceof CodeChallengeMethodsInterface && is_a($class, NeedsCodeChallengeMethodsInterface::class, true)) {
                $class::resolveWith($codeChallengeMethods);
            }
            if ($predicates instanceof AuthenticationPredicatesInterface && is_a($class, NeedsAuthenticationPredicatesInterface::class, true)) {
                $class::resolveWith($predicates);
            }
            if ($passkeyAttestations instanceof PasskeyAttestations && is_a($class, NeedsPasskeyAttestationsInterface::class, true)) {
                $class::resolveWith($passkeyAttestations);
            }
        }
        $this->registerEncryptedTypes();
    }

    /**
     * A connection is asked for first: DoctrineBundle puts the types the encrypted ones decorate in the registry only
     * when a connection is created (ConnectionFactory::initializeTypes), and asking for one opens nothing.
     */
    private function registerEncryptedTypes(): void
    {
        $container = $this->container;
        if ($container === null || ! $container->has('beffroi.encrypted_types')) {
            return;
        }
        $doctrine = $container->has('doctrine') ? $container->get('doctrine') : null;
        if ($doctrine instanceof ManagerRegistry) {
            $doctrine->getConnection();
        }
        $types = $container->get('beffroi.encrypted_types');
        if ($types instanceof EncryptedTypes) {
            $types->register();
        }
    }
}
