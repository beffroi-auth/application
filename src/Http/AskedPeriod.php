<?php

declare(strict_types=1);

namespace App\Http;

use App\Locale\UserTimeZone;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Listing\Period;
use DateTimeImmutable;
use InvalidArgumentException;
use function is_string;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class AskedPeriod
{
    public const string FROM = 'from';

    public const string TO = 'to';

    public function __construct(
        private UserTimeZone $zones,
        private RequestStack $requests,
    ) {
    }

    /**
     * @param string $from the word of the query string that names the first day
     * @param string $to the word that names the last one
     */
    public function asked(?User $reader = null, string $from = self::FROM, string $to = self::TO): ?Period
    {
        $request = $this->requests->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        try {
            return Period::ofDays(
                $this->day($request, $from, $reader),
                $this->day($request, $to, $reader),
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function day(Request $request, string $name, ?User $reader): ?DateTimeImmutable
    {
        $asked = $request->query->get($name);
        if (! is_string($asked) || $asked === '') {
            return null;
        }
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $asked, $this->zones->of($reader));

        return $day === false ? null : $day;
    }
}
