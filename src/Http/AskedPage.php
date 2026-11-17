<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Listing\Pagination;
use function is_numeric;
use function max;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class AskedPage
{
    public const string PARAMETER = 'page';

    public const int ROWS = 20;

    public function __construct(
        private RequestStack $requests,
    ) {
    }

    public function asked(): Pagination
    {
        $asked = $this->requests->getCurrentRequest()?->query->get(self::PARAMETER);

        return new Pagination(is_numeric($asked) ? max(1, (int) $asked) : 1, self::ROWS);
    }
}
