<?php

namespace App\Tests\Course;

use App\Entity\Course;
use App\Security\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CourseCreationTest extends WebTestCase
{
    private readonly EntityManager $entityManager;
    private readonly KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $testuser = new User()
            ->setApiToken("mock-admin-token")
            ->setEmail('admin@test.local')
            ->setRoles(['ROLE_SUPER_ADMIN']);
        $this->client->loginUser($testuser, 'main');

        $this->entityManager = static::$kernel->getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();
        $this->entityManager->close();
        parent::tearDown();
    }

    public function testCreateNewCourse(): void
    {
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
}
