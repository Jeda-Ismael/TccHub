<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EvaluationVerdict;
use App\Exception\DomainRuleViolation;
use App\Repository\BoardMemberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Avaliador na banca de um TCC, com o parecer da rodada atual.
 * Criado só por {@see Tcc::addBoardMember()}.
 */
#[ORM\Entity(repositoryClass: BoardMemberRepository::class)]
#[ORM\Table(name: 'board_members')]
#[ORM\UniqueConstraint(name: 'uniq_board_members_tcc_evaluator', columns: ['tcc_id', 'evaluator_id'])]
#[ORM\HasLifecycleCallbacks]
class BoardMember
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'boardMembers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tcc $tcc;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $evaluator;

    #[ORM\Column(length: 30, enumType: EvaluationVerdict::class)]
    private EvaluationVerdict $verdict = EvaluationVerdict::Pending;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Tcc $tcc, User $evaluator)
    {
        $this->id = Uuid::v7();
        $this->tcc = $tcc;
        $this->evaluator = $evaluator;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function giveVerdict(EvaluationVerdict $verdict, ?string $comment): void
    {
        if (EvaluationVerdict::Pending === $verdict) {
            throw new DomainRuleViolation('Escolha aprovar ou solicitar correções.');
        }
        $comment = null === $comment ? null : trim($comment);
        if (EvaluationVerdict::CorrectionsRequested === $verdict && (null === $comment || '' === $comment)) {
            throw new DomainRuleViolation('Descreva as correções solicitadas no comentário.');
        }

        $this->verdict = $verdict;
        $this->comment = '' === $comment ? null : $comment;
    }

    /** Nova rodada de avaliação (após o reenvio do aluno). */
    public function resetVerdict(): void
    {
        $this->verdict = EvaluationVerdict::Pending;
        $this->comment = null;
    }

    public function hasVoted(): bool
    {
        return EvaluationVerdict::Pending !== $this->verdict;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTcc(): Tcc
    {
        return $this->tcc;
    }

    public function getEvaluator(): User
    {
        return $this->evaluator;
    }

    public function getVerdict(): EvaluationVerdict
    {
        return $this->verdict;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
