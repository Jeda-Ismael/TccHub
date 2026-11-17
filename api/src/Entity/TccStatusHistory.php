<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TccStatus;
use App\Repository\TccStatusHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Registro imutável de cada mudança de status. Criado só por {@see Tcc::recordStatusChange()}.
 */
#[ORM\Entity(repositoryClass: TccStatusHistoryRepository::class, readOnly: true)]
#[ORM\Table(name: 'tcc_status_history')]
class TccStatusHistory
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'statusHistory')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tcc $tcc;

    #[ORM\Column(length: 30, nullable: true, enumType: TccStatus::class)]
    private ?TccStatus $fromStatus;

    #[ORM\Column(length: 30, enumType: TccStatus::class)]
    private TccStatus $toStatus;

    /** Nome da transição do workflow que gerou a mudança (ou "create"). */
    #[ORM\Column(length: 64)]
    private string $transition;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment;

    /** Nulo quando a mudança foi automática (ex.: fechamento da banca). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $changedBy;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Tcc $tcc, ?TccStatus $fromStatus, TccStatus $toStatus, string $transition, ?User $changedBy, ?string $comment = null)
    {
        $comment = null === $comment ? null : trim($comment);

        $this->id = Uuid::v7();
        $this->tcc = $tcc;
        $this->fromStatus = $fromStatus;
        $this->toStatus = $toStatus;
        $this->transition = $transition;
        $this->changedBy = $changedBy;
        $this->comment = '' === $comment ? null : $comment;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTcc(): Tcc
    {
        return $this->tcc;
    }

    public function getFromStatus(): ?TccStatus
    {
        return $this->fromStatus;
    }

    public function getToStatus(): TccStatus
    {
        return $this->toStatus;
    }

    public function getTransition(): string
    {
        return $this->transition;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
