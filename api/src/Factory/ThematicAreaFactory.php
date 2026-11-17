<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\ThematicArea;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ThematicArea>
 */
final class ThematicAreaFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ThematicArea::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->words(2, true),
        ];
    }
}
