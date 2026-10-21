<?php

declare(strict_types=1);

namespace App\Entity;

use App\Exception\DomainRuleViolation;
use App\Repository\SettingsRepository;
use App\Util\Grade;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Configurações gerais definidas pela coordenação. Existe uma única linha
 * (id = 1), criada pela migration com os valores padrão.
 */
#[ORM\Entity(repositoryClass: SettingsRepository::class)]
#[ORM\Table(name: 'settings')]
class Settings
{
    public const SINGLETON_ID = 1;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::SINGLETON_ID;

    #[ORM\Column]
    private int $boardMinSize;

    #[ORM\Column]
    private int $boardMaxSize;

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2)]
    private string $passingGrade;

    public function __construct(int $boardMinSize = 2, int $boardMaxSize = 3, string|float $passingGrade = '6.00')
    {
        $this->update($boardMinSize, $boardMaxSize, $passingGrade);
    }

    public function update(int $boardMinSize, int $boardMaxSize, string|float $passingGrade): void
    {
        if ($boardMinSize < 1) {
            throw new DomainRuleViolation('A banca precisa ter pelo menos 1 avaliador.');
        }
        if ($boardMaxSize < $boardMinSize) {
            throw new DomainRuleViolation('O tamanho máximo da banca não pode ser menor que o mínimo.');
        }

        $this->boardMinSize = $boardMinSize;
        $this->boardMaxSize = $boardMaxSize;
        $this->passingGrade = Grade::normalize($passingGrade, 'nota mínima');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getBoardMinSize(): int
    {
        return $this->boardMinSize;
    }

    public function getBoardMaxSize(): int
    {
        return $this->boardMaxSize;
    }

    public function getPassingGrade(): string
    {
        return $this->passingGrade;
    }

    public function acceptsBoardSize(int $size): bool
    {
        return $size >= $this->boardMinSize && $size <= $this->boardMaxSize;
    }
}
