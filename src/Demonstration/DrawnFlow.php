<?php

declare(strict_types=1);

namespace App\Demonstration;

use function array_map;
use App\Demonstration\Trace\FlowTrace;
use App\Demonstration\Trace\Payload;
use App\Demonstration\Trace\TracedHop;
use Beffroi\Oidc\Domain\Flow\Actor;
use Beffroi\Oidc\Domain\Flow\FlowPicture;
use Beffroi\Oidc\Domain\Flow\Hop;

final readonly class DrawnFlow
{
    /**
     * @return array<string, mixed>
     */
    public function of(FlowPicture $picture, ?FlowTrace $trace): array
    {
        return [
            'flow' => $picture->grantType->value,
            'client' => $trace?->clientId,
            'actors' => array_map(static fn (Actor $actor): string => $actor->value, $picture->actors()),
            'plan' => array_map(static fn (Hop $hop): array => [
                'name' => $hop->name,
                'from' => $hop->from->value,
                'to' => $hop->to->value,
                'specification' => $hop->specification,
                'repeats' => $hop->repeats,
                'taken' => $trace?->times($hop->name) ?? 0,
            ], $picture->hops),
            'hops' => $this->hops($picture, $trace),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function hops(FlowPicture $picture, ?FlowTrace $trace): array
    {
        if ($trace === null) {
            return [];
        }
        $hops = [];
        foreach ($trace->hops as $traced) {
            $planned = self::planned($picture, $traced->hop);
            if ($planned === null) {
                continue;
            }
            $hops[] = self::drawn($traced, $planned);
        }

        return $hops;
    }

    /**
     * @return array<string, mixed>
     */
    private static function drawn(TracedHop $traced, Hop $planned): array
    {
        $payload = Payload::of($traced->payload);

        return [
            'name' => $traced->hop,
            'from' => $planned->from->value,
            'to' => $planned->to->value,
            'specification' => $planned->specification,
            'at' => $traced->at->format('H:i:s.v'),
            'method' => $traced->method,
            'url' => $traced->url,
            'status' => $traced->status,
            'failed' => $traced->failed,
            'reason' => $traced->reason,
            'raw' => $traced->payload,
            'indented' => $payload->indented,
            'jwts' => self::jwts($payload),
        ];
    }

    /**
     * @return list<array{name: string, raw: string, header: string, claims: string|null}>
     */
    private static function jwts(Payload $payload): array
    {
        $jwts = [];
        foreach ($payload->jwts as $name => $jwt) {
            $jwts[] = [
                'name' => $name,
                'raw' => $jwt->raw,
                'header' => $jwt->header,
                'claims' => $jwt->claims,
            ];
        }

        return $jwts;
    }

    private static function planned(FlowPicture $picture, string $name): ?Hop
    {
        foreach ($picture->hops as $hop) {
            if ($hop->name === $name) {
                return $hop;
            }
        }

        return null;
    }
}
