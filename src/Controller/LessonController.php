<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Exception\BillingException;
use App\Form\LessonType;
use App\Repository\LessonRepository;
use App\Service\BillingClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/lessons')]
final class LessonController extends AbstractController
{
    #[Route('/new', name: 'app_lesson_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $lesson = new Lesson();

        // Поиск курса, с которого совершили редирект на создание
        $source_course = $request->query->get('course_id', null);
        if ($source_course !== null && $lesson->getCourse() === null) {
            $course = $entityManager->getRepository(Course::class)->find($source_course);
            if ($course !== null) {
                $lesson->setCourse($course);
                $lesson->setIndex($course->getLessons()->count() + 1);
            } else {
                $lesson->setIndex(1);
            }
        }

        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($lesson);
            $entityManager->flush();

            return $this->redirectToRoute('app_lesson_show', ['id' => $lesson->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('lesson/new.html.twig', [
            'lesson' => $lesson,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_lesson_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(
        #[CurrentUser] $user,
        BillingClient  $billingClient,
        Lesson         $lesson,
    ): Response {

        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            // override checkup for admin
            return $this->render('lesson/show.html.twig', [
                'lesson' => $lesson,
            ]);
        }

        // TODO сделать нормальный эндпоинт с фильтрацией
        $course = $lesson->getCourse();
        $course_paid = false;
        try {
            $billingClient->getActiveCourses($user);
            foreach ($billingClient->getActiveCourses($user) as $activeCourse) {
                if ($activeCourse['code'] !== $course->getSymbolicName()) {
                    continue;
                }
                $course_paid = true;
                break;
            }
        } catch (BillingException $e) {
            if ($e->getCode() >= 500) {
                $this->addFlash('error', 'Billing сервис недоступен');
            } else {
                throw $e;
            }
        }

        if ($course_paid) {
            return $this->render('lesson/show.html.twig', [
                'lesson' => $lesson,
            ]);
        }
        throw $this->createAccessDeniedException();
    }

    #[Route('/{id}/edit', name: 'app_lesson_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function edit(Request $request, Lesson $lesson, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_lesson_show', ['id' => $lesson->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('lesson/edit.html.twig', [
            'lesson' => $lesson,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_lesson_delete', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function delete(Request $request, Lesson $lesson, EntityManagerInterface $entityManager): Response
    {
        $course_id = $lesson->getCourse()->getId();
        if ($this->isCsrfTokenValid('delete' . $lesson->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($lesson);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_course_show', ['id' => $course_id], Response::HTTP_SEE_OTHER);
    }
}
