<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Tcc;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Tcc>
 */
final class TccFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Tcc::class;
    }

    protected function defaults(): array
    {
        return [
            'title' => rtrim(self::faker()->sentence(8), '.'),
            'abstract' => self::faker()->paragraphs(2, true),
            'student' => UserFactory::new()->student(),
        ];
    }
}
