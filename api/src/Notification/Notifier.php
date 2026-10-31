<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Notification;
use App\Entity\Tcc;
use App\Entity\User;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Cria os avisos do sininho. Só persiste: o flush é feito pela ação que gerou o
 * aviso (dentro do TccLocker), então aviso e mudança são gravados juntos ou não
 * são gravados. Quem fez a ação não é avisado do que ele mesmo fez.
 *
 * O e-mail fica para depois (docs/ARQUITETURA.md §15): reaproveita estes textos.
 */
final class Notifier
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
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
            $this->em->persist(new Notification($recipient, $type, $title, $body, $tcc));
        }
    }
}
