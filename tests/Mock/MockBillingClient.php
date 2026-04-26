<?php

namespace App\Tests\Mock;

use App\Exception\BillingException;
use App\Security\User;
use App\Service\BillingClient;

class MockBillingClient extends BillingClient
{
    public function __construct(){
        parent::__construct("empty_billing");
    }

    public function authenticate(string $email, string $password): string
    {
        if ($email === 'admin@test.local' && $password === 'admin_password') {
            return 'mock-admin-token';
        }

        if ($email === 'user@test.local' && $password === 'user_password') {
            return 'mock-user-token';
        }

        throw new BillingException('Invalid credentials.', 401);
    }

    public function getCurrentUser(string $token): User
    {
        return match ($token) {
            'mock-admin-token' => new User()
                ->setEmail('admin@test.local')
                ->setBalance(1000)
                ->setRoles(['ROLE_SUPER_ADMIN']),
            'mock-user-token' => new User()
                ->setEmail('user@test.local')
                ->setBalance(1000)
                ->setRoles(['ROLE_USER']),
            default => throw new BillingException('Unauthorized.', 401),
        };
    }

    public function register(string $email, string $password): string
    {
        if ($email === 'existing@test.local') {
            throw new BillingException('Bad request.', 400, [
                'email' => ['User already exists.'],
            ]);
        }

        return 'mock-user-token';
    }
}
