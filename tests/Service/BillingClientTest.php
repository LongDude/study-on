<?php

namespace App\Tests\Service;

use App\Exception\BillingException;
use App\Service\BillingClient;
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

    public function testBillingClientAuthentication(): void
    {
        // Test login
        try {
            $token = $this->billingClient->authenticate(
                "user@email.index", "user_plain_password"
            );
        } catch (BillingException $e) {
            self::fail($e->getMessage());
        }
        self::assertNotEmpty($token);

        // Test token correctnes
        try {
            $userProfile = $this->billingClient->getCurrentUser($token);
        } catch (BillingException $e) {
            self::fail($e->getMessage());
        }
        self::assertNotEmpty($userProfile);
        self::assertArrayHasKey("username", $userProfile);
        self::assertNotEmpty($userProfile["username"]);
        self::assertSame("user@email.index", $userProfile["username"]);
    }
}
