<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Uma regra de negócio foi violada (ex.: aluno com outro papel, orientador na
 * própria banca). A API converte em resposta 422 com a mensagem.
 */
final class DomainRuleViolation extends \DomainException
{
}
