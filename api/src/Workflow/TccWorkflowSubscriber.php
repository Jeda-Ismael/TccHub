<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\Tcc;
use App\Entity\User;
use App\Enum\TccStatus;
use App\Repository\SettingsRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * Regras de negócio do ciclo do TCC:
 * - guards: condições para cada transição (com mensagem para o usuário);
 * - completed: grava o histórico de status;
 * - entered: efeitos ao entrar em um status.
 */
final class TccWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.tcc.guard.submit' => 'guardReadyToSubmit',
            'workflow.tcc.guard.resubmit' => 'guardReadyToSubmit',
            'workflow.tcc.guard.advisor_approve' => 'guardBoardSize',
            'workflow.tcc.completed' => 'recordHistory',
            'workflow.tcc.entered.SENT_TO_EVALUATION' => 'startEvaluationRound',
            'workflow.tcc.entered.AWAITING_DEFENSE' => 'openDefense',
        ];
    }

    public function guardReadyToSubmit(GuardEvent $event): void
    {
        $tcc = $this->tcc($event);
        if (null === $tcc->getCurrentDocument()) {
            $event->addTransitionBlocker(new TransitionBlocker('Envie o PDF do TCC antes de submeter.', 'no_document'));
        }
        if (null === $tcc->getAdvisor()) {
            $event->addTransitionBlocker(new TransitionBlocker('Escolha um orientador antes de submeter.', 'no_advisor'));
        }
    }

    public function guardBoardSize(GuardEvent $event): void
    {
        $settings = $this->settings->get();
        $size = $this->tcc($event)->getBoardMembers()->count();

        if (!$settings->acceptsBoardSize($size)) {
            $event->addTransitionBlocker(new TransitionBlocker(
                \sprintf(
                    'A banca precisa ter de %d a %d avaliadores para seguir para avaliação (hoje tem %d). Peça à coordenação para montá-la.',
                    $settings->getBoardMinSize(),
                    $settings->getBoardMaxSize(),
                    $size,
                ),
                'board_size',
            ));
        }
    }

    public function recordHistory(CompletedEvent $event): void
    {
        $transition = $event->getTransition();
        if (null === $transition) {
            return;
        }

        $user = $this->security->getUser();
        $comment = $event->getContext()['comment'] ?? null;

        $this->tcc($event)->recordStatusChange(
            TccStatus::from($transition->getFroms()[0]),
            TccStatus::from($transition->getTos()[0]),
            $transition->getName(),
            // Transições automáticas (banca concluída) registram quem deu o último parecer
            $user instanceof User ? $user : null,
            \is_string($comment) ? $comment : null,
        );
    }

    public function startEvaluationRound(EnteredEvent $event): void
    {
        foreach ($this->tcc($event)->getBoardMembers() as $member) {
            $member->resetVerdict();
        }
    }

    public function openDefense(EnteredEvent $event): void
    {
        $this->tcc($event)->openDefense();
    }

    private function tcc(GuardEvent|CompletedEvent|EnteredEvent $event): Tcc
    {
        $subject = $event->getSubject();
        \assert($subject instanceof Tcc);

        return $subject;
    }
}
