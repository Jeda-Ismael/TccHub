<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class DefenseGradeRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Informe a nota do projeto.')]
        #[Assert\Range(min: 0, max: 10, notInRangeMessage: 'A nota do projeto deve estar entre 0 e 10.')]
        public readonly ?float $projectGrade = null,

        #[Assert\NotNull(message: 'Informe a nota da apresentação.')]
        #[Assert\Range(min: 0, max: 10, notInRangeMessage: 'A nota da apresentação deve estar entre 0 e 10.')]
        public readonly ?float $presentationGrade = null,

        #[Assert\Length(max: 5000, maxMessage: 'O comentário pode ter no máximo {{ limit }} caracteres.')]
        public readonly ?string $comment = null,
    ) {
    }
}
