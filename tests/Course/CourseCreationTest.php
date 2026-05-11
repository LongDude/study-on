<?php

namespace App\Tests\Course;

use App\Entity\Course;
use App\Service\BillingClient;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CourseCreationTest extends WebTestCase
{
    private readonly EntityManager $entityManager;
    private readonly KernelBrowser $client;
    private BillingClient $billingClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->entityManager = static::$kernel->getContainer()->get('doctrine.orm.entity_manager');
        $this->billingClient = static::$kernel->getContainer()->get('App\Service\BillingClient');
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
        $tokens = $this->billingClient->authenticate($email, $password);
        $user = $this->billingClient->getCurrentUser($tokens['token']);
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

    public function testAdminCreateNewCourse(): void
    {
        $this->authorizeRole("Admin");
        $crawler = $this->client->request('GET', '/courses/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Добавить')->form();
        $form['course[name]'] = "Тестовый курс";
        $form['course[description]'] = "Описание тестового курса";
        $form['course[symbolic_name]'] = 'test-course';
        $this->client->submit($form);

        self::assertResponseRedirects('/courses');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('div.card h5.card-title', "Тестовый курс");
        self::assertAnySelectorTextContains("div.card p.card-text", "Описание тестового курса");
    }

    public function testCreateCourseWithDuplicateSymnames(): void
    {
        $this->authorizeRole("Admin");

        // Создаем первый курс и занимаем символьное имя
        $course = new Course();
        $course->setName("Тестовый курс");
        $course->setDescription("Курс для тестирования редактирования");
        $course->setSymbolicName("test-course");
        try {
            $this->entityManager->persist($course);
            $this->entityManager->flush();
        } catch (ORMException $e) {
            $this->fail($e->getMessage());
        }

        // Страница добавления курса
        $crawler = $this->client->request('GET', '/courses/new');
        self::assertResponseIsSuccessful();

        // Заполняем форму добавления с дублированием имени
        $form = $crawler->selectButton('Добавить')->form();
        $form['course[name]'] = "Тестовый курс";
        $form['course[description]'] = "Описание тестового курса";
        $form['course[symbolic_name]'] = 'test-course';
        $this->client->submit($form);

        // Проверка наличия ошибки
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorExists('#course_symbolic_name_error1');
    }

    public function testRequiresFields(): void
    {
        $this->authorizeRole("Admin");

        // Страница добавления курса
        $crawler = $this->client->request('GET', '/courses/new');
        self::assertResponseIsSuccessful();

        // Заполняем форму добавления с пустыми полями
        $form = $crawler->selectButton('Добавить')->form();
        $form['course[name]'] = "";
        $form['course[description]'] = "";
        $form['course[symbolic_name]'] = "";
        $this->client->submit($form);

        // Проверка ошибок полей
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorExists("#course_name_error1");
        self::assertSelectorExists("#course_description_error1");
        self::assertSelectorExists("#course_symbolic_name_error1");
    }

    public function testFieldLimits(): void
    {
        $this->authorizeRole("Admin");

        // Страница добавления курса
        $crawler = $this->client->request('GET', '/courses/new');
        self::assertResponseIsSuccessful();

        // Заполняем форму добавления с пустыми полями
        $form = $crawler->selectButton('Добавить')->form();
        $form['course[name]'] = implode('s', range(0, 256));
        $form['course[description]'] = implode('s', range(0, 1001));
        $form['course[symbolic_name]'] = implode('s', range(0, 256));
        $this->client->submit($form);

        // Проверка ошибок полей
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorExists("#course_name_error1");
        self::assertSelectorExists("#course_description_error1");
        self::assertSelectorExists("#course_symbolic_name_error1");
    }

    public function testUserCreateBlocked(): void
    {
        $this->authorizeRole("User");
        $this->client->request('GET', '/courses/new');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnonymousCreateBlocked(): void
    {
        $this->client->request('GET', '/courses/new');
        self::assertResponseRedirects('/login');
    }
}
