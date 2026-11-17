<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Defense;
use App\Entity\DefenseGrade;
use App\Exception\DomainRuleViolation;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

/**
 * Ata da defesa em PDF: quem estava na banca, as notas de cada um, as médias e o
 * resultado. É gerada na hora a partir do banco, então sempre bate com o sistema.
 */
final class DefenseMinutes
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ClockInterface $clock,
        #[Autowire(param: 'app.timezone')] private readonly string $timezone,
    ) {
    }

    public function render(Defense $defense): string
    {
        if (!$defense->isClosed()) {
            throw new DomainRuleViolation('A ata só fica disponível depois que toda a banca lança as notas.');
        }

        $tz = new \DateTimeZone($this->timezone);
        $grades = $defense->getGrades()->getValues();
        usort($grades, static fn (DefenseGrade $a, DefenseGrade $b): int => strcmp(
            $a->getBoardMember()->getEvaluator()->getName(),
            $b->getBoardMember()->getEvaluator()->getName(),
        ));

        $html = $this->twig->render('pdf/defense_minutes.html.twig', [
            'defense' => $defense,
            'tcc' => $defense->getTcc(),
            'grades' => $grades,
            'scheduledAt' => $defense->getScheduledAt()?->setTimezone($tz),
            'generatedAt' => $this->clock->now()->setTimezone($tz),
        ]);

        $options = new Options();
        // Nada de buscar arquivos ou URLs de fora: a ata usa só o HTML do template
        $options->setIsRemoteEnabled(false);
        $options->setChroot([__DIR__.'/../../templates/pdf']);
        $options->setDefaultFont('DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return (string) $pdf->output();
    }
}
