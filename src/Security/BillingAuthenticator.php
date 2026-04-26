<?php

namespace App\Security;

use App\Exception\BillingException;
use App\Service\BillingClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class BillingAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const string LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly UrlGeneratorInterface  $urlGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly BillingClient $billingClient)
    { }

    public function authenticate(Request $request): Passport
    {
        $email = $request->getPayload()->getString('email');
        if ('' === $email) {
            throw new CustomUserMessageAuthenticationException('Почта не должен быть пустым.');
        }

        $password = $request->getPayload()->getString('password');
        if ('' === $password) {
            throw new CustomUserMessageAuthenticationException('Почта не должен быть пустым.');
        }
        try {
            $token = $this->billingClient->authenticate($email, $password);
        } catch (BillingException $e) {
            if ($e->getCode() >= 500) {
                throw new CustomUserMessageAuthenticationException("Сервис временно недоступен. Попробуйте авторизоваться позже");
            }
            if ($e->getCode() >= 400) {
                throw new CustomUserMessageAuthenticationException($e->getMessage());
            }
            throw new CustomUserMessageAuthenticationException("Неизвестная ошибка. Повторите попытку позже");
        }

        return new SelfValidatingPassport(
            new UserBadge($token),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }
        return new RedirectResponse($this->urlGenerator->generate('app_course_index'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
