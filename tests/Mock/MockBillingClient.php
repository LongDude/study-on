<?php

namespace App\Tests\Mock;

use App\Exception\BillingException;
use App\Security\User;
use App\Service\BillingClient;

class MockBillingClient extends BillingClient
{
    private array $credentials = [];
    private array $tokenCache = [];

    public function __construct(){
        parent::__construct("empty_billing");
        $this->credentials['admin@test.local'] = [
            "password" => "admin_password",
            'token' => "mock-admin-token"
        ];
        $this->tokenCache["mock-admin-token"] = new User()
            ->setApiToken("mock-admin-token")
            ->setEmail("admin@test.local")
            ->setBalance(1000)
            ->setRoles(["ROLE_SUPER_ADMIN"]);

        $this->credentials['user@test.local'] = [
            "password" => "user_password",
            'token' => "mock-user-token"
        ];
        $this->tokenCache["mock-user-token"] = new User()
            ->setApiToken("mock-user-token")
            ->setEmail("user@test.local")
            ->setBalance(100)
            ->setRoles(["ROLE_USER"]);
    }

    public function authenticate(string $email, string $password): string
    {
        if (array_key_exists($email, $this->credentials) && $this->credentials[$email]['password'] === $password ) {
            return $this->credentials[$email]['token'];
        }
        throw new BillingException('Invalid credentials.', 401);
    }

    public function getCurrentUser(string $token): User
    {
        if (array_key_exists($token, $this->tokenCache)) {
            return $this->tokenCache[$token];
        }
        throw new BillingException('Unauthorized.', 401);
    }

    public function register(string $email, string $password): string
    {
        if (array_key_exists($email, $this->credentials)) {
            throw new BillingException('Bad request.', 400, [
                'email' => ['User already exists.'],
            ]);
        }
        $token = base64_encode(random_bytes(10 ));
        $this->credentials[$email] = [
            "password" => $password,
            "token" => $token,
        ];
        $this->tokenCache[$token] = new User()
        ->setApiToken($token)
        ->setEmail($email)
        ->setBalance(0)
        ->setRoles(["ROLE_USER"]);
        return $token;
    }
}
