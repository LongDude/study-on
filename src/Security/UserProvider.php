<?php

namespace App\Security;

use App\Exception\BillingException;
use App\Service\BillingClient;
use App\Service\TokenDecoderService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    private const int EXPIRATION_THRESHOLD_SEC = 10;

    public function __construct(
        private readonly BillingClient $billingClient,
        private readonly TokenDecoderService  $tokenDecoderService,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Использует RefreshToken из JWT_REFRESH_TOKEN Cookie для восстановления сессии
     * @throws UserNotFoundException if the user is not found
     */
    public function loadUserByIdentifier($identifier): UserInterface
    {
        $refreshToken = $this->requestStack->getCurrentRequest()->cookies->get("JWT_REFRESH_TOKEN");
        if (empty($refreshToken)) {
            throw new UserNotFoundException('Refresh token expired.');
        }

        try {
            $apiToken = $this->billingClient->refreshToken($refreshToken);
            $usr = $this->billingClient->getCurrentUser($apiToken);
            $usr->setApiToken($apiToken);
            $usr->setRefreshToken($refreshToken);
            return $usr;
        } catch (BillingException $e) {
            if ($e->getCode() >= 500) {
                throw new CustomUserMessageAuthenticationException(
                    "Сервис недоступен, повторите попытку позже"
                );
            }
            throw new UserNotFoundException("Пользователь не найден");
        }
    }

    /**
     * Вызывается каждый раз, когда Security нужно
     * получить / проверить пользователя
     * @throws UserNotFoundException
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }
        if (empty($user->getApiToken())) {
            throw new UserNotFoundException(
                sprintf('Cannot refresh user with an empty API token: %s', $user->getEmail())
            );
        }

        // Если токен свежий - обновение не нужно
        $expirationDate = $this->tokenDecoderService->getTokenExpiration($user->getApiToken());
        if ($expirationDate && $expirationDate - $this::EXPIRATION_THRESHOLD_SEC >= time()) {
            return $user;
        }

        // Токен устарел - обновление через сервис
        try {
            $newApiToken = $this->billingClient->refreshToken($user->getRefreshToken());
            $user->setApiToken($newApiToken);
        } catch (BillingException $e) {
            if ($e->getCode() === 401) {
                throw new UserNotFoundException("Время жизни сессии истекло");
            }
        }

        return $user;
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
