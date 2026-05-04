<?php

namespace App\Controller;

use App\Entity\Course;
use App\Exception\BillingException;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use App\Security\User;
use App\Service\BillingClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/courses')]
final class CourseController extends AbstractController
{
    #[Route(name: 'app_course_index', methods: ['GET'])]
    public function index(
        #[CurrentUser] ?User $user,
        CourseRepository $courseRepository,
        BillingClient $billingClient
    ): Response {
        $courses = $courseRepository->findAll();

        // Get courses price/license type
        try {
            $coursesBilling = $billingClient->getCourseList();
        } catch (BillingException $e) {
            $this->addFlash('error', $e->getMessage());
            $coursesBilling = [];
        }


        $billingCourseMap = [];
        foreach ($coursesBilling as $billingCourse) {
            $billingCourseMap[$billingCourse['code']] = $billingCourse;
        }
        if ($user) {
            // Get user paid courses
            $activeCourses = $billingClient->getActiveCourses($user);
            foreach ($activeCourses as $activeCourse) {
                $billingCourseMap[$activeCourse['code']]['is_active'] = true;
                if (isset($activeCourse['valid_until'])) {
                    $billingCourseMap[$activeCourse['code']]['valid_until'] = $activeCourse['valid_until'];
                }
            }
        }
        $syncedCourses = [];
        foreach ($courses as $course) {
            $symbolicName = $course->getSymbolicName();
            if (isset($billingCourseMap[$symbolicName])) {
                $billingCourse = [
                    'id' => $course->getId(),
                    'name' => $course->getName(),
                    'description' => $course->getDescription(),
                    'type' => $billingCourseMap[$symbolicName]['type']
                ];
                if (isset($billingCourseMap[$symbolicName]['price'])) {
                    $billingCourse['price'] = $billingCourseMap[$symbolicName]['price'];
                }
                if (isset($billingCourseMap[$symbolicName]['is_active'])) {
                    $billingCourse['is_active'] = true;
                }
                if (isset($billingCourseMap[$symbolicName]['valid_until'])) {
                    $billingCourse['valid_until'] =
                        new \DateTime($billingCourseMap[$symbolicName]['valid_until'])
                            ->format('Y-m-d H:i:s');
                }

                $syncedCourses[] = $billingCourse;
            }
        }


        return $this->render('course/index.html.twig', ['courses' => $syncedCourses]);
    }

    #[Route('/new', name: 'app_course_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($course);
            $entityManager->flush();

            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/new.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_course_show', methods: ['GET'])]
    public function show(Course $course): Response
    {
        return $this->render('course/show.html.twig', [
            'course' => $course,
            'lessons' => $course->getLessons(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_course_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function edit(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_course_show', ['id' => $course->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/edit.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_course_delete', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function delete(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$course->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($course);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
    }
}
