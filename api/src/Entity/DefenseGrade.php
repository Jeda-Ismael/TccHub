<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DefenseGradeRepository;
use App\Util\Grade;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Notas de um avaliador em uma tentativa de defesa. Criada só por {@see Defense::grade()}.
 */
#[ORM\Entity(repositoryClass: DefenseGradeRepository::class)]
#[ORM\Table(name: 'defense_grades')]
#[ORM\UniqueConstraint(name: 'uniq_defense_grades_member', columns: ['defense_id', 'board_member_id'])]
#[ORM\HasLifecycleCallbacks]
class DefenseGrade
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'grades')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Defense $defense;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BoardMember $boardMember;

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2)]
    private string $projectGrade;

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2)]
    private string $presentationGrade;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Defense $defense, BoardMember $boardMember, string|float $projectGrade, string|float $presentationGrade, ?string $comment)
    {
        $this->id = Uuid::v7();
        $this->defense = $defense;
        $this->boardMember = $boardMember;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->change($projectGrade, $presentationGrade, $comment);
    }

    public function change(string|float $projectGrade, string|float $presentationGrade, ?string $comment): void
    {
        $this->projectGrade = Grade::normalize($projectGrade, 'nota do projeto');
        $this->presentationGrade = Grade::normalize($presentationGrade, 'nota da apresentação');
        $comment = null === $comment ? null : trim($comment);
        $this->comment = '' === $comment ? null : $comment;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDefense(): Defense
    {
        return $this->defense;
    }

    public function getBoardMember(): BoardMember
    {
        return $this->boardMember;
    }

    public function getProjectGrade(): string
    {
        return $this->projectGrade;
    }

    public function getPresentationGrade(): string
    {
        return $this->presentationGrade;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
