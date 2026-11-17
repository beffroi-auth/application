<?php

declare(strict_types=1);

namespace App\Security;

use App\Doctrine\TenantProvider;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Credential\ActivePassword;
use Beffroi\Core\Domain\Event\PasswordRehashed;
use Beffroi\Core\Domain\Session\RequestContext;
use Beffroi\Core\Port\CredentialRepositoryInterface;
use Beffroi\Core\Port\UserRepositoryInterface;
use LogicException;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SensitiveParameter;
use function sprintf;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use function trim;

/**
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class IdentityUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private TenantProvider $tenants,
        private UserRepositoryInterface $users,
        private CredentialRepositoryInterface $credentials,
        private ActivePassword $activePassword,
        private ClockInterface $clock,
        private EventDispatcherInterface $events,
        private RequestStack $requests,
        private RequestContextFactory $contexts,
        private CorePasswordHasher $hasher,
    ) {
    }

    /**
     * A disabled user is loaded like any other; the state is checked after the password (UserChecker).
     */
    public function loadUserByIdentifier(string $identifier): SecurityUser
    {
        $user = $this->users->findByUsername($this->tenants->provide(), trim($identifier));
        if ($user === null) {
            $exception = new UserNotFoundException();
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return new SecurityUser($user, $this->activePassword->of($user));
    }

    /**
     * By identifier, so that a rename does not sign the user out.
     */
    public function refreshUser(UserInterface $user): SecurityUser
    {
        if (! $user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }
        $found = $this->users->find($user->id);
        if ($found === null || ! $found->belongsTo($this->tenants->provide()) || ! $found->isEnabled()) {
            $exception = new UserNotFoundException();
            $exception->setUserIdentifier($user->getUserIdentifier());

            throw $exception;
        }

        return new SecurityUser($found, $this->activePassword->of($found));
    }

    public function supportsClass(string $class): bool
    {
        return $class === SecurityUser::class;
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, #[SensitiveParameter] string $newHashedPassword): void
    {
        if (! $user instanceof SecurityUser) {
            return;
        }
        $credential = $user->passwordCredential();
        if ($credential === null) {
            return;
        }
        $hash = $this->hasher->decode($newHashedPassword) ?? throw new LogicException(sprintf(
            'The hash to upgrade to was not produced by "%s"; the firewall of the interaction pages uses another password hasher than the one the provider configures.',
            CorePasswordHasher::class,
        ));
        $now = $this->clock->now();
        $credential->rehash($hash, $now);
        $this->credentials->save($credential);
        $this->events->dispatch(new PasswordRehashed($credential, $now, $this->context()));
    }

    private function context(): RequestContext
    {
        $request = $this->requests->getCurrentRequest();

        return $request === null ? new RequestContext($this->clock->now()) : $this->contexts->fromRequest($request);
    }
}
