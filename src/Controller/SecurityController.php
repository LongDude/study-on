<?php

namespace App\Controller;

use App\Exception\BillingException;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

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
                'role' => $this->isGranted('USER_ADMIN') ? 'Администратор' : 'Пользователь'
            ]);
        } catch (BillingException $e) {
            if ($e->getCode() >= 400) {
                return $this->redirectToRoute('app_login', status: 401);
            }
            return new Response('Сервис временно недоступен. Повторите попытку позже', 500);
        }
    }
}
