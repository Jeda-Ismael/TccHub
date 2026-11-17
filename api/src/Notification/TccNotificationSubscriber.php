<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\BoardMember;
use App\Entity\Tcc;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\Role;
use App\Enum\TccStatus;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Avisos gerados pelas mudanças de status do TCC (docs/ARQUITETURA.md §9).
 * Roda depois do histórico, dentro da mesma transação da ação.
 */
final class TccNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly UserRepository $users,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return ['workflow.tcc.completed' => ['onCompleted', -10]];
    }

    public function onCompleted(CompletedEvent $event): void
    {
        $tcc = $event->getSubject();
        $transition = $event->getTransition();
        if (!$tcc instanceof Tcc || null === $transition) {
            return;
        }

        $comment = $event->getContext()['comment'] ?? null;
        $comment = \is_string($comment) && '' !== trim($comment) ? trim($comment) : null;
        $title = $tcc->getTitle();
        $student = $tcc->getStudent();
        $advisor = $tcc->getAdvisor();

        match (TccStatus::from($transition->getTos()[0])) {
            TccStatus::Submitted => $this->send(NotificationType::TccSubmitted, $tcc, [$advisor],
                'TCC enviado para revisão',
                \sprintf('%s enviou "%s" para a sua revisão.', $student->getName(), $title)),
            TccStatus::Resubmitted => $this->send(NotificationType::TccSubmitted, $tcc, [$advisor],
                'Nova versão do TCC',
                \sprintf('%s enviou a versão corrigida de "%s".', $student->getName(), $title)),
            TccStatus::SentToEvaluation => $this->sentToEvaluation($tcc),
            TccStatus::CorrectionsRequested => $this->send(NotificationType::CorrectionsRequested, $tcc, [$student],
                'Correções pedidas no seu TCC',
                'board_request_corrections' === $transition->getName() || null === $comment
                    ? 'A banca terminou a avaliação e pediu correções. Veja os pareceres, envie a versão corrigida do PDF e reenvie o TCC.'
                    : 'Comentário do orientador: '.$comment),
            TccStatus::AdvisorFinalReview => $this->send(NotificationType::FinalReviewPending, $tcc, [$advisor],
                'Revisão final pendente',
                \sprintf('A banca aprovou "%s". Falta a sua revisão final.', $title)),
            TccStatus::AwaitingDefense => $this->awaitingDefense($tcc, 'allow_new_defense' === $transition->getName()),
            // Tarefa nova: avisa também o coordenador que estava na banca e deu a última nota
            TccStatus::AwaitingDecision => $this->notifier->notify(NotificationType::DecisionPending, $tcc, $this->users->findByRole(Role::Coordination, onlyActive: true),
                'Decisão pendente sobre uma defesa',
                trim(\sprintf('"%s" (%s) ficou abaixo da nota mínima. %s', $title, $student->getName(), $comment ?? '')),
                skipActor: false),
            TccStatus::Approved => $this->send(NotificationType::TccApproved, $tcc, [$student, $advisor],
                'TCC aprovado',
                \sprintf('"%s" foi aprovado com média final %s.', $title, str_replace('.', ',', (string) $tcc->getFinalGrade()))),
            TccStatus::Rejected => $this->send(NotificationType::TccRejected, $tcc, [$student, $advisor],
                'TCC reprovado',
                \sprintf('"%s" foi reprovado.%s', $title, null === $comment ? '' : ' Motivo: '.$comment)),
            default => null,
        };
    }

    private function sentToEvaluation(Tcc $tcc): void
    {
        $this->send(NotificationType::EvaluationOpen, $tcc, $this->board($tcc),
            'TCC aguardando o seu parecer',
            \sprintf('"%s", de %s, foi liberado para a banca.', $tcc->getTitle(), $tcc->getStudent()->getName()));
        $this->send(NotificationType::EvaluationOpen, $tcc, [$tcc->getStudent()],
            'Seu TCC foi para a banca',
            'O orientador aprovou e a banca já pode avaliar. Os pareceres aparecem quando todos terminarem.');
    }

    private function awaitingDefense(Tcc $tcc, bool $newAttempt): void
    {
        if ($newAttempt) {
            $this->send(NotificationType::DefensePending, $tcc, [$tcc->getStudent(), $tcc->getAdvisor()],
                'Nova defesa liberada',
                \sprintf('A coordenação liberou uma nova defesa para "%s". Aguarde o agendamento.', $tcc->getTitle()));

            return;
        }

        // O orientador não é avisado: foi ele quem aprovou (e a tela dele já pede o agendamento)
        $this->send(NotificationType::DefensePending, $tcc, [$tcc->getStudent()],
            'Aprovado para a defesa',
            'Seu TCC foi aprovado para a defesa. A data aparece aqui quando for agendada.');
    }

    /** @return list<User> */
    private function board(Tcc $tcc): array
    {
        return array_values($tcc->getBoardMembers()->map(static fn (BoardMember $m): User => $m->getEvaluator())->toArray());
    }

    /** @param iterable<User|null> $recipients */
    private function send(NotificationType $type, Tcc $tcc, iterable $recipients, string $title, string $body): void
    {
        $this->notifier->notify($type, $tcc, $recipients, $title, trim($body));
    }
}
