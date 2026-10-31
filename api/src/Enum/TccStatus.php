<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Estados do TCC. As transições ficam no Symfony Workflow (Fase 4);
 * ver docs/ARQUITETURA.md §7.
 */
enum TccStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Resubmitted = 'RESUBMITTED';
    case SentToEvaluation = 'SENT_TO_EVALUATION';
    case CorrectionsRequested = 'CORRECTIONS_REQUESTED';
    case AdvisorFinalReview = 'ADVISOR_FINAL_REVIEW';
    case AwaitingDefense = 'AWAITING_DEFENSE';
    case AwaitingDecision = 'AWAITING_DECISION';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Submitted => 'Submetido',
            self::Resubmitted => 'Reenviado',
            self::SentToEvaluation => 'Em avaliação pela banca',
            self::CorrectionsRequested => 'Correções solicitadas',
            self::AdvisorFinalReview => 'Revisão final do orientador',
            self::AwaitingDefense => 'Aguardando defesa',
            self::AwaitingDecision => 'Aguardando decisão da coordenação',
            self::Approved => 'Aprovado',
            self::Rejected => 'Reprovado',
        };
    }

    public function isFinal(): bool
    {
        return self::Approved === $this || self::Rejected === $this;
    }

    /** Estados em que o aluno pode editar o TCC e enviar um novo PDF. */
    public function isEditableByStudent(): bool
    {
        return self::Draft === $this || self::CorrectionsRequested === $this;
    }

    /** A banca não pode mudar enquanto aguarda ou passa pela defesa, nem depois do fim. */
    public function isBoardLocked(): bool
    {
        return $this->isFinal() || self::AwaitingDefense === $this || self::AwaitingDecision === $this;
    }
}
