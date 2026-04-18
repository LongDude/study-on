<?php

namespace App\Tests\Course;

use App\Entity\Course;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use http\Client;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CourseEditTest extends WebTestCase
{
    private readonly EntityManager $entityManager;
    private readonly KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->entityManager = static::$kernel->getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        parent::tearDown();
    }

    public function testCourseEdit(): void
    {
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

        $crawler = $this->client->request('GET', '/courses/' . $course->getId() . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton("Сохранить")->form();
        $form['course[name]'] = 'Обновленный курс';
        $form['course[description]'] = 'Обновленный курс для тестирования редактирования';

        $this->client->submit($form);
        self::assertResponseRedirects('/courses/' . $course->getId());

        $this->client->followRedirect();
        self::assertSelectorTextContains('main h1', 'Обновленный курс');
        self::assertSelectorTextContains('main p', 'Обновленный курс для тестирования редактирования');
    }
}
