<?php

declare(strict_types=1);

namespace App\Http;

use function array_slice;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use LogicException;
use function preg_split;
use function strtolower;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use function trim;

/**
 * Adds the origins a page named to one directive of the Content-Security-Policy its answer carries, whichever policy
 * that is (CSP Level 3 §6.1, §6.3).
 */
final readonly class PolicyCompletion
{
    /**
     * After the policy of the application is written: DeploymentPolicyPrevails recognises the floor of the plane by its
     * value, which a completion made earlier would change.
     */
    public const int AFTER_THE_POLICY_IS_WRITTEN = -32;

    /**
     * A source list on its own (CSP Level 3 §2.2.1): replaced by the origins, never combined with them.
     */
    private const string NO_SOURCE = "'none'";

    /**
     * The report-only policy is completed too: a report of what a page does on purpose tells nobody anything.
     */
    private const array HEADERS = ['Content-Security-Policy', 'Content-Security-Policy-Report-Only'];

    /**
     * @param string $directive lowercase, a directive being matched without regard to case (CSP Level 3 §2.2)
     */
    public function __construct(
        private string $attribute,
        private string $directive,
        private ?string $fallsBackTo = null,
    ) {
    }

    /**
     * Refused here rather than when the answer leaves, where an exception would skip the listeners left to run.
     *
     * @throws LogicException when the value is not an origin
     */
    public function mayReach(Request $request, string $origin): void
    {
        $origin = SourceOrigin::of($origin);
        $origins = $this->named($request);
        if (in_array($origin, $origins, true)) {
            return;
        }
        $origins[] = $origin;
        $request->attributes->set($this->attribute, $origins);
    }

    public function complete(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }
        $origins = $this->named($event->getRequest());
        if ($origins === []) {
            return;
        }
        $headers = $event->getResponse()->headers;
        foreach (self::HEADERS as $header) {
            $policies = $headers->all($header);
            if ($policies === []) {
                continue;
            }
            $headers->remove($header);
            foreach ($policies as $policy) {
                $headers->set($header, $this->completed((string) $policy, $origins), false);
            }
        }
    }

    /**
     * @param non-empty-list<string> $origins
     */
    private function completed(string $policy, array $origins): string
    {
        $directives = [];
        $completed = false;
        $fallback = null;
        foreach (explode(';', $policy) as $directive) {
            $directive = trim($directive);
            if ($directive === '') {
                continue;
            }
            $written = preg_split('/\s+/', $directive);
            if ($written === false) {
                $directives[] = $directive;

                continue;
            }
            $name = strtolower($written[0]);
            if ($name === $this->directive) {
                $directives[] = implode(' ', self::sourcesOf($written[0], array_slice($written, 1), $origins));
                $completed = true;

                continue;
            }
            if ($name === $this->fallsBackTo) {
                $fallback = array_slice($written, 1);
            }
            $directives[] = $directive;
        }
        if ($completed) {
            return implode('; ', $directives);
        }
        if ($fallback === null) {
            return $policy;
        }
        $directives[] = implode(' ', self::sourcesOf($this->directive, $fallback, $origins));

        return implode('; ', $directives);
    }

    /**
     * @param list<string> $written
     * @param non-empty-list<string> $origins
     *
     * @return non-empty-list<string>
     */
    private static function sourcesOf(string $name, array $written, array $origins): array
    {
        $sources = [$name];
        foreach ($written as $source) {
            if ($source !== self::NO_SOURCE) {
                $sources[] = $source;
            }
        }
        foreach ($origins as $origin) {
            if (! in_array($origin, $sources, true)) {
                $sources[] = $origin;
            }
        }

        return $sources;
    }

    /**
     * @return list<string>
     */
    private function named(Request $request): array
    {
        $origins = $request->attributes->get($this->attribute);
        if (! is_array($origins)) {
            return [];
        }
        $named = [];
        foreach ($origins as $origin) {
            if (is_string($origin)) {
                $named[] = $origin;
            }
        }

        return $named;
    }
}
