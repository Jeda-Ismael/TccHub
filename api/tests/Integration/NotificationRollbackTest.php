<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Notification;
use App\Entity\Tcc;
use App\Enum\NotificationType;
use App\Exception\DomainRuleViolation;
use App\Factory\UserFactory;
use App\Notification\Notifier;
use App\Service\TccLocker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * O aviso é gravado na mesma transação da ação: se a ação falha, ele some junto.
 */
final class NotificationRollbackTest extends KernelTestCase
{
    use Factories;

    public function testNotificationIsDiscardedWhenTheActionFails(): void
    {
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $student = UserFactory::new()->student()->create();
        $advisor = UserFactory::new()->advisor()->create();
        $tcc = new Tcc('TCC', 'Resumo', $student);
        $em->persist($tcc);
        $em->flush();

        try {
            $container->get(TccLocker::class)->run($tcc, static function () use ($container, $em, $tcc, $advisor): void {
                $container->get(Notifier::class)->notify(NotificationType::TccSubmitted, $tcc, [$advisor], 'Título', 'Texto');
                // Mesmo já enviado ao banco, o aviso é desfeito com o rollback
                $em->flush();
                throw new DomainRuleViolation('Regra barrou a ação.');
            });
            self::fail('A ação deveria ter falhado.');
        } catch (DomainRuleViolation) {
        }

        $em->clear();
        self::assertSame(0, $em->getRepository(Notification::class)->count([]));
    }
}
