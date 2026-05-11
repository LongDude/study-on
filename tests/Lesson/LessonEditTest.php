<?php

namespace App\Tests\Lesson;

use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LessonEditTest extends WebTestCase
{
    private readonly EntityManager $entityManager;
    private readonly KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->loginUser('admin@test.local', 'admin_password');
        $this->entityManager = static::$kernel->getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function loginUser(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Войти')->form();
        $form->setValues([
            'email' => $email,
            'password' => $password,
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/courses');

        $token = static::getContainer()
            ->get('security.untracked_token_storage')
            ->getToken();
        self::assertNotNull($token, 'Token отсутствует после авторизации');
        self::assertContains('ROLE_SUPER_ADMIN', $token->getRoleNames());
    }
    protected function tearDown(): void
    {
        $this->entityManager->clear();
        $this->entityManager->close();
        parent::tearDown();
    }
    public function testEditLessons(): void
    {
        $this->client->request('GET', '/courses');
        self::assertResponseIsSuccessful();
        $crawler = $this->client->clickLink("Перейти к курсу");
        self::assertResponseIsSuccessful();
        $first_lesson = $crawler->filter('section > div > div')->first();
        $link = $first_lesson->filter('a')->link();
        $crawler = $this->client->click($link);
        self::assertResponseIsSuccessful();

        $lesson_location = $this->client->getRequest()->getRequestUri();
        $edit_form = $crawler->selectButton('Редактировать')->form();
        $crawler = $this->client->submit($edit_form);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Обновить')->form();
        $form['lesson[name]'] = 'Измененный урок';
        $form['lesson[content]'] = "Новое содержание";
        $form['lesson[index]'] = '42';
        $crawler = $this->client->submit($form);

        self::assertResponseRedirects($lesson_location);
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('body > h1', "42. Измененный урок");
        self::assertSelectorTextContains('body > p', 'Новое содержание');
    }
}
