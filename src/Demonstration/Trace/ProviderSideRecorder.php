<?php

declare(strict_types=1);

namespace App\Demonstration\Trace;

use App\Demonstration\ProviderEndpointsOfTheTenant;
use App\Plane\Plane;
use Beffroi\Oidc\Domain\Flow\FlowPicture;
use Beffroi\Oidc\Port\FlowPicturesInterface;
use Beffroi\Oidc\Port\ServedGrantsInterface;
use function http_build_query;
use function is_string;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * Never wired in a deployment: what it records holds every token a flow exchanged.
 */
final readonly class ProviderSideRecorder implements EventSubscriberInterface
{
    public const string AGENT = 'beffroi-demonstration';

    public const string ENDPOINTS = 'app.demonstration.endpoint';

    /**
     * @param iterable<RecordedEndpointInterface> $endpoints
     */
    public function __construct(
        #[AutowireIterator(self::ENDPOINTS)]
        private iterable $endpoints,
        private ServedGrantsInterface $served,
        private FlowPicturesInterface $pictures,
        private ProviderEndpointsOfTheTenant $provider,
        private TraceStore $traces,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        // After everything that writes a response, so that what is recorded is what left.
        return [KernelEvents::RESPONSE => ['record', -1024]];
    }

    public function record(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        if (! $event->isMainRequest() || ! is_string($route) || Plane::ofRoute($route) === null) {
            return;
        }
        if ($request->headers->get('User-Agent') === self::AGENT) {
            return;
        }
        try {
            $endpoints = $this->provider->endpoints();
        } catch (Throwable) {
            return;
        }
        // No break: one call can be a hop of two pictures.
        foreach ($this->endpoints as $recognised) {
            if ($recognised->recognises($request, $event->getResponse(), $endpoints)) {
                $this->draw($request, $event->getResponse(), $recognised);
            }
        }
    }

    private function draw(Request $request, Response $response, RecordedEndpointInterface $recognised): void
    {
        $flow = $recognised->flowOf($request, $this->served);
        if ($flow === null) {
            return;
        }
        $picture = $this->pictures->of($flow);
        if ($picture === null) {
            return;
        }
        $clientId = $recognised->clientOf($request);
        $trace = $clientId === null
            ? $this->traces->lastOf($flow->value)
            : $this->traces->of($flow->value, $clientId) ?? $this->traces->opened($flow->value, $clientId);
        if ($trace === null) {
            return;
        }

        [$sent, $answered] = $recognised->hops();
        if ($sent !== null && $sent === $picture->hops[0]->name && $trace->drew($sent)) {
            $trace = $this->traces->opened($flow->value, $trace->clientId);
        }
        $at = $this->clock->now();
        $drawn = false;
        if ($sent !== null && self::draws($picture, $sent, $trace)) {
            $trace = $trace->with(TracedHop::sent(
                $sent,
                $at,
                $request->getMethod(),
                $request->getUri(),
                self::sentPayload($request),
            ));
            $drawn = true;
        }
        if ($answered !== null && self::draws($picture, $answered, $trace)) {
            $trace = $trace->with(TracedHop::answered(
                $answered,
                $at,
                $response->getStatusCode(),
                self::answeredPayload($response),
            ));
            $drawn = true;
        }
        if ($drawn) {
            $this->traces->save($trace);
        }
    }

    private static function draws(FlowPicture $picture, string $hop, FlowTrace $trace): bool
    {
        foreach ($picture->hops as $drawn) {
            if ($drawn->name === $hop) {
                return $drawn->repeats || ! $trace->drew($hop);
            }
        }

        return false;
    }

    /**
     * The parsed fields stand for a body already read, which is what a worker leaves behind.
     */
    private static function sentPayload(Request $request): string
    {
        if ($request->isMethod(Request::METHOD_GET)) {
            return $request->getQueryString() ?? '';
        }
        $body = $request->getContent();

        return $body !== '' ? $body : http_build_query($request->request->all());
    }

    private static function answeredPayload(Response $response): string
    {
        $location = $response->headers->get('Location');
        if ($location !== null) {
            return $location;
        }
        $body = $response->getContent();

        return $body === false ? '' : $body;
    }
}
