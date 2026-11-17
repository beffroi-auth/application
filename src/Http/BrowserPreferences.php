<?php

declare(strict_types=1);

namespace App\Http;

use function array_key_exists;
use function array_keys;
use function explode;
use function ksort;
use function implode;
use LogicException;
use function sprintf;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use function time;

final readonly class BrowserPreferences
{
    /**
     * The "__Host-" prefix: a browser refuses it unless Secure, on "/" and with no Domain, so no subdomain writes it.
     */
    public const string COOKIE = '__Host-beffroi_preferences';

    /**
     * A year, in seconds.
     */
    public const int LIFETIME = 31_536_000;

    /**
     * @var array<string, BrowserPreferenceInterface>
     */
    private array $declared;

    /**
     * @param iterable<BrowserPreferenceInterface> $preferences
     *
     * @throws LogicException when two preferences name one slot
     */
    public function __construct(iterable $preferences)
    {
        $declared = [];
        foreach ($preferences as $preference) {
            $slot = $preference->slot();
            if (array_key_exists($slot, $declared)) {
                throw new LogicException(sprintf('Two preferences of a browser name the slot "%s".', $slot));
            }
            $declared[$slot] = $preference;
        }
        $this->declared = $declared;
    }

    public function read(Request $request, string $slot): ?string
    {
        return $this->held($request)[$slot] ?? null;
    }

    /**
     * @throws LogicException
     */
    public function cookie(Request $request, string $slot, string $value): Cookie
    {
        $preference = $this->declared[$slot] ?? throw new LogicException(sprintf(
            'No preference of a browser declares the slot "%s"; the wired ones are %s.',
            $slot,
            $this->declared === [] ? 'none' : '"' . implode('", "', array_keys($this->declared)) . '"',
        ));
        $served = $preference->read($value) ?? throw new LogicException(sprintf(
            'The preference of the slot "%s" does not serve the value "%s".',
            $slot,
            $value,
        ));

        $slots = [$slot => $served] + $this->held($request);
        ksort($slots);
        $pairs = [];
        foreach ($slots as $declared => $value) {
            $pairs[] = $declared . '=' . $value;
        }

        return Cookie::create(
            self::COOKIE,
            implode('&', $pairs),
            time() + self::LIFETIME,
            '/',
            null,
            true,
            true,
            false,
            Cookie::SAMESITE_LAX,
        );
    }

    /**
     * @return array<string, string> values by slot
     */
    private function held(Request $request): array
    {
        $held = [];
        foreach (explode('&', $request->cookies->getString(self::COOKIE)) as $pair) {
            [$slot, $value] = explode('=', $pair, 2) + [1 => ''];
            $served = ($this->declared[$slot] ?? null)?->read($value);
            if ($served !== null) {
                $held[$slot] = $served;
            }
        }

        return $held;
    }
}
