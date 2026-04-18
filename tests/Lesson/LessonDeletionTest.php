<?php

namespace App\Tests\Lesson;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LessonDeletionTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManager $entityManager;

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

    public function testDeleteLesson(): void
    {
        $crawler = $this->client->request('GET', '/courses/1');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(4, "section > div > div");

        $last_lesson = $crawler->filter("section > div > div")->last();
        $last_lesson_title = $last_lesson->filter('h4')->text();
        $link = $last_lesson->filter('a')->link();

        $lesson_page = $this->client->click($link);
        self::assertResponseIsSuccessful();

        $form = $lesson_page->selectButton('Удалить урок')->form();
        $crawler = $this->client->submit($form);
        self::assertResponseRedirects('/courses/1');
        $this->client->followRedirect();

        self::assertSelectorCount(3, "section > div > div");
        self::assertSelectorTextNotContains('section > div > div > h4', $last_lesson_title);
    }
}
