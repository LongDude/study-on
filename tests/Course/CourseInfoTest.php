<?php

namespace App\Tests\Course;

use App\Entity\Course;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CourseInfoTest extends WebTestCase
{
    public function testDirectRequest(): void
    {
        $client = static::createClient();
        $client->request('GET', '/courses/1');
        self::assertResponseStatusCodeSame(200);
    }

    public function testHomePageRedirect(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        $crawler = $client->followRedirect();

        $course_link = $crawler->filter("div.card")->first()->selectLink("Перейти к курсу")->link();
        $client->click($course_link);
        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains("Основы веб-разработки");
    }

    public function testContent(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/courses/1');
        self::assertResponseIsSuccessful();

        # Breadcrumb structure
        $breadcrumb = $crawler->filter('li.breadcrumb-item');
        self::assertCount(2, $breadcrumb);
        $rootLink = $breadcrumb->eq(0)->filter('a');
        self::assertSame('StudyOn', $rootLink->text());
        self::assertSame('/courses', $rootLink->attr('href'));
        self::assertSame('Основы веб-разработки', $breadcrumb->eq(1)->text());

        # Info
        self::assertSelectorTextSame('body main h1', "Основы веб-разработки");
        self::assertSelectorTextSame('body main p', "Изучите HTML, CSS и JavaScript с нуля. Курс для начинающих, который даёт полное понимание того, как работают современные веб-сайты.");

        # Lessons
        $lessons = $crawler->filter("section div div");
        self::assertCount(4, $lessons);
        $lesson = $lessons->first();
        self::assertSame("1. Введение в веб-технологии", $lesson->filter('h4')->text());
        self::assertSame("/lessons/1", $lesson->filter('a')->attr('href'));
        self::assertSame("Как работает интернет, клиент-серверная архитектура, роль браузера. Обзор инструментов разработчика.", $lesson->filter("p")->text());

        # Buttons
        self::assertAnySelectorTextContains("main > div.container-fluid > a.btn", "Добавить урок");
        self::assertAnySelectorTextContains("main > div.container-fluid > a.btn", "Редактировать курс");
        self::assertAnySelectorTextContains("main > div.container-fluid  button.btn", "Удалить курс");
    }
}
