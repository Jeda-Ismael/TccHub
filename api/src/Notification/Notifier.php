<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Notification;
use App\Entity\Tcc;
use App\Entity\User;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Cria os avisos do sininho. Só persiste: o flush é feito pela ação que gerou o
 * aviso (dentro do TccLocker), então aviso e mudança são gravados juntos ou não
 * são gravados. Quem fez a ação não é avisado do que ele mesmo fez.
 *
 * Cada aviso também vira um e-mail com o mesmo título e texto. O Mailer só põe
 * o e-mail na fila (tabela messenger_messages, mesma transação); o serviço
 * worker envia. Usuários desativados não recebem e-mail.
 */
final class Notifier
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'APP_FRONTEND_URL')] private readonly string $frontendUrl,
    ) {
    }

    /**
     * @param iterable<User|null> $recipients
     * @param bool                $skipActor  false quando o aviso é uma tarefa nova para quem
     *                                        agiu (ex.: o coordenador que deu a última nota
     *                                        precisa decidir a defesa)
     */
    public function notify(NotificationType $type, ?Tcc $tcc, iterable $recipients, string $title, string $body, bool $skipActor = true): void
    {
        $actor = $skipActor ? $this->security->getUser() : null;
        $sent = [];
        foreach ($recipients as $recipient) {
            if (null === $recipient) {
                continue;
            }
            $key = $recipient->getId()->toRfc4122();
            if (isset($sent[$key]) || ($actor instanceof User && $actor->getId()->equals($recipient->getId()))) {
                continue;
            }
            $sent[$key] = true;
            $notification = new Notification($recipient, $type, $title, $body, $tcc);
            $this->em->persist($notification);
            if ($recipient->isActive()) {
                $this->mailer->send($this->email($notification));
            }
        }
    }

    private function email(Notification $notification): TemplatedEmail
    {
        $recipient = $notification->getRecipient();

        return (new TemplatedEmail())
            ->to(new Address($recipient->getEmail(), $recipient->getName()))
            ->subject('[hubtcc] '.$notification->getTitle())
            ->htmlTemplate('email/notification.html.twig')
            ->textTemplate('email/notification.txt.twig')
            ->context([
                'name' => $recipient->getName(),
                'title' => $notification->getTitle(),
                'body' => $notification->getBody(),
                'tccTitle' => $notification->getTcc()?->getTitle(),
                'link' => rtrim($this->frontendUrl, '/').'/',
            ]);
    }
}
