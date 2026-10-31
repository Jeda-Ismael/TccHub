<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\DomainRuleViolation;
use App\Exception\TransitionNotAllowed;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Todo erro sob /api vira JSON no formato que o frontend já trata:
 * { "statusCode": 422, "message": "..." } — ou message como lista, na validação.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -10)]
final class ApiExceptionListener
{
    public function __construct(private readonly bool $debug = false)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();

        // Duas ações simultâneas sobre o mesmo registro (ex.: dois envios de PDF)
        if ($exception instanceof UniqueConstraintViolationException) {
            $event->setResponse($this->json(Response::HTTP_CONFLICT, 'Outra alteração foi feita ao mesmo tempo. Atualize a página e tente de novo.'));

            return;
        }

        if ($exception instanceof TransitionNotAllowed) {
            $event->setResponse($this->json(Response::HTTP_CONFLICT, $exception->getMessage()));

            return;
        }

        if ($exception instanceof DomainRuleViolation) {
            $event->setResponse($this->json(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getMessage()));

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $previous = $exception->getPrevious();

            // #[MapRequestPayload] falhou na validação: devolve a lista de mensagens
            if ($previous instanceof ValidationFailedException) {
                $messages = array_map(
                    static fn (ConstraintViolationInterface $v): string => (string) $v->getMessage(),
                    iterator_to_array($previous->getViolations()),
                );
                $event->setResponse($this->json(Response::HTTP_UNPROCESSABLE_ENTITY, array_values(array_unique($messages))));

                return;
            }

            $message = match ($status) {
                // Mensagem própria quando o código explicou o motivo; senão, a genérica
                Response::HTTP_FORBIDDEN => \in_array($exception->getMessage(), ['', 'Access Denied.'], true)
                    ? 'Você não tem permissão para executar esta ação.'
                    : $exception->getMessage(),
                // A mensagem padrão do Symfony expõe nomes de classe e rota
                Response::HTTP_NOT_FOUND => 'Recurso não encontrado.',
                default => '' !== $exception->getMessage() ? $exception->getMessage() : (Response::$statusTexts[$status] ?? 'Erro'),
            };
            $response = $this->json($status, $message);
            $response->headers->add($exception->getHeaders());
            $event->setResponse($response);

            return;
        }

        if (!$this->debug) {
            $event->setResponse($this->json(Response::HTTP_INTERNAL_SERVER_ERROR, 'Erro interno. Tente novamente em instantes.'));
        }
    }

    /** @param string|list<string> $message */
    private function json(int $status, string|array $message): JsonResponse
    {
        return new JsonResponse(['statusCode' => $status, 'message' => $message], $status);
    }
}
