<?php

declare(strict_types=1);

namespace App\Confirmation;

use App\Configuration\BoundedNumber;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\ConfirmationSendLimiterInterface;
use DateInterval;
use function hash_hmac;
use function mb_strtolower;
use function preg_replace;
use SensitiveParameter;
use function sprintf;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * Decision 0014: counted on sliding windows, the person by tenant and by identifier, the address for the whole
 * deployment, on the store of the limits of the attempts and under a lock every process of a deployment takes.
 */
final readonly class RateLimitedConfirmationSends implements ConfirmationSendLimiterInterface
{
    private RateLimiterFactoryInterface $persons;

    private RateLimiterFactoryInterface $addresses;

    /**
     * Checked here as well as in the tree: a value from the environment is a number only here. An address takes more
     * than a person, so that one person never spends the budget of an address alone.
     *
     * @param string $secret keys the hash an address is counted under, which the store then never holds in clear
     *
     * @throws InvalidConfigurationException when a limit is below one, or the one of an address is not above the one of a person
     */
    public function __construct(
        int $personLimit,
        int $addressLimit,
        DateInterval $interval,
        StorageInterface $storage,
        LockFactory $locks,
        #[SensitiveParameter]
        private string $secret,
    ) {
        BoundedNumber::assertAtLeast($personLimit, 1, 'confirmation.sends.person_limit', 'confirmations');
        BoundedNumber::assertAtLeast($addressLimit, $personLimit + 1, 'confirmation.sends.address_limit', 'confirmations');
        $this->persons = self::limiter('beffroi_confirmations_of_a_person', $personLimit, $interval, $storage, $locks);
        $this->addresses = self::limiter('beffroi_confirmations_to_an_address', $addressLimit, $interval, $storage, $locks);
    }

    /**
     * An address is counted whatever its case and the spaces, dots, hyphens and brackets a person writes in a number.
     */
    public function spend(User $user, #[SensitiveParameter] string $address): bool
    {
        $person = $this->persons->create(sprintf('%s|%s', $user->tenant->id->toRfc4122(), $user->id->toRfc4122()))->consume();
        $written = (string) preg_replace('/[\s.\-()]/u', '', mb_strtolower($address));
        $destination = $this->addresses->create(hash_hmac('sha256', $written, $this->secret))->consume();

        return $person->isAccepted() && $destination->isAccepted();
    }

    private static function limiter(string $id, int $limit, DateInterval $interval, StorageInterface $storage, LockFactory $locks): RateLimiterFactoryInterface
    {
        return new RateLimiterFactory(
            [
                'id' => $id,
                'policy' => 'sliding_window',
                'limit' => $limit,
                'interval' => $interval,
            ],
            $storage,
            $locks,
        );
    }
}
