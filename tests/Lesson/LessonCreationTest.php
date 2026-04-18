<?php

namespace App\Tests\Lesson;

use App\Entity\Course;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class LessonCreationTest extends WebTestCase
{
    public function testCreateLessonWithCourseId(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/lessons/new?course_id=1');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Создать')->form();
        $form['lesson[name]']='Тестовый урок';
        $form['lesson[content]']='Содержание тестового урока';
        $form['lesson[index]']='5';
        self::assertSame('1', $form['lesson[Course]']->getValue());

        $client->submit($form);

        // Есть подозрения что DAMA не откатывает id-счетчики PostgreSQL
        // В данном случае для проверки редиректа будет извлекаться id из respons-а
        // TODO: убрать привязку к точным id

        self::assertResponseRedirects();
        $redirectUrl = $client->getResponse()->headers->get('location');
        self::assertStringStartsWith('/lessons/', $redirectUrl);

        $client->followRedirect();
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
        $client = static::createClient();
        $crawler = $client->request('GET', '/lessons/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Создать')->form();
        $form['lesson[name]']='Тестовый урок';
        $form['lesson[content]']='Содержание тестового урока';
        $form['lesson[index]']='5';
        $form['lesson[Course]']='1';

        $client->submit($form);
        self::assertResponseRedirects();
        $redirectUrl = $client->getResponse()->headers->get('location');
        self::assertStringStartsWith('/lessons/', $redirectUrl);

        $client->followRedirect();
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
        $client = static::createClient();
        $crawler = $client->request('GET', '/lessons/new');

        $form = $crawler->selectButton('Создать')->form();
        $form['lesson[name]']='';
        $form['lesson[content]']='';
        $form['lesson[index]']='';

        $client->submit($form);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorExists("#lesson_name_error1");
        self::assertSelectorExists("#lesson_content_error1");
    }
}
