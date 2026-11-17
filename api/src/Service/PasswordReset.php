<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PasswordResetToken;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Exception\DomainRuleViolation;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * "Esqueci minha senha": manda um link de uso único, válido por 1 hora, e
 * troca a senha com ele. Trocar a senha encerra as sessões abertas.
 */
final class PasswordReset
{
    public const INVALID_LINK = 'Este link de troca de senha é inválido ou expirou. Peça um novo.';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly PasswordResetTokenRepository $tokens,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly ClockInterface $clock,
        #[Autowire(env: 'APP_FRONTEND_URL')] private readonly string $frontendUrl,
    ) {
    }

    /**
     * Manda o e-mail se a conta existir e estiver ativa. Não diz ao chamador se
     * existe: a resposta da API é a mesma nos dois casos.
     */
    public function request(string $email): void
    {
        $user = $this->users->findOneByEmail($email);
        if (null === $user || !$user->isActive()) {
            return;
        }

        $this->tokens->deleteAllFor($user);
        $plainToken = bin2hex(random_bytes(32));
        $token = new PasswordResetToken($user, $plainToken, $this->clock->now());
        $this->em->persist($token);
        $this->mailer->send($this->email($user, $plainToken));
        $this->em->flush();
    }

    public function reset(string $plainToken, string $newPassword): User
    {
        $token = $this->tokens->findOneByPlainToken($plainToken);
        if (null === $token || $token->isExpiredAt($this->clock->now()) || !$token->getUser()->isActive()) {
            throw new DomainRuleViolation(self::INVALID_LINK);
        }

        $user = $token->getUser();
        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->tokens->deleteAllFor($user);
        // Quem tinha a senha antiga perde as sessões abertas
        $this->em->createQueryBuilder()
            ->delete(RefreshToken::class, 'r')
            ->where('r.username = :email')
            ->setParameter('email', $user->getEmail())
            ->getQuery()
            ->execute();
        $this->em->flush();

        return $user;
    }

    private function email(User $user, string $plainToken): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->to(new Address($user->getEmail(), $user->getName()))
            ->subject('[hubtcc] Troca de senha')
            ->htmlTemplate('email/password_reset.html.twig')
            ->textTemplate('email/password_reset.txt.twig')
            ->context([
                'name' => $user->getName(),
                'link' => rtrim($this->frontendUrl, '/').'/reset-password?token='.$plainToken,
            ]);
    }
}
