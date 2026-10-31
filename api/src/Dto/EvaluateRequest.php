<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\EvaluationVerdict;
use Symfony\Component\Validator\Constraints as Assert;

final class EvaluateRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['APPROVED', 'CORRECTIONS_REQUESTED'], message: 'Escolha aprovar ou solicitar correções.')]
        public readonly string $verdict = '',

        #[Assert\Length(max: 5000, maxMessage: 'O comentário pode ter no máximo {{ limit }} caracteres.')]
        public readonly ?string $comment = null,
    ) {
    }

    public function verdictEnum(): EvaluationVerdict
    {
        return EvaluationVerdict::from($this->verdict);
    }
}
