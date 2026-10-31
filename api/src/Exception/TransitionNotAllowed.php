<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A ação não vale para o status atual do TCC (ex.: submeter algo já em
 * avaliação). A API responde 409 com a mensagem.
 */
final class TransitionNotAllowed extends \DomainException
{
}
