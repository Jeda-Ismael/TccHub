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

    /** Nome usado na API e no frontend: ROLE_STUDENT → STUDENT. */
    public function apiName(): string
    {
        return substr($this->value, \strlen('ROLE_'));
    }

    public static function fromApiName(string $name): self
    {
        return self::tryFrom('ROLE_'.strtoupper($name))
            ?? throw new \ValueError(\sprintf('Papel desconhecido: "%s".', $name));
    }

    /** @return list<string> */
    public static function apiNames(): array
    {
        return array_map(static fn (self $role): string => $role->apiName(), self::cases());
    }

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
