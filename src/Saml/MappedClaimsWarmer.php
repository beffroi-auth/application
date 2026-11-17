<?php

declare(strict_types=1);

namespace App\Saml;

use Beffroi\Saml\Port\ProfileClaimsInterface;
use Beffroi\Saml\Port\ServedClaimsInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Checks the claims the declarations name against the real claims providers, which no compiler pass can build.
 */
final readonly class MappedClaimsWarmer implements CacheWarmerInterface
{
    /**
     * @param array<array-key, mixed> $declarations by entity ID
     * @param array<array-key, mixed> $upstreamDeclarations by entity ID
     */
    public function __construct(
        private array $declarations,
        private ServedClaimsInterface $claims,
        private ProfileClaimsInterface $profileClaims,
        private array $upstreamDeclarations = [],
    ) {
    }

    /**
     * Not optional: the warmers a kernel runs right after it compiled its container are those that are not.
     */
    public function isOptional(): bool
    {
        return false;
    }

    /**
     * @throws InvalidConfigurationException
     *
     * @return list<string>
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if ($this->declarations !== []) {
            ServiceProvidersPass::build($this->declarations, $this->claims);
        }
        if ($this->upstreamDeclarations !== []) {
            UpstreamIdentityProvidersPass::build($this->upstreamDeclarations, $this->profileClaims);
        }

        return [];
    }
}
