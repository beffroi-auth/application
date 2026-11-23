<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Archive\ArchivePassword;
use Beffroi\Core\Domain\Archive\PersonalArchive;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\ArchiveChapterInterface;
use Beffroi\Core\Port\ArchiveContainerInterface;
use Beffroi\Core\Port\PersonalArchiveRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * RGPD articles 15 and 20
 */
final readonly class ArchivePage
{
    public const string NOTHING_TO_DROP = 'nothing_to_drop';

    public const string PASSPHRASE_REFUSED = 'passphrase_refused';

    public const string TOO_MANY_REQUESTS = 'too_many_requests';

    /**
     * @param iterable<ArchiveChapterInterface> $chapters
     */
    public function __construct(
        private PersonalArchiveRepositoryInterface $archives,
        private iterable $chapters,
        private ArchiveContainerInterface $container,
        private ClockInterface $clock,
        private InteractionPages $pages,
    ) {
    }

    public function render(User $user, ?string $refusal = null): Response
    {
        $now = $this->clock->now();
        $archive = $this->archives->latestOf($user);
        $chapters = [];
        foreach ($this->chapters as $chapter) {
            $chapters[] = $chapter->name();
        }

        return $this->pages->render('account/archive.html.twig', [
            'user' => $user,
            'archive' => $archive,
            'state' => ArchiveState::of($archive, $now),
            'chapters' => $chapters,
            'kept_for_days' => PersonalArchive::KEPT_FOR_DAYS,
            'refusal' => $refusal,
            'passphrase_minimum' => ArchivePassword::MINIMUM_LENGTH,
            'container' => $this->container->extension(),
        ]);
    }
}
