<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ForgotPasswordRequest;
use App\Dto\RegisterRequest;
use App\Dto\ResetPasswordRequest;
use App\Dto\UserView;
use App\Entity\User;
use App\Service\PasswordReset;
use App\Service\UserManager;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Login, refresh e logout são atendidos pelo firewall (config/packages/security.yaml).
 * Aqui ficam o cadastro público e o "quem sou eu".
 */
#[OA\Tag(name: 'Autenticação')]
final class AuthController extends AbstractController
{
    #[Route('/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload] RegisterRequest $payload,
        Request $request,
        UserManager $users,
        AuthenticationSuccessHandler $authenticationSuccess,
        #[Autowire(service: 'limiter.register')] RateLimiterFactory $registerLimiter,
    ): Response {
        $limit = $registerLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Muitos cadastros a partir deste endereço. Tente novamente em alguns minutos.');
        }

        $user = $users->register($payload->name, $payload->email, $payload->password);

        // Mesma resposta do login: o aluno já sai autenticado (token + cookie de refresh)
        $response = $authenticationSuccess->handleAuthenticationSuccess($user);
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    /**
     * "Esqueci minha senha": manda o link por e-mail. Responde 202 mesmo se o
     * e-mail não existir, para não revelar quem tem conta.
     */
    #[Route('/auth/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        #[MapRequestPayload] ForgotPasswordRequest $payload,
        Request $request,
        PasswordReset $passwordReset,
        #[Autowire(service: 'limiter.password_reset')] RateLimiterFactory $limiter,
    ): JsonResponse {
        $key = ($request->getClientIp() ?? 'unknown').'|'.mb_strtolower(trim($payload->email));
        $limit = $limiter->create($key)->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Muitos pedidos de troca de senha. Tente novamente em alguns minutos.');
        }

        $passwordReset->request($payload->email);

        return $this->json(
            ['message' => 'Se o e-mail estiver cadastrado, você vai receber um link para trocar a senha.'],
            Response::HTTP_ACCEPTED,
        );
    }

    /** Troca a senha com o link do e-mail e já entra (mesma resposta do login). */
    #[Route('/auth/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function resetPassword(
        #[MapRequestPayload] ResetPasswordRequest $payload,
        PasswordReset $passwordReset,
        AuthenticationSuccessHandler $authenticationSuccess,
    ): Response {
        $user = $passwordReset->reset($payload->token, $payload->password);

        return $authenticationSuccess->handleAuthenticationSuccess($user);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(UserView::from($user));
    }
}
