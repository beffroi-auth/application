<?php

declare(strict_types=1);

namespace App\Demonstration;

use Beffroi\Oidc\Domain\Client\GrantTypeName;
use Beffroi\Oidc\Domain\Flow\FlowPicture;
use Beffroi\Oidc\Exception\InvalidArgumentException;
use Beffroi\Oidc\Port\FlowPicturesInterface;
use function is_string;
use Symfony\Component\HttpFoundation\Request;

/**
 * The flow is read from the query and not the path: a grant type is often an absolute URI.
 */
final readonly class AskedFlow
{
    public const string PARAMETER = 'flow';

    public const string CLIENT_PARAMETER = 'client';

    public function __construct(
        private FlowPicturesInterface $pictures,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the wiring does not hold together
     */
    public function picture(Request $request): ?FlowPicture
    {
        $asked = $request->query->get(self::PARAMETER);
        if (! is_string($asked) || $asked === '') {
            return null;
        }
        foreach ($this->pictures->pictures() as $picture) {
            if ($picture->grantType->value === $asked) {
                return $picture;
            }
        }

        return null;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function grantType(Request $request): ?GrantTypeName
    {
        return $this->picture($request)?->grantType;
    }

    public function client(Request $request): ?string
    {
        $asked = $request->query->get(self::CLIENT_PARAMETER);

        return is_string($asked) && $asked !== '' ? $asked : null;
    }
}
