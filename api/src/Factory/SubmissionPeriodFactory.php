<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\SubmissionPeriod;
use App\Enum\PeriodType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Por padrão cria um período ativo agora (começou ontem, termina em 6 meses).
 *
 * @extends PersistentObjectFactory<SubmissionPeriod>
 */
final class SubmissionPeriodFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return SubmissionPeriod::class;
    }

    public function submission(): self
    {
        return $this->with(['type' => PeriodType::Submission, 'label' => 'Submissão de TCC']);
    }

    public function evaluation(): self
    {
        return $this->with(['type' => PeriodType::Evaluation, 'label' => 'Avaliação pela banca']);
    }

    public function expired(): self
    {
        return $this->with([
            'startDate' => new \DateTimeImmutable('-2 months'),
            'endDate' => new \DateTimeImmutable('-1 month'),
        ]);
    }

    protected function defaults(): array
    {
        return [
            'type' => PeriodType::Submission,
            'label' => 'Submissão de TCC',
            'startDate' => new \DateTimeImmutable('-1 day'),
            'endDate' => new \DateTimeImmutable('+6 months'),
        ];
    }
}
