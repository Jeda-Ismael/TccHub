<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PeriodType;
use App\Exception\DomainRuleViolation;
use App\Repository\SubmissionPeriodRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Janela em que é permitido criar TCCs (SUBMISSION) ou dar parecer (EVALUATION).
 */
#[ORM\Entity(repositoryClass: SubmissionPeriodRepository::class)]
#[ORM\Table(name: 'submission_periods')]
#[ORM\Index(name: 'idx_submission_periods_active', columns: ['type', 'start_date', 'end_date'])]
class SubmissionPeriod
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 20, enumType: PeriodType::class)]
    private PeriodType $type;

    #[ORM\Column(length: 120)]
    private string $label;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $endDate;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(PeriodType $type, string $label, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate)
    {
        $this->id = Uuid::v7();
        $this->update($type, $label, $startDate, $endDate);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function update(PeriodType $type, string $label, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): void
    {
        $label = trim($label);
        if ('' === $label) {
            throw new DomainRuleViolation('O nome do período não pode ficar vazio.');
        }
        if ($endDate <= $startDate) {
            throw new DomainRuleViolation('A data de término deve ser depois da data de início.');
        }

        $this->type = $type;
        $this->label = $label;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function isActiveAt(\DateTimeImmutable $moment): bool
    {
        return $this->startDate <= $moment && $moment <= $this->endDate;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getType(): PeriodType
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
