<?php

declare(strict_types=1);

namespace App\Security;

use App\Dto\UserView;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * Ajusta o JWT e as respostas de autenticação ao formato que o frontend usa:
 * sucesso → { "accessToken": "...", "user": { id, name, email, roles } };
 * falha   → { "statusCode": 401, "message": "..." } em português.
 */
final class JwtSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_CREATED => 'onJwtCreated',
            // Prioridade baixa: roda depois do listener do refresh token, que também mexe na resposta
            Events::AUTHENTICATION_SUCCESS => ['onAuthenticationSuccess', -100],
            Events::AUTHENTICATION_FAILURE => 'onAuthenticationFailure',
            Events::JWT_NOT_FOUND => 'onJwtNotFound',
            Events::JWT_INVALID => 'onJwtInvalid',
            Events::JWT_EXPIRED => 'onJwtExpired',
        ];
    }

    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $view = UserView::from($user);
        $payload = $event->getData();
        $payload['sub'] = $view['id'];
        $payload['name'] = $view['name'];
        $payload['roles'] = $view['roles'];
        $event->setData($payload);
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $data = $event->getData();
        $token = $data['token'] ?? null;
        unset($data['token']);

        $event->setData(['accessToken' => $token, 'user' => UserView::from($user)] + $data);
    }

    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $message = $event->getException() instanceof TooManyLoginAttemptsAuthenticationException
            ? 'Muitas tentativas de login. Aguarde um minuto e tente novamente.'
            : 'E-mail ou senha incorretos.';

        $event->setResponse($this->unauthorized($message));
    }

    public function onJwtNotFound(JWTNotFoundEvent $event): void
    {
        $event->setResponse($this->unauthorized('Faça login para continuar.'));
    }

    public function onJwtInvalid(JWTInvalidEvent $event): void
    {
        $event->setResponse($this->unauthorized('Sessão inválida. Faça login novamente.'));
    }

    public function onJwtExpired(JWTExpiredEvent $event): void
    {
        $event->setResponse($this->unauthorized('Sessão expirada.'));
    }

    private function unauthorized(string $message): JsonResponse
    {
        return new JsonResponse(['statusCode' => Response::HTTP_UNAUTHORIZED, 'message' => $message], Response::HTTP_UNAUTHORIZED);
    }
}
