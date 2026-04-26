<?php

namespace App\Tests\Course;

use App\Entity\Course;
use App\Security\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use http\Client;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CourseEditTest extends WebTestCase
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
            $this->client->loginUser(
                new User()
                    ->setApiToken("mock-user-token")
                    ->setEmail('user@test.local')
                    ->setRoles(['ROLE_USER']),
                'main'
            );
        }
        else if ($role === 'Admin') {
            $this->client->loginUser(
                new User()
                    ->setApiToken("mock-admin-token")
                    ->setEmail('admin@test.local')
                    ->setRoles(['ROLE_SUPER_ADMIN']),
                'main'
            );
        }
        else {
            throw new \ValueError("Expected 'User' or 'Admin', got $role");
        }
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();
        $this->entityManager->close();
        parent::tearDown();
    }

    public function testAdminCourseEdit(): void
    {
        $this->authorizeRole("Admin");
        $crawler = $this->client->request('GET', '/courses/' . $this->course->getId() . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton("Сохранить")->form();
        $form['course[name]'] = 'Обновленный курс';
        $form['course[description]'] = 'Обновленный курс для тестирования редактирования';

        $this->client->submit($form);
        self::assertResponseRedirects('/courses/' . $this->course->getId());

        $this->client->followRedirect();
        self::assertSelectorTextContains('main h1', 'Обновленный курс');
        self::assertSelectorTextContains('main p', 'Обновленный курс для тестирования редактирования');
    }

    public function testEditUserBlocked(): void {
        $this->authorizeRole("User");
        $this->client->request('GET', '/courses/' . $this->course->getId() . '/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEditAnonymousUserBlocked(): void {
        $this->client->request('GET', '/courses/' . $this->course->getId() . '/edit');
        self::assertResponseRedirects('/login');
    }
}
