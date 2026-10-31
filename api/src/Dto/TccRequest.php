<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Criação (POST, título e resumo obrigatórios) e edição (PATCH, só o que vier).
 * advisorId "" remove o orientador.
 */
final class TccRequest
{
    /**
     * @param list<string>|null $thematicAreaIds
     * @param list<string>|null $keywordIds
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Informe o título.', groups: ['create'])]
        #[Assert\Length(max: 255, maxMessage: 'O título pode ter no máximo {{ limit }} caracteres.', groups: ['create', 'Default'])]
        public readonly ?string $title = null,

        #[Assert\NotBlank(message: 'Informe o resumo.', groups: ['create'])]
        #[Assert\Length(max: 5000, maxMessage: 'O resumo pode ter no máximo {{ limit }} caracteres.', groups: ['create', 'Default'])]
        public readonly ?string $abstract = null,

        public readonly ?string $advisorId = null,

        #[Assert\All([new Assert\Type('string')], groups: ['create', 'Default'])]
        public readonly ?array $thematicAreaIds = null,

        #[Assert\All([new Assert\Type('string')], groups: ['create', 'Default'])]
        public readonly ?array $keywordIds = null,
    ) {
    }
}
