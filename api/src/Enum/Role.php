<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Papéis do sistema. Os valores seguem a convenção ROLE_* do Symfony Security.
 *
 * Aluno é exclusivo; Orientador, Avaliador e Coordenação combinam livremente.
 */
enum Role: string
{
    case Student = 'ROLE_STUDENT';
    case Advisor = 'ROLE_ADVISOR';
    case Evaluator = 'ROLE_EVALUATOR';
    case Coordination = 'ROLE_COORDINATION';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Aluno',
            self::Advisor => 'Orientador',
            self::Evaluator => 'Avaliador',
            self::Coordination => 'Coordenação',
        };
    }
}
