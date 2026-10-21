<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Keyword;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Keyword>
 */
final class KeywordFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Keyword::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->word(),
        ];
    }
}
