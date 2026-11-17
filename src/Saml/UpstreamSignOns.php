<?php

declare(strict_types=1);

namespace App\Saml;

use function array_is_list;
use function array_key_exists;
use function array_slice;
use App\Http\StepUpOffer;
use App\Http\WaitingPage;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Domain\Interaction\Interaction;
use Beffroi\Saml\Domain\Upstream\SentAuthnRequest;
use DateTimeImmutable;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * beffroi/saml S-50
 *
 * @phpstan-type StepUp array{classes: non-empty-list<string>, kinds: non-empty-list<string>}
 * @phpstan-type Common array{tenant: string, request: string, identity_provider: string, force_authn: bool, issued_at: int}
 * @phpstan-type Kept array{tenant: string, interaction: string, request: string, identity_provider: string, force_authn: bool, issued_at: int}|array{tenant: string, page: array{route: string, parameters: array<string, string>}, step_up: StepUp|null, request: string, identity_provider: string, force_authn: bool, issued_at: int}
 */
final readonly class UpstreamSignOns
{
    public const string SESSION_KEY = '_beffroi_saml_upstream_sign_ons';

    public const int MAX_KEPT = 8;

    /**
     * Seconds, as long as an interaction waits (spec/saml.md section 7).
     */
    public const int WAIT = 600;

    public function keep(SessionInterface $httpSession, Tenant $tenant, Interaction|WaitingPage $for, SentAuthnRequest $sent): void
    {
        $kept = self::kept($httpSession);
        $common = [
            'request' => $sent->id,
            'identity_provider' => $sent->request->identityProvider,
            'force_authn' => $sent->request->forceAuthn,
            'issued_at' => $sent->request->issuedAt->getTimestamp(),
        ];
        $kept[$sent->request->relayState] = $for instanceof Interaction
            ? [
                'tenant' => $tenant->id->toRfc4122(),
                'interaction' => $for->id,
                ...$common,
            ]
            : [
                'tenant' => $tenant->id->toRfc4122(),
                'page' => [
                    'route' => $for->route,
                    'parameters' => $for->parameters,
                ],
                'step_up' => $for->stepUp === null
                    ? null
                    : [
                        'classes' => $for->stepUp->classes,
                        'kinds' => $for->stepUp->kinds,
                    ],
                ...$common,
            ];
        $httpSession->set(self::SESSION_KEY, array_slice($kept, -self::MAX_KEPT, preserve_keys: true));
    }

    /**
     * Forgotten as it is read, so that a form posted again finds nothing (beffroi/saml S-53).
     */
    public function take(SessionInterface $httpSession, Tenant $tenant, string $relayState, DateTimeImmutable $now): ?RememberedSignOn
    {
        $kept = self::kept($httpSession);
        if (! array_key_exists($relayState, $kept)) {
            return null;
        }
        $entry = $kept[$relayState];
        unset($kept[$relayState]);
        $httpSession->set(self::SESSION_KEY, $kept);
        if ($entry['tenant'] !== $tenant->id->toRfc4122() || ($entry['issued_at'] + self::WAIT) < $now->getTimestamp()) {
            return null;
        }
        $issuedAt = new DateTimeImmutable('@' . $entry['issued_at']);
        if (array_key_exists('interaction', $entry)) {
            return RememberedSignOn::forInteraction($entry['interaction'], $entry['request'], $entry['identity_provider'], $entry['force_authn'], $issuedAt);
        }
        $stepUp = $entry['step_up'];

        return RememberedSignOn::forPage(
            new WaitingPage(
                $entry['page']['route'],
                $entry['page']['parameters'],
                $stepUp === null ? null : new StepUpOffer($stepUp['classes'], $stepUp['kinds']),
            ),
            $entry['request'],
            $entry['identity_provider'],
            $entry['force_authn'],
            $issuedAt,
        );
    }

    /**
     * @return array<string, Kept>
     */
    private static function kept(SessionInterface $httpSession): array
    {
        $stored = $httpSession->get(self::SESSION_KEY);
        if (! is_array($stored)) {
            return [];
        }
        $kept = [];
        foreach ($stored as $reference => $entry) {
            if (! is_string($reference) || ! is_array($entry)) {
                continue;
            }
            $common = self::common($entry);
            if ($common === null) {
                continue;
            }
            $interaction = $entry['interaction'] ?? null;
            $page = self::page($entry['page'] ?? null);
            if (is_string($interaction) && ! array_key_exists('page', $entry)) {
                $kept[$reference] = [
                    'tenant' => $common['tenant'],
                    'interaction' => $interaction,
                    'request' => $common['request'],
                    'identity_provider' => $common['identity_provider'],
                    'force_authn' => $common['force_authn'],
                    'issued_at' => $common['issued_at'],
                ];
            } elseif ($page !== null && ! array_key_exists('interaction', $entry)) {
                $kept[$reference] = [
                    'tenant' => $common['tenant'],
                    'page' => $page,
                    'step_up' => self::stepUp($entry['step_up'] ?? null),
                    'request' => $common['request'],
                    'identity_provider' => $common['identity_provider'],
                    'force_authn' => $common['force_authn'],
                    'issued_at' => $common['issued_at'],
                ];
            }
        }

        return $kept;
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @return Common|null
     */
    private static function common(array $entry): ?array
    {
        $tenant = $entry['tenant'] ?? null;
        $request = $entry['request'] ?? null;
        $identityProvider = $entry['identity_provider'] ?? null;
        $forceAuthn = $entry['force_authn'] ?? null;
        $issuedAt = $entry['issued_at'] ?? null;
        if (! is_string($tenant) || ! is_string($request) || ! is_string($identityProvider) || ! is_bool($forceAuthn) || ! is_int($issuedAt)) {
            return null;
        }

        return [
            'tenant' => $tenant,
            'request' => $request,
            'identity_provider' => $identityProvider,
            'force_authn' => $forceAuthn,
            'issued_at' => $issuedAt,
        ];
    }

    /**
     * @return array{route: string, parameters: array<string, string>}|null
     */
    private static function page(mixed $page): ?array
    {
        if (! is_array($page) || ! is_string($page['route'] ?? null) || ! is_array($page['parameters'] ?? null)) {
            return null;
        }
        $parameters = [];
        foreach ($page['parameters'] as $name => $value) {
            if (! is_string($name) || ! is_string($value)) {
                return null;
            }
            $parameters[$name] = $value;
        }

        return [
            'route' => $page['route'],
            'parameters' => $parameters,
        ];
    }

    /**
     * @return StepUp|null
     */
    private static function stepUp(mixed $stepUp): ?array
    {
        if (! is_array($stepUp)) {
            return null;
        }
        $classes = self::words($stepUp['classes'] ?? null);
        $kinds = self::words($stepUp['kinds'] ?? null);
        if ($classes === [] || $kinds === []) {
            return null;
        }

        return [
            'classes' => $classes,
            'kinds' => $kinds,
        ];
    }

    /**
     * @return list<string>
     */
    private static function words(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }
        $words = [];
        foreach ($value as $word) {
            if (! is_string($word) || $word === '') {
                return [];
            }
            $words[] = $word;
        }

        return $words;
    }
}
