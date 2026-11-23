<?php

declare(strict_types=1);

namespace App\Administration;

use App\Configuration\BoundedNumber;
use Beffroi\Oidc\Application\Administration\AdministeredGroups;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Beffroi\Oidc\Exception\PersonsBudgetSpentException;
use Beffroi\Oidc\Port\PersonsBudgetInterface;
use DateInterval;
use function max;
use function sprintf;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\StorageInterface;
use function time;

/**
 * Counted on a sliding window by tenant and by the person the token was issued for, under a lock every process of a
 * deployment takes in the same store, so that two requests at once never spend the same persons twice.
 */
final readonly class RateLimitedPersonsBudget implements PersonsBudgetInterface
{
    private RateLimiterFactoryInterface $limiter;

    /**
     * Checked here as well as in the tree: a value from the environment is a number only here. A budget smaller than
     * the largest page would refuse that page to an administrator who has read nobody.
     *
     * @throws InvalidConfigurationException when the limit holds less than the largest page of members
     */
    public function __construct(
        int $limit,
        DateInterval $interval,
        StorageInterface $storage,
        LockFactory $locks,
    ) {
        BoundedNumber::assertAtLeast($limit, AdministeredGroups::LARGEST_PAGE, 'administrator.persons.limit', 'persons');
        $this->limiter = new RateLimiterFactory(
            [
                'id' => 'beffroi_administrator_persons',
                'policy' => 'sliding_window',
                'limit' => $limit,
                'interval' => $interval,
            ],
            $storage,
            $locks,
        );
    }

    /**
     * The delay is read on the clock of the limiter, the machine's, and is never zero, which would invite a retry
     * refused again.
     */
    public function spend(ActingAdministrator $administrator, int $persons): void
    {
        $limit = $this->limiter->create(sprintf('%s|%s', $administrator->tenant->id->toRfc4122(), $administrator->user->id->toRfc4122()))->consume($persons);
        if ($limit->isAccepted()) {
            return;
        }

        throw new PersonsBudgetSpentException(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
    }
}
