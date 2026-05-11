<?php

namespace App\Tests\Lesson;

use App\Entity\Course;
use App\Service\BillingClient;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class LessonCreationTest extends WebTestCase
{
    private readonly EntityManager $entityManager;
    private readonly KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->loginBillingUser('admin@test.local', 'admin_password');

        $this->entityManager = static::$kernel->getContainer()->get('doctrine.orm.entity_manager');
    }

    private function loginBillingUser(string $email, string $password): void
    {
        $tokens = static::getContainer()->get(BillingClient::class)->authenticate($email, $password);
        $user = static::getContainer()->get(BillingClient::class)->getCurrentUser($tokens['token']);
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

    public function testCreateLessonWithCourseId(): void
    {
        // For stability: create temporal course with stable id for this test
        $tmpCourse = new Course()
            ->setName("TmpCourse")
            ->setSymbolicName('tmp-course')
            ->setDescription("temporal course");
        $this->entityManager->persist($tmpCourse);
        $this->entityManager->flush();
        $courseId = $tmpCourse->getId();

        $crawler = $this->client->request('GET', "/lessons/new?course_id=$courseId");
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Создать')->form();
        $form['lesson[name]']='Тестовый урок';
        $form['lesson[content]']='Содержание тестового урока';
        $form['lesson[index]']='5';
        self::assertSame((string)$courseId, $form['lesson[Course]']->getValue());

        $this->client->submit($form);

        self::assertResponseRedirects();
        $redirectUrl = $this->client->getResponse()->headers->get('location');
        self::assertStringStartsWith('/lessons/', $redirectUrl);

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'body > h1',
            "5. Тестовый урок"
        );
        self::assertSelectorTextContains(
            'body p',
            "Содержание тестового урока"
        );
    }

    public function testCreateLessonWithoutCourseId(): void
    {
        // For stability: create temporal course with stable id for this test
        $tmpCourse = new Course()
            ->setName("TmpCourse")
            ->setSymbolicName('tmp-course')
            ->setDescription("temporal course");
        $this->entityManager->persist($tmpCourse);
        $this->entityManager->flush();
        $courseId = $tmpCourse->getId();

        $crawler = $this->client->request('GET', '/lessons/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Создать')->form();
        $form['lesson[name]']='Тестовый урок';
        $form['lesson[content]']='Содержание тестового урока';
        $form['lesson[index]']='5';
        $form['lesson[Course]']=$courseId;

        $this->client->submit($form);
        self::assertResponseRedirects();
        $redirectUrl = $this->client->getResponse()->headers->get('location');
        self::assertStringStartsWith('/lessons/', $redirectUrl);

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'body > h1',
            "5. Тестовый урок"
        );
        self::assertSelectorTextContains(
            'body > p',
            "Содержание тестового урока"
        );
    }

    public function testLessonRequiresFields(): void
    {
        $crawler = $this->client->request('GET', '/lessons/new');

        $form = $crawler->selectButton('Создать')->form();
        $form['lesson[name]']='';
        $form['lesson[content]']='';
        $form['lesson[index]']='';

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorExists("#lesson_name_error1");
        self::assertSelectorExists("#lesson_content_error1");
    }
}
