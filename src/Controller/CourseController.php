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
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
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
        CourseRepository     $courseRepository,
        BillingClient        $billingClient,
    ): Response {

        // Get courses price/license type
        $courses = $courseRepository->findAll();
        try {
            $coursesBilling = $billingClient->getCourseList();
            if ($user) {
                $activeCourses = $billingClient->getActiveCourses($user);
            }
        } catch (BillingException $e) {
            if ($e->getCode() >= 500) {
                $this->addFlash('error', "Billing-сервис временно недоступен");
            } else {
                throw $e;
            }
        }

        $courseObjects = [];
        foreach ($courses as $course) {
            $courseSymbolicName = $course->getSymbolicName();
            $courseObjects[$courseSymbolicName] = [];
            $courseObjects[$courseSymbolicName]['id'] = $course->getId();
            $courseObjects[$courseSymbolicName]['code'] = $courseSymbolicName;
            $courseObjects[$courseSymbolicName]['name'] = $course->getName();
            $courseObjects[$courseSymbolicName]['description'] = $course->getDescription();
            // to simplify twig
            $courseObjects[$courseSymbolicName]['is_active'] = false;
            $courseObjects[$courseSymbolicName]['type'] = 'free';
            $courseObjects[$courseSymbolicName]['price'] = 0;
        }

        foreach ($coursesBilling ?? [] as $course) {
            $courseObjects[$course['code']]['type'] = $course['type'];
            $courseObjects[$course['code']]['price'] = $course['price'] ?? 0;
        }

        foreach ($activeCourses ?? [] as $course) {
            $courseObjects[$course['code']]['is_active'] = true;
            if (isset($course['valid_until'])) {
                $courseObjects[$course['code']]['valid_until'] =
                    new \DateTime($course['valid_until'])->format('Y-m-d H:i:s');
            }
        }

        return $this->render('course/index.html.twig', ['courses' => $courseObjects]);
    }

    #[Route('/new', name: 'app_course_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        BillingClient $billingClient,
        #[CurrentUser] User $user,
    ): Response {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $type = (string) $form->get('type')->getData();
                if ($type === 'free') {
                    $price = 0;
                } else {
                    $price = (float) ($form->get('price')->getData() ?? 0);
                }

                $billingClient->createCourse($course, $type, $price, (string) $user->getApiToken());

                $entityManager->persist($course);
                $entityManager->flush();

                return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
            } catch (BillingException $e) {
                if ($e->getCode() >= 500) {
                    $this->addFlash('error', "Billing сервис временно недоступен");
                }
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('course/new.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_course_show', methods: ['GET'])]
    public function show(
        Course $course,
        #[CurrentUser] ?User $user,
        BillingClient $billingClient,
    ): Response
    {
        $courseInfo = [
            'type' => 'free',
            'price' => 0,
            'is_active' => false,
            'valid_until' => null,
        ];
        $balance = null;

        try {
            $billingCourse = $billingClient->getCourseInfo((string) $course->getSymbolicName());
            $courseInfo['type'] = $billingCourse['type'];
            $courseInfo['price'] = $billingCourse['price'] ?? 0;

            if ($user) {
                $freshUser = $billingClient->getCurrentUser((string) $user->getApiToken());
                $balance = $freshUser->getBalance();
                $user->setBalance($balance);

                // TODO сделать нормальный эндпоинт с фильтрацией
                foreach ($billingClient->getActiveCourses($user) as $activeCourse) {
                    if ($activeCourse['code'] !== $course->getSymbolicName()) {
                        continue;
                    }

                    $courseInfo['is_active'] = true;
                    if (isset($activeCourse['valid_until'])) {
                        $courseInfo['valid_until'] = new \DateTime($activeCourse['valid_until'])->format('Y-m-d H:i:s');
                    }
                    break;
                }
            }
        } catch (BillingException $e) {
            if ($e->getCode() >= 500) {
                $this->addFlash('error', 'Billing сервис недоступен');
            } else {
                throw $e;
            }
        }

        return $this->render('course/show.html.twig', [
            'course' => $course,
            'course_info' => $courseInfo,
            'balance' => $balance,
            'lessons' => $course->getLessons(),
        ]);
    }

    #[Route('/{id}/pay', name: 'app_course_pay', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function pay(
        Course $course,
        #[CurrentUser] User $user,
        Request $request,
        BillingClient $billingClient,
    ): Response {
        if (!$this->isCsrfTokenValid('pay_course'.$course->getId(), $request->getPayload()->getString('_csrf_token'))) {
            $this->addFlash('error', 'Некорректный csrf токен.');
            return $this->redirectToRoute('app_course_show', ['id' => $course->getId()]);
        }

        try {
            $billingClient->payCourse($user, $course);
            $this->addFlash('success', 'Курс успешно оплачен');
        } catch (BillingException $e) {
            if ($e->getCode() === 406) {
                $this->addFlash('error', 'Недостаточно средств');
            } elseif ($e->getCode() >= 500) {
                $this->addFlash('error', 'Billing сервис недоступен');
            } else {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_course_show', ['id' => $course->getId()]);
    }

    #[Route('/{id}/edit', name: 'app_course_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function edit(
        Request $request,
        Course $course,
        EntityManagerInterface $entityManager,
        BillingClient $billingClient,
        #[CurrentUser] User $user,
    ): Response {
        $currentCode = (string) $course->getSymbolicName();

        try {
            $billingCourse = $billingClient->getCourseInfo((string) $course->getSymbolicName());
            $courseType = $billingCourse['type'] ?? 'free';
            $price = (float) ($billingCourse['price'] ?? 0);
        } catch (BillingException $e) {
            if ($e->getCode() >= 500) {
                $this->addFlash('error', 'Billing сервис недоступен');
            }
            return $this->redirectToRoute('app_course_show', ['id' => $course->getId()]);
        }


        $form = $this->createForm(
            CourseType::class,
            $course,
            ['course_type' => $courseType, 'price' => $price]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $type = (string) $form->get('type')->getData();
                if ($type === 'free') {
                    $price = 0;
                } else {
                    $price = (float) ($form->get('price')->getData() ?? 0);
                }
                $billingClient->updateCourse($course, $type, $price, (string) $user->getApiToken(), $currentCode);

                $entityManager->flush();

                return $this->redirectToRoute('app_course_show', ['id' => $course->getId()], Response::HTTP_SEE_OTHER);
            } catch (BillingException $e) {
                if ($e->getCode() >= 500) {
                    $this->addFlash('error', "Billing сервис временно недоступен");
                }
                $form->addError(new FormError($e->getMessage()));
            }
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
        if ($this->isCsrfTokenValid('delete' . $course->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($course);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
    }
}
