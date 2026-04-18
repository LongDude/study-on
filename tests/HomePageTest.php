<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomePageTest extends WebTestCase
{
    public function testRedirect(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        self::assertResponseRedirects("/courses");
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function test404Page(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/co');
        self::assertResponseStatusCodeSame(404);
    }

    public function testResponseSuccessful(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/courses');
        self::assertResponseIsSuccessful();
    }

    public function testHomePageContent(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/courses');

        $breadcrumb = $crawler->filter('li.breadcrumb-item');
        self::assertCount(2, $breadcrumb);
        $rootLink = $breadcrumb->eq(0)->filter('a');
        self::assertSame('StudyOn', $rootLink->text());
        self::assertSame('/courses', $rootLink->attr('href'));

        $courses = $crawler->filter('div.card');
        self::assertCount(5, $courses);
        self::assertSelectorTextContains('div.card h5.card-title', 'Основы веб-разработки');
        self::assertSelectorTextContains('div.card p.card-text', 'Изучите HTML, CSS и JavaScript с нуля. Курс для начинающих, который даёт полное понимание того, как работают современные веб-сайты.');
        self::assertSelectorExists('div.card a');

        $info_link = $courses->filter('a')->extract(['href']);
        self::assertCount(5, $info_link);
        self::assertContains('/courses/1', $info_link);
    }
}
