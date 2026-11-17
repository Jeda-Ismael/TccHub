<?php

declare(strict_types=1);

namespace App\Entity;

use App\Exception\DomainRuleViolation;
use App\Repository\MilestoneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Etapa do cronograma do TCC (ex.: projeto, qualificação, versão final), com a data
 * limite definida pela coordenação. Vale para todos os TCCs do semestre.
 */
#[ORM\Entity(repositoryClass: MilestoneRepository::class)]
#[ORM\Table(name: 'milestones')]
class Milestone
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dueDate;

    public function __construct(string $name, ?string $description, \DateTimeImmutable $dueDate)
    {
        $this->id = Uuid::v7();
        $this->update($name, $description, $dueDate);
    }

    public function update(string $name, ?string $description, \DateTimeImmutable $dueDate): void
    {
        $name = trim($name);
        if ('' === $name) {
            throw new DomainRuleViolation('O nome da etapa não pode ficar vazio.');
        }
        $description = null === $description ? null : trim($description);

        $this->name = $name;
        $this->description = '' === $description ? null : $description;
        $this->dueDate = $dueDate;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDueDate(): \DateTimeImmutable
    {
        return $this->dueDate;
    }
}
