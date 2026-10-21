<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DefenseOutcome;
use App\Exception\DomainRuleViolation;
use App\Repository\DefenseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Uma tentativa de defesa. Nasce sem data (aguardando agendamento) quando o
 * TCC entra em AWAITING_DEFENSE; criada só por {@see Tcc::openDefense()}.
 * O cálculo do resultado fica no DefenseService (Fase 5).
 */
#[ORM\Entity(repositoryClass: DefenseRepository::class)]
#[ORM\Table(name: 'defenses')]
#[ORM\UniqueConstraint(name: 'uniq_defenses_tcc_attempt', columns: ['tcc_id', 'attempt'])]
class Defense
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'defenses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tcc $tcc;

    #[ORM\Column]
    private int $attempt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $meetingUrl = null;

    #[ORM\ManyToOne]
    private ?User $scheduledBy = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2, nullable: true)]
    private ?string $projectAverage = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2, nullable: true)]
    private ?string $presentationAverage = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2, nullable: true)]
    private ?string $finalGrade = null;

    #[ORM\Column(length: 20, enumType: DefenseOutcome::class)]
    private DefenseOutcome $outcome = DefenseOutcome::Pending;

    /** @var Collection<int, DefenseGrade> */
    #[ORM\OneToMany(targetEntity: DefenseGrade::class, mappedBy: 'defense', cascade: ['persist'], orphanRemoval: true)]
    private Collection $grades;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Tcc $tcc, int $attempt)
    {
        if ($attempt < 1) {
            throw new DomainRuleViolation('A tentativa de defesa deve começar em 1.');
        }

        $this->id = Uuid::v7();
        $this->tcc = $tcc;
        $this->attempt = $attempt;
        $this->grades = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    /** Agenda ou reagenda; só enquanto nenhuma nota foi lançada. */
    public function schedule(\DateTimeImmutable $at, ?string $location, ?string $meetingUrl, User $by): void
    {
        if ($this->isClosed()) {
            throw new DomainRuleViolation('Esta defesa já foi encerrada.');
        }
        if (!$this->grades->isEmpty()) {
            throw new DomainRuleViolation('Não é possível reagendar: a banca já começou a lançar notas.');
        }

        $location = null === $location ? null : trim($location);
        $meetingUrl = null === $meetingUrl ? null : trim($meetingUrl);
        if (('' === $location || null === $location) && ('' === $meetingUrl || null === $meetingUrl)) {
            throw new DomainRuleViolation('Informe a sala ou o link da defesa.');
        }
        if (null !== $meetingUrl && '' !== $meetingUrl && false === filter_var($meetingUrl, \FILTER_VALIDATE_URL)) {
            throw new DomainRuleViolation('Link da defesa inválido.');
        }

        $this->scheduledAt = $at;
        $this->location = '' === $location ? null : $location;
        $this->meetingUrl = '' === $meetingUrl ? null : $meetingUrl;
        $this->scheduledBy = $by;
    }

    public function isScheduled(): bool
    {
        return null !== $this->scheduledAt;
    }

    public function hasStartedAt(\DateTimeImmutable $moment): bool
    {
        return null !== $this->scheduledAt && $this->scheduledAt <= $moment;
    }

    public function isClosed(): bool
    {
        return DefenseOutcome::Pending !== $this->outcome;
    }

    public function findGradeBy(BoardMember $member): ?DefenseGrade
    {
        foreach ($this->grades as $grade) {
            if ($grade->getBoardMember()->getId()->equals($member->getId())) {
                return $grade;
            }
        }

        return null;
    }

    /** Lança ou corrige a nota de um avaliador (até a defesa ser encerrada). */
    public function grade(BoardMember $member, string|float $projectGrade, string|float $presentationGrade, ?string $comment): DefenseGrade
    {
        if ($this->isClosed()) {
            throw new DomainRuleViolation('Esta defesa já foi encerrada.');
        }
        if (!$member->getTcc()->getId()->equals($this->tcc->getId())) {
            throw new DomainRuleViolation('Este avaliador não faz parte da banca deste TCC.');
        }

        $existing = $this->findGradeBy($member);
        if (null !== $existing) {
            $existing->change($projectGrade, $presentationGrade, $comment);

            return $existing;
        }

        $grade = new DefenseGrade($this, $member, $projectGrade, $presentationGrade, $comment);
        $this->grades->add($grade);

        return $grade;
    }

    /** Grava o resultado já calculado e encerra a tentativa. */
    public function close(string $projectAverage, string $presentationAverage, string $finalGrade, DefenseOutcome $outcome): void
    {
        if (DefenseOutcome::Pending === $outcome) {
            throw new DomainRuleViolation('O resultado da defesa precisa ser aprovado ou reprovado.');
        }
        if ($this->isClosed()) {
            throw new DomainRuleViolation('Esta defesa já foi encerrada.');
        }

        $this->projectAverage = $projectAverage;
        $this->presentationAverage = $presentationAverage;
        $this->finalGrade = $finalGrade;
        $this->outcome = $outcome;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTcc(): Tcc
    {
        return $this->tcc;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getMeetingUrl(): ?string
    {
        return $this->meetingUrl;
    }

    public function getScheduledBy(): ?User
    {
        return $this->scheduledBy;
    }

    public function getProjectAverage(): ?string
    {
        return $this->projectAverage;
    }

    public function getPresentationAverage(): ?string
    {
        return $this->presentationAverage;
    }

    public function getFinalGrade(): ?string
    {
        return $this->finalGrade;
    }

    public function getOutcome(): DefenseOutcome
    {
        return $this->outcome;
    }

    /** @return Collection<int, DefenseGrade> */
    public function getGrades(): Collection
    {
        return $this->grades;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
