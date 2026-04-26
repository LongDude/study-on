<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    private $email;

    /**
     * @var list<string> The user roles
     */

    private $roles = [];

    private ?string $apiToken = null;

    private ?float $balance = null;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->apiToken;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function setApiToken(?string $apiToken): static
    {
        $this->apiToken = $apiToken;
        return $this;
    }

    public function setBalance(?float $balance): static
    {
        $this->balance = $balance;
        return $this;
    }

    public function getApiToken(): ?string
    {
        return $this->apiToken;
    }

    public function getBalance(): ?float
    {
        return $this->balance;
    }

    public function __serialize(): array {
        // Профиль пользователя фактически хранится по токену
        // После refreshUser восстанавливаем свежие данные по токену
        // userIdentifier нужен для корректной обработки пользователя Symfony
        return [
            'email' => $this->getEmail(),
            'apiToken' => $this->apiToken,
        ];
    }

    public function __unserialize(array $data): void {
        // Получаем два поля - идентификатор для сравнения с новой записью в Security и токен для обновления данных
        $this->setEmail($data['email']);
        $this->setApiToken($data['apiToken']);
    }
}
