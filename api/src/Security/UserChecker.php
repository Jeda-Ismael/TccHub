<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Barra usuários desativados pela coordenação, tanto no login quanto em cada
 * requisição com JWT e no refresh: desativar tira o acesso na hora.
 */
final class UserChecker implements UserCheckerInterface
{
    public const MESSAGE = 'Este usuário foi desativado. Fale com a coordenação.';

    public function checkPreAuth(UserInterface $user): void
    {
    }

    /**
     * Depois da senha conferida: assim a mensagem de "desativado" só aparece
     * para quem sabe a senha, e não revela se um e-mail existe.
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if ($user instanceof User && !$user->isActive()) {
            throw new CustomUserMessageAccountStatusException(self::MESSAGE);
        }
    }
}
