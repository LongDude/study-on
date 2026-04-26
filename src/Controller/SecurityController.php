<?php

namespace App\Controller;

use App\Exception\BillingException;
use App\Form\RegistrationType;
use App\Security\BillingAuthenticator;
use App\Security\User;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use function PHPUnit\Framework\isArray;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/profile', name: 'app_profile')]
    #[IsGranted('ROLE_USER')]
    public function profile(#[CurrentUser] $user, BillingClient $billingClient, UrlGeneratorInterface  $urlGenerator): Response {

        try {
            $freshProfile = $billingClient->getCurrentUser($user->getApiToken());
            $user->setBalance($freshProfile->getBalance());

            return $this->render('security/profile.html.twig', [
                'email' => $freshProfile->getEmail(),
                'balance' => $freshProfile->getBalance(),
            ]);
        } catch (BillingException $e) {
            if ($e->getCode() >= 400) {
                return $this->redirectToRoute('app_login', status: 401);
            }
            return new Response('Сервис временно недоступен. Повторите попытку позже', 500);
        }
    }

    #[Route(path: '/register', name: 'app_register')]
    public function registerUser(
        Request $request,
        UserAuthenticatorInterface $userAuthenticator,
        BillingAuthenticator $billingAuthenticator,
        BillingClient $billingClient,
    ): Response {

        if ($this->getUser()) {
            $this->container->get('security.token_storage')->setToken(null);
            $this->container->get('session')->invalidate();
        }

        $form = $this->createForm(RegistrationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $email = $data['email'];
            $password = $data['password'];

            try {
                $userToken = $billingClient->register($email, $password);

                $user = new User();
                $user->setEmail($email);
                $user->setApiToken($userToken);
                $this->addFlash('success', 'Регистрация прошла успешно');
                return $userAuthenticator->authenticateUser($user, $billingAuthenticator, $request);
            } catch (BillingException $e) {
                $errors = $e->getErrors();
                if ($errors && is_array($errors)) {
                    foreach ($errors as $field => $fieldErrors) {
                        if (is_array($fieldErrors)) {
                            foreach ($fieldErrors as $err) {
                                $this->addFlash('error', sprintf("Ошибка в поле %s: %s", $field, $err));
                            }
                        } else {
                            $this->addFlash('error', $fieldErrors);
                        }
                    }
                } else {
                    $this->addFlash('error', "Ошибка регистрации: " . $e->getMessage());
                }

                return $this->render('security/_register.html.twig', ['form' => $form->createView()]);
            } catch (\Exception $e) {
                $this->addFlash('error', "Ошибка регистрации. Попробуйте позже.");
                return $this->render('security/_register.html.twig', ['form' => $form->createView()]);
            }
        }
        return $this->render('security/_register.html.twig', ['form' => $form]);
    }
}
