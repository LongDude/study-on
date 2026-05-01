<?php

namespace App\Security;

use AllowDynamicProperties;
use App\Exception\BillingException;
use App\Service\BillingClient;
use App\Service\TokenDecoderService;
use PHPUnit\Framework\Exception;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Exception\InvalidParameterException;
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
                throw new UnsupportedUserException(
                    "Сервис недоступен, повторите попытку позже"
                );
            }
            throw new UserNotFoundException("Пользователь не найден");
        }
    }

    /**
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

        // Обновляем JWT токен если устарел
        $expirationDate = $this->tokenDecoderService->getTokenExpiration($user->getApiToken());
        if (empty($expirationDate) || $expirationDate < time()) {
            // Expired token, try to refresh
            try {
                $newApiToken = $this->billingClient->refreshToken($user->getRefreshToken());
                $user->setApiToken($newApiToken);
            } catch (BillingException $e) {
                if ($e->getCode() === 401) {
                    throw new UserNotFoundException("Время жизни сессии истекло");
                }
                throw new UnsupportedUserException("Сервис временно недоступен");
            }
        }

        try {
            $curUsr = $this->billingClient->getCurrentUser($user->getApiToken());
            $curUsr->setApiToken($user->getApiToken());
            return $curUsr;
        } catch (BillingException $e) {
            if ($e->getCode() === 401) {
                throw new UserNotFoundException(
                    "Ошибка обновления сессии пользователя"
                );
            }
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
