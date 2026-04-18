<?php

namespace App\Tests\Lesson;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LessonEditTest extends WebTestCase
{
    public function testEditLessons(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/courses/1');
        self::assertResponseIsSuccessful();

        $first_lesson = $crawler->filter('section > div > div')->first();
        $link = $first_lesson->filter('a')->link();
        $crawler = $client->click($link);
        self::assertResponseIsSuccessful();

        $lesson_location = $client->getResponse()->headers->get('Location');
        $edit_form = $crawler->selectButton('Редактировать')->form();
        $crawler = $client->submit($edit_form);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Обновить')->form();
        $form['lesson[name]'] = 'Измененный урок';
        $form['lesson[content]'] = "Новое содержание";
        $form['lesson[index]'] = '42';
        $crawler = $client->submit($form);

        self::assertResponseRedirects($lesson_location);
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('body > h1', "42. Измененный урок");
        self::assertSelectorTextContains('body > p', 'Новое содержание');
    }
}
