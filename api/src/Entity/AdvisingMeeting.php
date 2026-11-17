<?php

declare(strict_types=1);

namespace App\Entity;

use App\Exception\DomainRuleViolation;
use App\Repository\AdvisingMeetingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Registro de uma reunião de orientação: o orientador anota o que foi conversado
 * e o aluno confirma que a reunião aconteceu. Depois de confirmada, não muda mais.
 */
#[ORM\Entity(repositoryClass: AdvisingMeetingRepository::class)]
#[ORM\Table(name: 'advising_meetings')]
#[ORM\Index(name: 'idx_advising_meetings_tcc', columns: ['tcc_id', 'held_on'])]
class AdvisingMeeting
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tcc $tcc;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $advisor;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $heldOn;

    #[ORM\Column(type: Types::TEXT)]
    private string $summary;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $nextSteps;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Tcc $tcc, User $advisor, \DateTimeImmutable $heldOn, string $summary, ?string $nextSteps, \DateTimeImmutable $now)
    {
        $summary = trim($summary);
        if ('' === $summary) {
            throw new DomainRuleViolation('Escreva o que foi conversado na reunião.');
        }
        if ($heldOn->format('Y-m-d') > $now->format('Y-m-d')) {
            throw new DomainRuleViolation('A reunião não pode ter data no futuro.');
        }

        $this->id = Uuid::v7();
        $this->tcc = $tcc;
        $this->advisor = $advisor;
        $this->heldOn = $heldOn;
        $this->summary = $summary;
        $nextSteps = null === $nextSteps ? null : trim($nextSteps);
        $this->nextSteps = '' === $nextSteps ? null : $nextSteps;
        $this->createdAt = $now;
    }

    public function confirm(\DateTimeImmutable $at): void
    {
        if ($this->isConfirmed()) {
            throw new DomainRuleViolation('Esta reunião já foi confirmada.');
        }
        $this->confirmedAt = $at;
    }

    public function isConfirmed(): bool
    {
        return null !== $this->confirmedAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTcc(): Tcc
    {
        return $this->tcc;
    }

    public function getAdvisor(): User
    {
        return $this->advisor;
    }

    public function getHeldOn(): \DateTimeImmutable
    {
        return $this->heldOn;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getNextSteps(): ?string
    {
        return $this->nextSteps;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
