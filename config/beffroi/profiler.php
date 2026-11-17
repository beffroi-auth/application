<?php

declare(strict_types=1);

use App\Http\AuthorizationRequestReaderInterface;
use App\Http\AuthorizationResponderInterface;
use App\Profiler\KmsClient;
use App\Profiler\OpCollector;
use App\Profiler\OpEndpointListener;
use App\Profiler\OpProfile;
use App\Profiler\OpResponseListener;
use App\Profiler\PanelValues;
use App\Profiler\ProfilingAccessTokenIssuer;
use App\Profiler\ProfilingAuthenticationSessionRepository;
use App\Profiler\ProfilingAuthorizationRequestReader;
use App\Profiler\Decision\ConsentRequiredObservation;
use App\Profiler\Decision\DecisionObservations;
use App\Profiler\Decision\GrantedObservation;
use App\Profiler\Decision\LoginRequiredObservation;
use App\Profiler\ProfilingAuthorizationResponder;
use App\Profiler\ProfilingClientRepository;
use App\Profiler\ProfilingCompactJwtReader;
use App\Profiler\ProfilingConsentRepository;
use App\Profiler\ProfilingDecryptionKeyProvider;
use App\Profiler\ProfilingEventDispatcher;
use App\Profiler\ProfilingIdTokenIssuer;
use App\Profiler\ProfilingInteractionRepository;
use App\Profiler\ProfilingSigningKeyProvider;
use App\Profiler\ProviderCapabilitiesListener;
use App\Messenger\EventBusDispatcher;
use Beffroi\Core\Port\AuthenticationSessionRepositoryInterface;
use Beffroi\Core\Port\DecryptionKeyProviderInterface;
use Beffroi\Core\Port\SigningKeyProviderInterface;
use Beffroi\Oidc\Port\AccessTokenIssuerInterface;
use Beffroi\Oidc\Port\ClientRepositoryInterface;
use Beffroi\Oidc\Port\CompactJwtReaderInterface;
use Beffroi\Oidc\Port\ConsentRepositoryInterface;
use Beffroi\Oidc\Port\IdTokenIssuerInterface;
use Beffroi\Oidc\Port\InteractionRepositoryInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()->autowire()->autoconfigure()->private();

    $services->set(OpProfile::class)->tag('kernel.reset', [
        'method' => 'reset',
    ]);
    $services->set(OpCollector::class)->tag('data_collector', [
        'id' => OpCollector::NAME,
        'template' => '@Beffroi/profiler/panel.html.twig',
        'priority' => 300,
    ]);
    $services->set(PanelValues::class);
    $services->set(OpEndpointListener::class);
    $services->set(OpResponseListener::class);
    $services->set(ProviderCapabilitiesListener::class);

    $services
        ->set(ProfilingAuthorizationRequestReader::class)
        ->decorate(AuthorizationRequestReaderInterface::class)
        ->args([service('.inner')]);
    $services->set(LoginRequiredObservation::class)->tag('beffroi.profiler.authorization_decision');
    $services->set(ConsentRequiredObservation::class)->tag('beffroi.profiler.authorization_decision');
    $services->set(GrantedObservation::class)->tag('beffroi.profiler.authorization_decision');
    $services->set(DecisionObservations::class)->args([tagged_iterator('beffroi.profiler.authorization_decision')]);
    $services
        ->set(ProfilingAuthorizationResponder::class)
        ->decorate(AuthorizationResponderInterface::class)
        ->args([service('.inner')]);
    $services
        ->set(ProfilingCompactJwtReader::class)
        ->decorate(CompactJwtReaderInterface::class)
        ->args([service('.inner')]);
    $services
        ->set(ProfilingInteractionRepository::class)
        ->decorate(InteractionRepositoryInterface::class)
        ->args([service('.inner')]);
    $services
        ->set(ProfilingClientRepository::class)
        ->decorate(ClientRepositoryInterface::class)
        ->args([service('.inner')]);
    $services
        ->set(ProfilingConsentRepository::class)
        ->decorate(ConsentRepositoryInterface::class)
        ->args([service('.inner')]);
    $services
        ->set(ProfilingAuthenticationSessionRepository::class)
        ->decorate(AuthenticationSessionRepositoryInterface::class)
        ->args([service('.inner')]);

    $services
        ->set(ProfilingAccessTokenIssuer::class)
        ->decorate(AccessTokenIssuerInterface::class)
        ->args([service('.inner')]);
    $services
        ->set(ProfilingIdTokenIssuer::class)
        ->decorate(IdTokenIssuerInterface::class)
        ->args([service('.inner')]);
    $services
        ->set(ProfilingSigningKeyProvider::class)
        ->decorate(SigningKeyProviderInterface::class)
        ->args([service('.inner')]);
    $services
        ->set(ProfilingDecryptionKeyProvider::class)
        ->decorate(DecryptionKeyProviderInterface::class)
        ->args([service('.inner')]);

    $services
        ->set(ProfilingEventDispatcher::class)
        ->decorate(EventBusDispatcher::class)
        ->args([service('.inner')]);

    // Only the scheme of the KMS DSN reaches the panel: the rest of a DSN may carry a credential.
    $services->set(KmsClient::class);
};
