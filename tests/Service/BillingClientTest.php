<?php

namespace App\Tests\Service;

use App\Exception\BillingException;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BillingClientTest extends KernelTestCase
{
    private BillingClient $billingClient;
    protected function setUp(): void
    {
        parent::setUp();
        $this->billingClient = self::getContainer()->get(BillingClient::class);
    }

    public function testRegisterReturnsToken(): void
    {
        $token = $this->billingClient->register('new-user@test.local', 'password');
        self::assertIsString($token);
        $usr = $this->billingClient->getCurrentUser($token);
        self::assertSame($token, $usr->getApiToken());
        self::assertSame("new-user@test.local", $usr->getEmail());
    }

    public function testRegisterExistingUserFails(): void
    {
        $this->expectException(BillingException::class);
        $this->billingClient->register('user@test.local', 'password');
    }
}
