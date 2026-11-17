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

    public function asked(?User $reader = null): ?Period
    {
        $request = $this->requests->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        try {
            return Period::ofDays(
                $this->day($request, self::FROM, $reader),
                $this->day($request, self::TO, $reader),
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
