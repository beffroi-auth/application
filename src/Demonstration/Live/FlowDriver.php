<?php

declare(strict_types=1);

namespace App\Demonstration\Live;

use App\Demonstration\ProviderEndpointsOfTheTenant;
use App\Demonstration\Trace\FlowTrace;
use App\Demonstration\Trace\TracedHop;
use App\Demonstration\Trace\TraceStore;
use Beffroi\Oidc\Domain\Client\GrantTypeName;
use Beffroi\Oidc\Port\FlowPicturesInterface;

final readonly class FlowDriver
{
    public function __construct(
        private NextHop $next,
        private TakenHop $taken,
        private FlowPicturesInterface $pictures,
        private DemonstrationClients $clients,
        private ProviderEndpointsOfTheTenant $provider,
        private TraceStore $traces,
    ) {
    }

    public function drives(GrantTypeName $flow): bool
    {
        $picture = $this->pictures->of($flow);

        return $picture !== null && $this->clients->of($flow) !== null && $this->next->opens($picture);
    }

    public function open(GrantTypeName $flow): ?FlowTrace
    {
        $client = $this->clients->of($flow);

        return $client === null ? null : $this->traces->opened($flow->value, $client->id);
    }

    public function advance(GrantTypeName $flow): ?FlowTrace
    {
        $picture = $this->pictures->of($flow);
        $client = $this->clients->of($flow);
        if ($picture === null || $client === null) {
            return null;
        }
        $trace = $this->trace($flow) ?? $this->traces->opened($flow->value, $client->id);
        $driven = $this->next->of($picture, $trace);
        if ($driven === null) {
            return $trace;
        }
        $call = $driven->call($trace, $this->provider->endpoints(), $client);
        if ($call === null) {
            return $this->traces->save($trace->with(TracedHop::unreachable(
                $driven->hops()[1],
                $trace->startedAt,
                'The client of this run does not hold what this hop needs: the flow did not get that far.',
            )));
        }

        return $this->traces->save($this->taken->take($driven, $call, $trace, $client));
    }

    public function trace(GrantTypeName $flow): ?FlowTrace
    {
        $client = $this->clients->of($flow);

        return $client === null ? null : $this->traces->of($flow->value, $client->id);
    }
}
