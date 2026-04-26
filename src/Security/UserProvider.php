<?php

namespace App\Security;

use App\Exception\BillingException;
use App\Service\BillingClient;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{

    public function __construct(
        private readonly BillingClient $billingClient,
    ){}

    /**
     * @throws UserNotFoundException if the user is not found
     */
    public function loadUserByIdentifier($identifier): UserInterface
    {
        // Более правильным подходом было бы хранить в условном Redis пару mail-token
        // и сверять с RememberMe cookie валидность токена
        if (empty($identifier)) {
            throw new UserNotFoundException("Не предоставлен токен пользователя");
        }

        try {
            $usr = $this->billingClient->getCurrentUser($identifier);
            $usr->setApiToken($identifier);
            return $usr;
        } catch (BillingException $e) {
            if ($e->getCode() >= 500) {
                throw new UserNotFoundException("Сервис недоступен, повторите попытку позже");
            }
            throw new UserNotFoundException("Пользователь не найден");
        }
    }

    /**
     * @throws BillingException if cannot refresh user in Billing service
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }
        if (!$user->getApiToken() || empty($user->getApiToken())) {
            throw new UnsupportedUserException(sprintf('Cannot refresh user with an empty API token: %s', $user->getEmail()));
        }

        try {
            $curUsr = $this->billingClient->getCurrentUser($user->getApiToken());
            $curUsr->setApiToken($user->getApiToken());
            return $curUsr;
        } catch (BillingException $e) {
            throw new UnsupportedUserException("Сервис временно недоступен");

        }
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    /**
     * Upgrades the hashed password of a user, typically for using a better hash algorithm.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        // TODO: when hashed passwords are in use, this method should:
        // 1. persist the new password in the user storage
        // 2. update the $user object with $user->setPassword($newHashedPassword);
    }
}
