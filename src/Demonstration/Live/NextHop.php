<?php

declare(strict_types=1);

namespace App\Demonstration\Live;

use App\Demonstration\Trace\FlowTrace;
use Beffroi\Oidc\Domain\Flow\FlowPicture;
use Beffroi\Oidc\Domain\Flow\Hop;
use Beffroi\Oidc\Exception\InvalidArgumentException;
use Beffroi\Oidc\Port\ServedGrantsInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class NextHop
{
    public const string HOPS = 'app.demonstration.hop';

    /**
     * @param iterable<DrivenHopInterface> $hops
     */
    public function __construct(
        #[AutowireIterator(self::HOPS)]
        private iterable $hops,
        private ServedGrantsInterface $served,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the wiring does not hold together
     */
    public function of(FlowPicture $picture, FlowTrace $trace): ?DrivenHopInterface
    {
        foreach ($picture->hops as $hop) {
            $driven = $this->driving($picture, $hop);
            if ($driven === null || $this->taken($driven, $hop, $trace)) {
                continue;
            }

            return $driven;
        }

        return null;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function opens(FlowPicture $picture): bool
    {
        return $this->driving($picture, $picture->hops[0]) !== null;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function driving(FlowPicture $picture, Hop $hop): ?DrivenHopInterface
    {
        foreach ($this->hops as $driven) {
            if ($driven->hops()[0] !== $hop->name) {
                continue;
            }
            foreach ($driven->flows($this->served) as $flow) {
                if ($flow->equals($picture->grantType)) {
                    return $driven;
                }
            }
        }

        return null;
    }

    private function taken(DrivenHopInterface $driven, Hop $hop, FlowTrace $trace): bool
    {
        $answer = $driven->hops()[1];
        if (! $trace->drew($answer)) {
            return false;
        }
        if (! $hop->repeats) {
            return true;
        }
        foreach ($trace->hops as $traced) {
            if ($traced->hop === $answer) {
                $last = $traced;
            }
        }

        return isset($last) && ! $last->failed;
    }
}
