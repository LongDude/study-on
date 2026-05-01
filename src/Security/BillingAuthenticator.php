<?php

namespace App\Security;

use App\Exception\BillingException;
use App\Service\BillingClient;
use App\Service\TokenDecoderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\HttpClient\ResponseInterface;

class BillingAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const string LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly UrlGeneratorInterface  $urlGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly BillingClient          $billingClient,
        private readonly TokenDecoderService    $tokenDecoderService,
        private readonly RequestStack $requestStack,
    ) {
    }

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
        $userIdentifier = null;
        try {
            // {'token'=>'...', 'refresh_token'=>'...'}
            $tokens = $this->billingClient->authenticate($email, $password);
            $request->attributes->set('refresh_token', $tokens['refresh_token']);
            $userIdentifier = $this->tokenDecoderService->decodeApiToken($tokens['token'])['username'];
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
            new UserBadge($userIdentifier, function () use ($tokens) {
                try {
                    $usr = $this->billingClient->getCurrentUser($tokens['token']);
                    $usr->setApiToken($tokens['token']);
                    $usr->setRefreshToken($tokens['refresh_token']);
                    return $usr;
                } catch (BillingException $e) {
                    if ($e->getCode() >= 500) {
                        throw new UnsupportedUserException(
                            "Сервис недоступен, повторите попытку позже"
                        );
                    }
                    throw new UserNotFoundException("Пользователь не найден");
                }
            }),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $refreshToken = $request->attributes->get('refresh_token');
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            $url = $targetPath;
        } else {
            $url = $this->urlGenerator->generate('app_course_index');
        }
        $resp = new RedirectResponse($url);
        $resp->headers->setCookie(
            Cookie::create(
                'JWT_REFRESH_TOKEN',
                $refreshToken,
                expire: new \DateTimeImmutable('+ 30 days') // Глобальная настройка lifetime не работает
            )
        );

        return $resp;
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
