<?php

declare(strict_types=1);

namespace App\Twig;

use App\Locale\Moments;
use Beffroi\Core\Domain\Identity\User;
use DateTimeImmutable;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Attribute\AsTwigFilter;

final readonly class MomentExtension
{
    public function __construct(
        private Moments $moments,
        private LocaleSwitcher $switcher,
    ) {
    }

    #[AsTwigFilter(name: 'beffroi_moment')]
    public function moment(DateTimeImmutable $moment, ?User $reader = null): string
    {
        return $this->moments->write($moment, $reader, $this->switcher->getLocale());
    }
}
