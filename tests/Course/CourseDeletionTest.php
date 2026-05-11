<?php

namespace App\Tests\Course;

use App\Entity\Course;
use App\Service\BillingClient;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

class CourseDeletionTest extends WebTestCase
{
    private readonly EntityManager $entityManager;
    private readonly KernelBrowser $client;
    private Course $course;


    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();

        $this->entityManager = static::$kernel->getContainer()->get('doctrine.orm.entity_manager');

        $this->course = new Course()
            ->setName("Тестовый курс")
            ->setDescription("Курс для тестирования редактирования")
            ->setSymbolicName("test-course");
        try {
            $this->entityManager->persist($this->course);
            $this->entityManager->flush();
        } catch (ORMException $e) {
            $this->fail($e->getMessage());
        }
    }

    /**
     * Login client as selected user type
     * @param $role string either 'User' or 'Admin'
     * @return void
     */
    private function authorizeRole($role): void {
        if ($role === 'User') {
            $this->loginBillingUser('user@test.local', 'user_password');
        }
        else if ($role === 'Admin') {
            $this->loginBillingUser('admin@test.local', 'admin_password');
        }
        else {
            throw new \ValueError("Expected 'User' or 'Admin', got $role");
        }
    }

    private function loginBillingUser(string $email, string $password): void
    {
        $billingClient = static::getContainer()->get(BillingClient::class);
        $tokens = $billingClient->authenticate($email, $password);
        $user = $billingClient->getCurrentUser($tokens['token']);
        $user->setApiToken($tokens['token']);
        $user->setRefreshToken($tokens['refresh_token']);

        $this->client->loginUser($user, 'main');
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();
        $this->entityManager->close();
        parent::tearDown();
    }

    public function testAdminCourseDeletion(): void
    {
        $this->authorizeRole("Admin");
        $crawler = $this->client->request("GET", "/courses/" . $this->course->getId());

        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton("Удалить курс")->form();
        $this->client->submit($form);

        self::assertResponseRedirects("/courses");
        self::assertNull($this->entityManager->find(Course::class, $this->course->getId()));
    }
}
