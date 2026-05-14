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
    private ?string $refreshToken = null;

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
        return (string) $this->email;
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

    public function __serialize(): array
    {
        return [
            'email' => $this->getEmail(),
            'roles' => $this->roles,
            'apiToken' => $this->apiToken,
            'refreshToken' => $this->refreshToken,
            'balance' => $this->balance,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->setEmail($data['email']);
        $this->setRoles($data['roles'] ?? []);
        $this->setApiToken($data['apiToken']);
        $this->setRefreshToken($data['refreshToken']);
        $this->setBalance($data['balance'] ?? null);
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }
}
