<?php

declare(strict_types=1);

namespace App\Demonstration\Trace\Endpoint;

use function count;
use Beffroi\Oidc\Domain\Client\GrantTypeName;

final readonly class OneFlowOfTheWiring
{
    /**
     * @param list<GrantTypeName> $grantTypes
     */
    public static function of(array $grantTypes): ?GrantTypeName
    {
        return count($grantTypes) === 1 ? $grantTypes[0] : null;
    }
}
