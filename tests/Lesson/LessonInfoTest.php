<?php

namespace App\Tests\Lesson;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LessonInfoTest extends WebTestCase
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

    public function testLessonDetails(): void
    {
        $crawler = $this->client->request("GET", '/courses/1');
        self::assertResponseIsSuccessful();

        $lessons_list = $crawler->filter("section > div > div");
        self::assertCount(4, $lessons_list);

        $first_lesson = $lessons_list->first()->filter("a")->link();
        $lesson_details = $this->client->click($first_lesson);
        self::assertResponseIsSuccessful();

        // Breadcrumb
        $course = $this->entityManager->getRepository(Course::class)->find(1);
        $lesson = $this->entityManager->getRepository(Lesson::class)->find(1);
        $breadcrumbs = $lesson_details->filter(".breadcrumb-item");
        self::assertSame(
            $course->getName(),
            $breadcrumbs->getNode(1)->textContent
        );
        self::assertSame(
            $lesson->getName(),
            $breadcrumbs->getNode(2)->textContent
        );

        self::assertSelectorTextContains('body > h1', $lesson->getName());
        self::assertSelectorTextContains('body > p', $lesson->getContent());

        // Forms
        self::assertSelectorExists("form[action='/lessons/1/edit']");
        self::assertSelectorExists("form[action='/lessons/1']");
    }

    public function testLessonOrder(): void
    {
        $course = $this->entityManager->getRepository(Course::class)->find(1);
        $lesson_tgt_list = $course->getLessons();

        $crawler = $this->client->request("GET", '/courses/1');
        self::assertResponseIsSuccessful();

        $lesson_list = $crawler->filter("section > div > div");
        self::assertCount(4, $lesson_list);

        for ($i = 0; $i < $lesson_list->count(); $i++) {
            self::assertStringContainsString(
                $lesson_tgt_list[$i]->getName(),
                $lesson_list->filter('h4')->getNode($i)->textContent
            );
            self::assertStringContainsString(
                $lesson_tgt_list[$i]->getContent(),
                $lesson_list->filter('p')->getNode($i)->textContent
            );
            self::assertSame(
                '/lessons/' . $lesson_tgt_list[$i]->getId(),
                $lesson_list->filter('a')->extract(['href'])[$i]
            );
        }
    }
}
