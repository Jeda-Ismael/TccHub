<?php

declare(strict_types=1);

namespace App\Entity;

use App\Exception\DomainRuleViolation;
use App\Repository\ThematicAreaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ThematicAreaRepository::class)]
#[ORM\Table(name: 'thematic_areas')]
class ThematicArea
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 120, unique: true)]
    private string $name;

    public function __construct(string $name)
    {
        $this->id = Uuid::v7();
        $this->rename($name);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ('' === $name) {
            throw new DomainRuleViolation('O nome da área temática não pode ficar vazio.');
        }
        $this->name = $name;
    }
}
