<?php

namespace App\Tests\Service;

use App\Entity\Course;
use App\Exception\BillingException;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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

    public function testCourseListAndInfo(): void
    {
        $courses = $this->billingClient->getCourseList();

        self::assertCount(5, $courses);
        self::assertContains([
            'code' => 'sql-database-design',
            'type' => 'buy',
            'price' => 5000.0,
        ], $courses);

        self::assertSame([
            'code' => 'symfony-framework-mastery',
            'type' => 'rent',
            'price' => 199.99,
        ], $this->billingClient->getCourseInfo('symfony-framework-mastery'));
    }

    public function testGetCourseInfoForUnknownCourseFails(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionCode(404);

        $this->billingClient->getCourseInfo('unknown-course');
    }

    public function testActiveCoursesAndTransactionHistory(): void
    {
        $tokens = $this->billingClient->authenticate('user@test.local', 'user_password');
        $user = $this->billingClient->getCurrentUser($tokens['token']);

        $activeCourses = $this->billingClient->getActiveCourses($user);
        self::assertContains(['code' => 'web-development-basics'], $activeCourses);
        self::assertContains(['code' => 'sql-database-design'], $activeCourses);

        $transactions = $this->billingClient->getTransactionHistory($user, 'payment', 'symfony-framework-mastery', true);
        self::assertCount(1, $transactions);
        self::assertSame('symfony-framework-mastery', $transactions[0]['course_code']);
        self::assertSame('payment', $transactions[0]['type']);
    }

    public function testPayCourseChangesBalanceAndReturnsBillingResponse(): void
    {
        $token = $this->billingClient->register('pay-user@test.local', 'password');
        $user = $this->billingClient->getCurrentUser($token);
        $course = (new Course())->setSymbolicName('symfony-framework-mastery');

        $response = $this->billingClient->payCourse($user, $course);

        self::assertTrue($response['success']);
        self::assertSame('rent', $response['course_type']);
        self::assertArrayHasKey('expires_at', $response);
        self::assertSame(2800.01, $this->billingClient->getCurrentUser($token)->getBalance());
    }
}
