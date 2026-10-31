<?php

declare(strict_types=1);

namespace App\Story;

use App\Enum\Role;
use App\Factory\KeywordFactory;
use App\Factory\SubmissionPeriodFactory;
use App\Factory\ThematicAreaFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Story;

/**
 * Dados mínimos para usar o sistema em desenvolvimento: um usuário por papel
 * (senha 123456), áreas temáticas, palavras-chave e períodos abertos.
 * TCCs de exemplo ficam no App\DataFixtures\DemoFixtures (`make demo`).
 */
final class BaseStory extends Story
{
    public const USERS = [
        'aluno' => ['Ana Aluna', 'aluno@hubtcc.dev', [Role::Student]],
        'aluno2' => ['Bruno Aluno', 'aluno2@hubtcc.dev', [Role::Student]],
        'orientador' => ['Olga Orientadora', 'orientador@hubtcc.dev', [Role::Advisor]],
        'avaliador1' => ['Arthur Avaliador', 'avaliador1@hubtcc.dev', [Role::Evaluator]],
        'avaliador2' => ['Alice Avaliadora', 'avaliador2@hubtcc.dev', [Role::Evaluator]],
        'coordenacao' => ['Carla Coordenadora', 'coordenacao@hubtcc.dev', [Role::Coordination]],
        // Professor com os três papéis, para testar o seletor de papel
        'professor' => ['Paulo Professor', 'professor@hubtcc.dev', [Role::Advisor, Role::Evaluator, Role::Coordination]],
    ];

    public const THEMATIC_AREAS = [
        'Engenharia de Software',
        'Inteligência Artificial',
        'Redes de Computadores',
        'Banco de Dados',
        'Segurança da Informação',
        'Interação Humano-Computador',
    ];

    public const KEYWORDS = [
        'aprendizado de máquina',
        'desenvolvimento web',
        'acessibilidade',
        'computação em nuvem',
        'internet das coisas',
        'metodologias ágeis',
        'visão computacional',
        'educação',
    ];

    public function build(): void
    {
        foreach (self::USERS as $key => [$name, $email, $roles]) {
            $this->addState($key, UserFactory::createOne(['name' => $name, 'email' => $email, 'roles' => $roles]));
        }

        foreach (self::THEMATIC_AREAS as $name) {
            ThematicAreaFactory::createOne(['name' => $name]);
        }

        foreach (self::KEYWORDS as $name) {
            KeywordFactory::createOne(['name' => $name]);
        }

        SubmissionPeriodFactory::new()->submission()->create();
        SubmissionPeriodFactory::new()->evaluation()->create();
    }
}
