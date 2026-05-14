<?php

namespace App\Tests\Security;

use App\Security\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testSerializationKeepsAdminRoles(): void
    {
        $user = (new User())
            ->setEmail('admin@email.index')
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setApiToken('access-token')
            ->setRefreshToken('refresh-token')
            ->setBalance(3000.0);

        $restoredUser = unserialize(serialize($user));

        self::assertInstanceOf(User::class, $restoredUser);
        self::assertSame('admin@email.index', $restoredUser->getEmail());
        self::assertContains('ROLE_SUPER_ADMIN', $restoredUser->getRoles());
        self::assertSame('access-token', $restoredUser->getApiToken());
        self::assertSame('refresh-token', $restoredUser->getRefreshToken());
        self::assertSame(3000.0, $restoredUser->getBalance());
    }
}
