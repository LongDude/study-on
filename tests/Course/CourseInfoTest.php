<?php

namespace App\Tests\Course;

use App\Entity\Course;
use App\Repository\CourseRepository;
use App\Security\User;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class CourseInfoTest extends WebTestCase
{
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
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testHomePageRedirect(): void
    {
        $this->client->request('GET', '/');
        $crawler = $this->client->followRedirect();

        $course_link = $crawler->filter("div.card")->first()->selectLink("Перейти к курсу")->link();
        $this->client->click($course_link);
        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains("Основы веб-разработки");
    }

    public function testUserContent(): void
    {
        # Application test
        # Главная страница
        $crawler = $this->client->request('GET', '/courses');
        self::assertResponseIsSuccessful();

        # Search and follow specific course
        $tgtCourseLink = $crawler
            ->filter("div.card")
            ->reduce(function (Crawler $node) {
                return $node->filter("h5.card-title")->text() === "Основы веб-разработки";
            })->selectLink("Перейти к курсу")->link();
        $crawler = $this->client->click($tgtCourseLink);

        self::assertResponseIsSuccessful();

        # Check breadcrumb structure
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
        self::assertSame("Как работает интернет, клиент-серверная архитектура, роль браузера. Обзор инструментов разработчика.", $lesson->filter("p")->text());

        # Buttons
        $buttonGroup = $crawler->filter("main > div.container-fluid");
        self::assertNotNull($buttonGroup->selectLink("Добавить урок"));
        self::assertNotNull($buttonGroup->selectLink("Редактировать курс"));
        self::assertNotNull($buttonGroup->selectButton("Удалить курс"));
    }
}
