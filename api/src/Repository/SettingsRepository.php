<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Settings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Settings>
 */
class SettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Settings::class);
    }

    /** A linha única é criada pela migration; ausência indica banco sem migrations. */
    public function get(): Settings
    {
        return $this->find(Settings::SINGLETON_ID)
            ?? throw new \LogicException('Configurações não encontradas. Rode as migrations.');
    }
}
