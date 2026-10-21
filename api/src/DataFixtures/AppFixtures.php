<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Settings;
use App\Story\BaseStory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Carregado por `bin/console doctrine:fixtures:load` (make fixtures / make db-reset).
 */
final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // O purge das fixtures apaga também a linha de configurações criada pela migration
        if (null === $manager->find(Settings::class, Settings::SINGLETON_ID)) {
            $manager->persist(new Settings());
            $manager->flush();
        }

        BaseStory::load();
    }
}
