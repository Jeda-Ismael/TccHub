<?php

declare(strict_types=1);

namespace App\Entity;

use App\Exception\DomainRuleViolation;
use App\Repository\TccDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Uma versão do PDF do TCC. Criada só por {@see Tcc::addDocument()}.
 */
#[ORM\Entity(repositoryClass: TccDocumentRepository::class)]
#[ORM\Table(name: 'tcc_documents')]
#[ORM\UniqueConstraint(name: 'uniq_tcc_documents_version', columns: ['tcc_id', 'version'])]
class TccDocument
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tcc $tcc;

    #[ORM\Column]
    private int $version;

    /** Caminho relativo dentro do storage privado (Flysystem). */
    #[ORM\Column(length: 255)]
    private string $storagePath;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(type: Types::BIGINT)]
    private int $sizeBytes;

    #[ORM\Column(length: 64, options: ['fixed' => true])]
    private string $sha256;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $uploadedBy;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $uploadedAt;

    public function __construct(Tcc $tcc, int $version, string $storagePath, string $originalName, int $sizeBytes, string $sha256, User $uploadedBy)
    {
        if ($version < 1) {
            throw new DomainRuleViolation('A versão do documento deve começar em 1.');
        }
        if ($sizeBytes <= 0) {
            throw new DomainRuleViolation('O arquivo está vazio.');
        }
        if (1 !== preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            throw new DomainRuleViolation('Hash SHA-256 inválido.');
        }

        $this->id = Uuid::v7();
        $this->tcc = $tcc;
        $this->version = $version;
        $this->storagePath = $storagePath;
        $this->originalName = $originalName;
        $this->sizeBytes = $sizeBytes;
        $this->sha256 = $sha256;
        $this->uploadedBy = $uploadedBy;
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTcc(): Tcc
    {
        return $this->tcc;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function getSha256(): string
    {
        return $this->sha256;
    }

    public function getUploadedBy(): User
    {
        return $this->uploadedBy;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }
}
