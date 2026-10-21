<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Keyword;
use App\Entity\Notification;
use App\Entity\Settings;
use App\Entity\SubmissionPeriod;
use App\Entity\Tcc;
use App\Entity\TccDocument;
use App\Entity\ThematicArea;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\PeriodType;
use App\Exception\DomainRuleViolation;
use PHPUnit\Framework\TestCase;

/** Entidades simples: catálogos, períodos, configurações, notificações e documentos. */
final class SmallEntitiesTest extends TestCase
{
    public function testCatalogNamesAreTrimmedAndRequired(): void
    {
        $area = new ThematicArea('  IA ');
        $keyword = new Keyword(' web ');
        $keyword->rename('nuvem');

        self::assertSame('IA', $area->getName());
        self::assertSame('nuvem', $keyword->getName());

        $this->expectExceptionMessage('O nome da área temática não pode ficar vazio.');
        $area->rename(' ');
    }

    public function testPeriodIsActiveOnlyInsideItsWindow(): void
    {
        $period = new SubmissionPeriod(PeriodType::Submission, ' 2026/2 ', new \DateTimeImmutable('2026-08-01'), new \DateTimeImmutable('2026-12-01'));

        self::assertSame('2026/2', $period->getLabel());
        self::assertSame(PeriodType::Submission, $period->getType());
        self::assertTrue($period->isActiveAt(new \DateTimeImmutable('2026-08-01')));
        self::assertTrue($period->isActiveAt(new \DateTimeImmutable('2026-10-15')));
        self::assertFalse($period->isActiveAt(new \DateTimeImmutable('2026-12-02')));
        self::assertFalse($period->isActiveAt(new \DateTimeImmutable('2026-07-31')));
    }

    public function testPeriodMustEndAfterItStarts(): void
    {
        $this->expectExceptionMessage('A data de término deve ser depois da data de início.');

        new SubmissionPeriod(PeriodType::Evaluation, 'Avaliação', new \DateTimeImmutable('2026-12-01'), new \DateTimeImmutable('2026-12-01'));
    }

    public function testPeriodNeedsLabel(): void
    {
        $this->expectException(DomainRuleViolation::class);

        new SubmissionPeriod(PeriodType::Evaluation, '', new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-02-01'));
    }

    public function testSettingsDefaultsAndBoardSize(): void
    {
        $settings = new Settings();

        self::assertSame(Settings::SINGLETON_ID, $settings->getId());
        self::assertSame(2, $settings->getBoardMinSize());
        self::assertSame(3, $settings->getBoardMaxSize());
        self::assertSame('6.00', $settings->getPassingGrade());
        self::assertFalse($settings->acceptsBoardSize(1));
        self::assertTrue($settings->acceptsBoardSize(2));
        self::assertTrue($settings->acceptsBoardSize(3));
        self::assertFalse($settings->acceptsBoardSize(4));

        $settings->update(1, 1, 7.5);
        self::assertSame('7.50', $settings->getPassingGrade());
    }

    public function testSettingsValidation(): void
    {
        $settings = new Settings();

        foreach ([[0, 3, 6], [3, 2, 6], [2, 3, 11]] as [$min, $max, $grade]) {
            try {
                $settings->update($min, $max, $grade);
                self::fail(\sprintf('Configuração inválida aceita: %d/%d/%s', $min, $max, $grade));
            } catch (DomainRuleViolation) {
            }
        }

        self::assertSame(2, $settings->getBoardMinSize());
    }

    public function testNotificationIsMarkedReadOnlyOnce(): void
    {
        $user = new User('Ana', 'ana@hubtcc.dev');
        $tcc = new Tcc('Título', 'Resumo', $user);
        $notification = new Notification($user, NotificationType::CorrectionsRequested, 'Correções', 'Veja os comentários', $tcc);
        $first = new \DateTimeImmutable('2026-10-01');

        self::assertFalse($notification->isRead());

        $notification->markAsRead($first);
        $notification->markAsRead(new \DateTimeImmutable('2026-10-02'));

        self::assertTrue($notification->isRead());
        self::assertSame($first, $notification->getReadAt());
        self::assertSame($user, $notification->getRecipient());
        self::assertSame($tcc, $notification->getTcc());
        self::assertSame(NotificationType::CorrectionsRequested, $notification->getType());
        self::assertSame('Correções', $notification->getTitle());
        self::assertSame('Veja os comentários', $notification->getBody());
    }

    public function testDocumentValidation(): void
    {
        $student = new User('Ana', 'ana@hubtcc.dev');
        $tcc = new Tcc('Título', 'Resumo', $student);
        $hash = str_repeat('f', 64);

        $document = new TccDocument($tcc, 1, 'tcc/x.pdf', 'tcc.pdf', 2048, $hash, $student);
        self::assertSame(2048, $document->getSizeBytes());
        self::assertSame('tcc.pdf', $document->getOriginalName());
        self::assertSame('tcc/x.pdf', $document->getStoragePath());
        self::assertSame($hash, $document->getSha256());
        self::assertSame($student, $document->getUploadedBy());

        foreach ([[0, 10, $hash], [1, 0, $hash], [1, 10, 'abc']] as [$version, $size, $sha]) {
            try {
                new TccDocument($tcc, $version, 'p', 'n', $size, $sha, $student);
                self::fail('Documento inválido aceito');
            } catch (DomainRuleViolation) {
            }
        }
    }
}
