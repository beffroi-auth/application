<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Identity\User;
use function count;
use SortDirection;
use function sprintf;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Each person is scheduled for an update, so that BlindIndexListener derives their tags again on the flush.
 */
#[AsCommand(
    name: self::NAME,
    description: 'Derive again the blind indexes of every person of the tenant, after the projection of one changed.',
)]
final readonly class UserReindexCommand
{
    public const string NAME = 'beffroi:user:reindex';

    public const int PAGE = 100;

    public function __construct(
        private ConfiguredTenant $tenant,
        private EntityManagers $managers,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }

        $manager = $this->managers->forClass(User::class);
        $walked = 0;
        do {
            // The tenant is named by its identifier: every clear detaches its entity.
            /** @var list<User> $page */
            $page = $manager
                ->createQueryBuilder()
                ->select('u')
                ->from(User::class, 'u')
                ->where('u.tenant = :tenant')
                ->orderBy('u.id', SortDirection::Ascending)
                ->setParameter('tenant', $tenant->id, UuidType::NAME)
                ->setFirstResult($walked)
                ->setMaxResults(self::PAGE)
                ->getQuery()
                ->getResult();
            foreach ($page as $user) {
                $manager->getUnitOfWork()->scheduleForUpdate($user);
            }
            $manager->flush();
            $manager->clear();
            $walked += count($page);
        } while (count($page) === self::PAGE);

        $io->success(sprintf('The blind indexes of %d person(s) of the tenant "%s" were derived again.', $walked, $tenant->slug));

        return Command::SUCCESS;
    }
}
