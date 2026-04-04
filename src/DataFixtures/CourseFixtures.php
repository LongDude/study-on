<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Курс 1. Основы веб-разработки
        $course1 = new Course();
        $course1->setName("Основы веб-разработки");
        $course1->setSymbolicName("web-development-basics");
        $course1->setDescription(
            "Изучите HTML, CSS и JavaScript с нуля. Курс для начинающих, который даёт полное понимание того, как работают современные веб-сайты."
        );
        $manager->persist($course1);

        $c1_les1 = new Lesson();
        $c1_les1
            ->setName("Введение в веб-технологии")
            ->setContent(
                "Как работает интернет, клиент-серверная архитектура, роль браузера. Обзор инструментов разработчика."
            )
            ->setIndex(1);
        $manager->persist($c1_les1);
        $course1->addLesson($c1_les1);

        $c1_les2 = new Lesson();
        $c1_les2
            ->setName("HTML: структура страницы")
            ->setContent(
                "Основные теги, семантическая вёрстка, формы, таблицы. Создание первой HTML-страницы."
            )
            ->setIndex(2);
        $manager->persist($c1_les2);
        $course1->addLesson($c1_les2);

        $c1_les3 = new Lesson();
        $c1_les3
            ->setName("CSS: стилизация и макеты")
            ->setContent("Селекторы, цвета, шрифты, Flexbox, Grid. Адаптивный дизайн и медиа-запросы.")
            ->setIndex(3);
        $manager->persist($c1_les3);
        $course1->addLesson($c1_les3);

        $c1_les4 = new Lesson();
        $c1_les4
            ->setName("JavaScript: основы программирования")
            ->setContent("Переменные, функции, события, работа с DOM. Примеры интерактивных элементов на странице.")
            ->setIndex(4);
        $manager->persist($c1_les4);
        $course1->addLesson($c1_les4);

        // Курс 2: Python для анализа данных
        $course2 = new Course();
        $course2->setName("Python для анализа данных");
        $course2->setSymbolicName("python-for-data-science");
        $course2->setDescription(
            "Курс по использованию Python для обработки и визуализации данных. Библиотеки Pandas, NumPy, Matplotlib."
        );
        $manager->persist($course2);

        $c2_les1 = new Lesson();
        $c2_les1
            ->setName("Введение в Python")
            ->setContent("Типы данных, циклы, функции, списки и словари. Основы синтаксиса Python.")
            ->setIndex(1);
        $manager->persist($c2_les1);
        $course2->addLesson($c2_les1);

        $c2_les2 = new Lesson();
        $c2_les2
            ->setName("NumPy: численные вычисления")
            ->setContent("Массивы, матричные операции, статистические функции. Работа с многомерными данными.")
            ->setIndex(2);
        $manager->persist($c2_les2);
        $course2->addLesson($c2_les2);

        $c2_les3 = new Lesson();
        $c2_les3
            ->setName("Pandas: работа с таблицами")
            ->setContent("DataFrame, чтение CSV/Excel, фильтрация, группировка, объединение данных.")
            ->setIndex(3);
        $manager->persist($c2_les3);
        $course2->addLesson($c2_les3);

        $c2_les4 = new Lesson();
        $c2_les4
            ->setName("Визуализация с Matplotlib и Seaborn")
            ->setContent("Построение графиков, гистограмм, тепловых карт. Настройка стилей и анимаций.")
            ->setIndex(4);
        $manager->persist($c2_les4);
        $course2->addLesson($c2_les4);

        $c2_les5 = new Lesson();
        $c2_les5
            ->setName("Финальный проект: анализ реального набора данных")
            ->setContent("Очистка данных, исследовательский анализ, создание отчёта с графиками.")
            ->setIndex(5);
        $manager->persist($c2_les5);
        $course2->addLesson($c2_les5);

        // Курс 3: Symfony: от новичка до профи
        $course3 = new Course();
        $course3->setName("Symfony: от новичка до профи");
        $course3->setSymbolicName("symfony-framework-mastery");
        $course3->setDescription(
            "Полный курс по фреймворку Symfony 6/7: маршрутизация, Doctrine, формы, безопасность, тестирование."
        );
        $manager->persist($course3);

        $c3_les1 = new Lesson();
        $c3_les1
            ->setName("Установка и структура Symfony")
            ->setContent("Композитор, консоль Symfony, структура каталогов, переменные окружения.")
            ->setIndex(1);
        $manager->persist($c3_les1);
        $course3->addLesson($c3_les1);

        $c3_les2 = new Lesson();
        $c3_les2
            ->setName("Маршрутизация и контроллеры")
            ->setContent("Аннотации/атрибуты, параметры маршрутов, генерация URL, подстановки.")
            ->setIndex(2);
        $manager->persist($c3_les2);
        $course3->addLesson($c3_les2);

        $c3_les3 = new Lesson();
        $c3_les3
            ->setName("Doctrine ORM: работа с базой данных")
            ->setContent("Сущности, миграции, репозитории, связи (OneToMany, ManyToMany), QueryBuilder.")
            ->setIndex(3);
        $manager->persist($c3_les3);
        $course3->addLesson($c3_les3);

        $c3_les4 = new Lesson();
        $c3_les4
            ->setName("Формы и валидация")
            ->setContent("Создание форм, типы полей, CSRF-защита, кастомная валидация данных.")
            ->setIndex(4);
        $manager->persist($c3_les4);
        $course3->addLesson($c3_les4);

        // Курс 4: Проектирование и оптимизация SQL баз данных
        $course4 = new Course();
        $course4->setName("Проектирование и оптимизация SQL баз данных");
        $course4->setSymbolicName("sql-database-design");
        $course4->setDescription(
            "Нормализация, индексы, сложные запросы, транзакции. PostgreSQL и MySQL."
        );
        $manager->persist($course4);

        $c4_les1 = new Lesson();
        $c4_les1
            ->setName("Основы реляционных баз данных")
            ->setContent("Первичные ключи, внешние ключи, типы данных. Создание первой таблицы.")
            ->setIndex(1);
        $manager->persist($c4_les1);
        $course4->addLesson($c4_les1);

        $c4_les2 = new Lesson();
        $c4_les2
            ->setName("SELECT, JOIN, подзапросы")
            ->setContent("Внутренние и внешние соединения, агрегатные функции, GROUP BY, HAVING.")
            ->setIndex(2);
        $manager->persist($c4_les2);
        $course4->addLesson($c4_les2);

        $c4_les3 = new Lesson();
        $c4_les3
            ->setName("Нормализация: 1НФ, 2НФ, 3НФ")
            ->setContent("Устранение дублирования, разбиение таблиц, примеры плохих и хороших схем.")
            ->setIndex(3);
        $manager->persist($c4_les3);
        $course4->addLesson($c4_les3);

        $c4_les4 = new Lesson();
        $c4_les4
            ->setName("Индексы и оптимизация запросов")
            ->setContent("B-tree индексы, покрывающие индексы, анализ плана запроса (EXPLAIN).")
            ->setIndex(4);
        $manager->persist($c4_les4);
        $course4->addLesson($c4_les4);

        // Курс 5: Docker для разработчиков
        $course5 = new Course();
        $course5->setName("Docker для разработчиков");
        $course5->setSymbolicName("docker-for-developers");
        $course5->setDescription(
            "Контейнеризация, Dockerfile, docker-compose, работа с многоконтейнерными приложениями."
        );
        $manager->persist($course5);

        $c5_les1 = new Lesson();
        $c5_les1
            ->setName("Что такое контейнеры и зачем нужен Docker")
            ->setContent("Сравнение с виртуальными машинами, установка Docker, основные команды.")
            ->setIndex(1);
        $manager->persist($c5_les1);
        $course5->addLesson($c5_les1);

        $c5_les2 = new Lesson();
        $c5_les2
            ->setName("Создание Dockerfile")
            ->setContent("Инструкции FROM, RUN, COPY, CMD. Многоэтапная сборка. Пример с Node.js приложением.")
            ->setIndex(2);
        $manager->persist($c5_les2);
        $course5->addLesson($c5_les2);

        $c5_les3 = new Lesson();
        $c5_les3
            ->setName("Docker Compose: оркестрация сервисов")
            ->setContent("Файл docker-compose.yml, сети, тома, переменные окружения. Запуск LEMP стека.")
            ->setIndex(3);
        $manager->persist($c5_les3);
        $course5->addLesson($c5_les3);

        $c5_les4 = new Lesson();
        $c5_les4
            ->setName("Работа с реестрами: Docker Hub и приватные registry")
            ->setContent("Push/pull образов, тегирование, автоматические сборки.")
            ->setIndex(4);
        $manager->persist($c5_les4);
        $course5->addLesson($c5_les4);

        $c5_les5 = new Lesson();
        $c5_les5
            ->setName("Практика: контейнеризация Symfony приложения")
            ->setContent("Настройка PHP-FPM, Nginx, PostgreSQL в контейнерах, использование volumes для кода.")
            ->setIndex(5);
        $manager->persist($c5_les5);
        $course5->addLesson($c5_les5);

        $manager->flush();
    }
}
