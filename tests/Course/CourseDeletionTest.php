<?php

namespace App\Tests\Course;

use App\Entity\Course;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CourseDeletionTest extends WebTestCase
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

    public function testCourseDeletion(): void
    {
        // Создаем курс для удаления
        $course = new Course();
        $course->setName("Тестовый курс");
        $course->setDescription("Курс для тестирования удаления");
        $course->setSymbolicName("test-course");
        try {
            $this->entityManager->persist($course);
            $this->entityManager->flush();
        } catch (ORMException $e) {
            $this->fail($e->getMessage());
        }

        $crawler = $this->client->request('GET', "/courses/{$course->getId()}");
        $form = $crawler->selectButton("Удалить курс")->form();
        $this->client->submit($form);

        self::assertResponseRedirects("/courses");
        $this->client->followRedirect();
        self::assertAnySelectorTextNotContains("div.card h5.card-title", "Тестовый курс");
    }
}
