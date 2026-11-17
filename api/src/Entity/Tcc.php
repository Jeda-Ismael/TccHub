<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Role;
use App\Enum\TccStatus;
use App\Exception\DomainRuleViolation;
use App\Repository\TccRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TccRepository::class)]
#[ORM\Table(name: 'tccs')]
#[ORM\Index(name: 'idx_tccs_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Tcc
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $abstract;

    #[ORM\Column(length: 30, enumType: TccStatus::class)]
    private TccStatus $status = TccStatus::Draft;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $student;

    #[ORM\ManyToOne]
    private ?User $advisor = null;

    /** Nota da última defesa aprovada (copiada de Defense para facilitar listagens). */
    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2, nullable: true)]
    private ?string $finalGrade = null;

    #[ORM\Column]
    private bool $publicationAuthorized = false;

    /** Versão do PDF quando as correções foram pedidas; o reenvio exige uma versão mais nova. */
    #[ORM\Column(nullable: true)]
    private ?int $correctionsRequestedAtVersion = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /** @var Collection<int, ThematicArea> */
    #[ORM\ManyToMany(targetEntity: ThematicArea::class)]
    #[ORM\JoinTable(name: 'tcc_thematic_areas')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private Collection $thematicAreas;

    /** @var Collection<int, Keyword> */
    #[ORM\ManyToMany(targetEntity: Keyword::class)]
    #[ORM\JoinTable(name: 'tcc_keywords')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private Collection $keywords;

    /** @var Collection<int, TccDocument> */
    #[ORM\OneToMany(targetEntity: TccDocument::class, mappedBy: 'tcc', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['version' => 'ASC'])]
    private Collection $documents;

    /** @var Collection<int, BoardMember> */
    #[ORM\OneToMany(targetEntity: BoardMember::class, mappedBy: 'tcc', cascade: ['persist'], orphanRemoval: true)]
    private Collection $boardMembers;

    /** @var Collection<int, Defense> */
    #[ORM\OneToMany(targetEntity: Defense::class, mappedBy: 'tcc', cascade: ['persist'])]
    #[ORM\OrderBy(['attempt' => 'ASC'])]
    private Collection $defenses;

    /** @var Collection<int, TccStatusHistory> */
    #[ORM\OneToMany(targetEntity: TccStatusHistory::class, mappedBy: 'tcc', cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $statusHistory;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $title, string $abstract, User $student)
    {
        if (!$student->hasRole(Role::Student)) {
            throw new DomainRuleViolation('Só um aluno pode ser autor de um TCC.');
        }

        $this->id = Uuid::v7();
        $this->student = $student;
        $this->thematicAreas = new ArrayCollection();
        $this->keywords = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->boardMembers = new ArrayCollection();
        $this->defenses = new ArrayCollection();
        $this->statusHistory = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->changeTitle($title);
        $this->changeAbstract($abstract);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function changeTitle(string $title): void
    {
        $title = trim($title);
        if ('' === $title) {
            throw new DomainRuleViolation('O título não pode ficar vazio.');
        }
        $this->title = $title;
    }

    public function getAbstract(): string
    {
        return $this->abstract;
    }

    public function changeAbstract(string $abstract): void
    {
        $abstract = trim($abstract);
        if ('' === $abstract) {
            throw new DomainRuleViolation('O resumo não pode ficar vazio.');
        }
        $this->abstract = $abstract;
    }

    public function getStatus(): TccStatus
    {
        return $this->status;
    }

    /**
     * Usado pelo Symfony Workflow (Fase 4). Fora dele, mude o status só por
     * transições do workflow, nunca chamando este método diretamente.
     */
    public function setStatus(TccStatus $status): void
    {
        $this->status = $status;
    }

    /** Marcação usada pelo Symfony Workflow (config/packages/workflow.yaml). */
    public function getMarking(): string
    {
        return $this->status->value;
    }

    /** @param array<string, mixed> $context */
    public function setMarking(string $marking, array $context = []): void
    {
        $this->status = TccStatus::from($marking);
    }

    public function getStudent(): User
    {
        return $this->student;
    }

    public function getAdvisor(): ?User
    {
        return $this->advisor;
    }

    public function changeAdvisor(?User $advisor): void
    {
        if (null !== $advisor) {
            if (!$advisor->hasRole(Role::Advisor)) {
                throw new DomainRuleViolation(\sprintf('%s não tem o papel de Orientador.', $advisor->getName()));
            }
            // Quem já orienta continua, mesmo desativado; só não entra como novo orientador
            if (!$advisor->isActive() && $advisor !== $this->advisor) {
                throw new DomainRuleViolation(\sprintf('%s está desativado e não pode ser escolhido como orientador.', $advisor->getName()));
            }
            if (null !== $this->findBoardMember($advisor)) {
                throw new DomainRuleViolation('O orientador não pode fazer parte da banca do próprio orientando.');
            }
        }
        $this->advisor = $advisor;
    }

    public function isAdvisedBy(User $user): bool
    {
        return null !== $this->advisor && $this->advisor->getId()->equals($user->getId());
    }

    public function isAuthoredBy(User $user): bool
    {
        return $this->student->getId()->equals($user->getId());
    }

    /** @return Collection<int, ThematicArea> */
    public function getThematicAreas(): Collection
    {
        return $this->thematicAreas;
    }

    /** @param iterable<ThematicArea> $areas */
    public function replaceThematicAreas(iterable $areas): void
    {
        $this->thematicAreas->clear();
        foreach ($areas as $area) {
            if (!$this->thematicAreas->contains($area)) {
                $this->thematicAreas->add($area);
            }
        }
    }

    /** @return Collection<int, Keyword> */
    public function getKeywords(): Collection
    {
        return $this->keywords;
    }

    /** @param iterable<Keyword> $keywords */
    public function replaceKeywords(iterable $keywords): void
    {
        $this->keywords->clear();
        foreach ($keywords as $keyword) {
            if (!$this->keywords->contains($keyword)) {
                $this->keywords->add($keyword);
            }
        }
    }

    // —— Documentos ————————————————————————————————————————————————

    /** @return Collection<int, TccDocument> */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function getCurrentDocument(): ?TccDocument
    {
        $last = $this->documents->last();

        return false === $last ? null : $last;
    }

    /** Cada envio vira uma nova versão; as anteriores são mantidas. */
    public function addDocument(string $storagePath, string $originalName, int $sizeBytes, string $sha256, User $uploadedBy): TccDocument
    {
        $version = (int) $this->getCurrentDocument()?->getVersion() + 1;
        $document = new TccDocument($this, $version, $storagePath, $originalName, $sizeBytes, $sha256, $uploadedBy);
        $this->documents->add($document);

        return $document;
    }

    /** Marca a versão atual do PDF ao pedir correções (chamado pelo workflow). */
    public function markCorrectionsRequested(): void
    {
        $this->correctionsRequestedAtVersion = $this->getCurrentDocument()?->getVersion() ?? 0;
    }

    /** Depois de correções pedidas, só reenvia com um PDF enviado depois do pedido. */
    public function hasDocumentSinceCorrectionsRequested(): bool
    {
        if (null === $this->correctionsRequestedAtVersion) {
            return true;
        }

        return ($this->getCurrentDocument()?->getVersion() ?? 0) > $this->correctionsRequestedAtVersion;
    }

    // —— Banca ————————————————————————————————————————————————————

    /** @return Collection<int, BoardMember> */
    public function getBoardMembers(): Collection
    {
        return $this->boardMembers;
    }

    public function findBoardMember(User $evaluator): ?BoardMember
    {
        foreach ($this->boardMembers as $member) {
            if ($member->getEvaluator()->getId()->equals($evaluator->getId())) {
                return $member;
            }
        }

        return null;
    }

    /** Lança DomainRuleViolation se o usuário não puder entrar na banca deste TCC. */
    public function assertCanJoinBoard(User $evaluator): void
    {
        $this->assertBoardIsEditable();

        if (!$evaluator->hasRole(Role::Evaluator)) {
            throw new DomainRuleViolation(\sprintf('%s não tem o papel de Avaliador.', $evaluator->getName()));
        }
        if (!$evaluator->isActive()) {
            throw new DomainRuleViolation(\sprintf('%s está desativado e não pode entrar na banca.', $evaluator->getName()));
        }
        if ($this->isAdvisedBy($evaluator)) {
            throw new DomainRuleViolation('O orientador não pode fazer parte da banca do próprio orientando.');
        }
        if (null !== $this->findBoardMember($evaluator)) {
            throw new DomainRuleViolation(\sprintf('%s já faz parte da banca.', $evaluator->getName()));
        }
    }

    public function addBoardMember(User $evaluator): BoardMember
    {
        $this->assertCanJoinBoard($evaluator);

        $member = new BoardMember($this, $evaluator);
        $this->boardMembers->add($member);

        return $member;
    }

    public function removeBoardMember(User $evaluator): void
    {
        $this->assertBoardIsEditable();

        $member = $this->findBoardMember($evaluator);
        if (null === $member) {
            throw new DomainRuleViolation(\sprintf('%s não faz parte da banca.', $evaluator->getName()));
        }
        $this->boardMembers->removeElement($member);
    }

    private function assertBoardIsEditable(): void
    {
        if ($this->status->isBoardLocked()) {
            throw new DomainRuleViolation('A banca não pode ser alterada a partir da etapa de defesa.');
        }
    }

    // —— Defesa ———————————————————————————————————————————————————

    /** @return Collection<int, Defense> */
    public function getDefenses(): Collection
    {
        return $this->defenses;
    }

    public function getCurrentDefense(): ?Defense
    {
        $last = $this->defenses->last();

        return false === $last ? null : $last;
    }

    /** Abre uma nova tentativa de defesa (a primeira, ou outra após decisão da coordenação). */
    public function openDefense(): Defense
    {
        $current = $this->getCurrentDefense();
        if (null !== $current && !$current->isClosed()) {
            throw new DomainRuleViolation('Já existe uma defesa em andamento para este TCC.');
        }

        $defense = new Defense($this, (int) $current?->getAttempt() + 1);
        $this->defenses->add($defense);

        return $defense;
    }

    public function getFinalGrade(): ?string
    {
        return $this->finalGrade;
    }

    public function recordFinalGrade(string $finalGrade): void
    {
        $this->finalGrade = $finalGrade;
    }

    // —— Histórico ————————————————————————————————————————————————

    /** @return Collection<int, TccStatusHistory> */
    public function getStatusHistory(): Collection
    {
        return $this->statusHistory;
    }

    public function recordStatusChange(?TccStatus $from, TccStatus $to, string $transition, ?User $changedBy, ?string $comment = null): TccStatusHistory
    {
        $entry = new TccStatusHistory($this, $from, $to, $transition, $changedBy, $comment);
        $this->statusHistory->add($entry);

        return $entry;
    }

    // —— Publicação ———————————————————————————————————————————————

    public function isPublicationAuthorized(): bool
    {
        return $this->publicationAuthorized;
    }

    public function authorizePublication(bool $authorized): void
    {
        $this->publicationAuthorized = $authorized;
        if (!$authorized) {
            $this->publishedAt = null;
        }
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function isPublished(): bool
    {
        return null !== $this->publishedAt;
    }

    public function publish(\DateTimeImmutable $at): void
    {
        if (TccStatus::Approved !== $this->status) {
            throw new DomainRuleViolation('Só TCCs aprovados podem ser publicados.');
        }
        if (!$this->publicationAuthorized) {
            throw new DomainRuleViolation('O aluno ainda não autorizou a publicação.');
        }
        $this->publishedAt = $at;
    }

    public function unpublish(): void
    {
        $this->publishedAt = null;
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
