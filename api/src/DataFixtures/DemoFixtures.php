<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AdvisingMeeting;
use App\Entity\BoardMember;
use App\Entity\Keyword;
use App\Entity\Milestone;
use App\Entity\Tcc;
use App\Entity\ThematicArea;
use App\Entity\User;
use App\Enum\DefenseOutcome;
use App\Enum\EvaluationVerdict;
use App\Enum\Role;
use App\Enum\TccStatus;
use App\Factory\UserFactory;
use App\Search\RepositoryIndex;
use App\Service\DocumentStorage;
use App\Util\Grade;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Dados de demonstração (`make demo`): TCCs de outros alunos em vários pontos do
 * fluxo e alguns já publicados no repositório. Os usuários do BaseStory
 * (aluno@, aluno2@...) ficam livres para testar o fluxo do zero.
 *
 * Os TCCs são montados direto pelas entidades (com histórico e PDF de verdade),
 * sem passar pelo Workflow nem pelas regras de prazo: é só para ter o que
 * mostrar. Limitação conhecida: as datas do histórico são as do momento da carga.
 */
final class DemoFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /** Título, resumo, aluno, áreas, palavras-chave e notas [projeto, apresentação] de cada avaliador. */
    private const PUBLISHED = [
        ['Detecção de fraudes em cartões com aprendizado de máquina', 'Compara florestas aleatórias, XGBoost e redes neurais na detecção de fraudes em transações de cartão de crédito, com foco no desbalanceamento das classes.', 'Carla Mendes', ['Inteligência Artificial'], ['aprendizado de máquina'], [[9.0, 8.5], [8.5, 9.0]]],
        ['Acessibilidade em portais de prefeituras brasileiras', 'Avalia 120 portais municipais segundo as diretrizes WCAG 2.1 e propõe um checklist de correções de baixo custo para equipes pequenas.', 'Diego Rocha', ['Interação Humano-Computador'], ['acessibilidade', 'desenvolvimento web'], [[8.0, 8.0], [7.5, 8.5]]],
        ['Monitoramento de lavouras com sensores de baixo custo', 'Rede de sensores LoRa para umidade do solo em pequenas propriedades, com painel web e alertas por SMS.', 'Fernanda Lima', ['Redes de Computadores'], ['internet das coisas', 'computação em nuvem'], [[9.5, 9.0], [9.0, 9.5]]],
        ['Contagem de veículos com visão computacional', 'Uso de YOLOv8 em câmeras de trânsito para contar veículos por faixa e estimar o fluxo em cruzamentos.', 'Gustavo Pereira', ['Inteligência Artificial'], ['visão computacional'], [[7.0, 7.5], [8.0, 7.0]]],
    ];

    public function __construct(
        private readonly DocumentStorage $storage,
        private readonly RepositoryIndex $index,
        #[Autowire(param: 'app.timezone')] private readonly string $timezone,
    ) {
    }

    public static function getGroups(): array
    {
        return ['demo'];
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $advisor = $this->user($manager, 'orientador@hubtcc.dev');
        $evaluator1 = $this->user($manager, 'avaliador1@hubtcc.dev');
        $evaluator2 = $this->user($manager, 'avaliador2@hubtcc.dev');
        $coordination = $this->user($manager, 'coordenacao@hubtcc.dev');
        $professor = $this->user($manager, 'professor@hubtcc.dev');

        foreach (self::PUBLISHED as $i => [$title, $abstract, $studentName, $areas, $keywords, $grades]) {
            $student = $this->student($studentName);
            $tcc = $this->tcc($manager, $title, $abstract, $student, $i % 2 ? $professor : $advisor, $areas, $keywords);
            $this->approve($tcc, $student, [$evaluator1, $evaluator2], $grades, $coordination, new \DateTimeImmutable(\sprintf('-%d days', 20 + 15 * $i)));
            $tcc->authorizePublication(true);
            $tcc->publish(new \DateTimeImmutable(\sprintf('-%d days', 10 + 15 * $i)));
            $manager->persist($tcc);
        }

        // Em avaliação pela banca, com um parecer já dado
        $student = $this->student('Helena Costa');
        $tcc = $this->tcc($manager, 'Chatbot para atendimento em bibliotecas universitárias', 'Assistente conversacional que responde dúvidas frequentes e consulta o acervo.', $student, $advisor, ['Inteligência Artificial'], ['educação']);
        $board = $this->toBoard($tcc, $student, $advisor, [$evaluator1, $evaluator2]);
        $board[0]->giveVerdict(EvaluationVerdict::Approved, 'Bom trabalho, metodologia clara.');
        $manager->persist($tcc);

        // Aguardando a revisão do orientador
        $student = $this->student('Igor Santos');
        $tcc = $this->tcc($manager, 'Migração de monólitos para microsserviços', 'Estudo de caso da migração de um sistema acadêmico legado.', $student, $advisor, ['Engenharia de Software'], ['metodologias ágeis', 'computação em nuvem']);
        $this->submit($tcc, $student);
        $manager->persist($tcc);
        $tz = new \DateTimeZone($this->timezone);
        $now = new \DateTimeImmutable('now', $tz);
        $first = new AdvisingMeeting($tcc, $advisor, new \DateTimeImmutable('-21 days', $tz), 'Definimos o recorte: o módulo de matrículas será o primeiro a sair do monólito.', 'Levantar as dependências do módulo de matrículas.', $now);
        $first->confirm($now);
        $manager->persist($first);
        $manager->persist(new AdvisingMeeting($tcc, $advisor, new \DateTimeImmutable('-3 days', $tz), 'Revisamos o capítulo de metodologia e o plano de testes da migração.', 'Ajustar o cronograma e enviar a versão revisada.', $now));

        // Aguardando a defesa, já agendada
        $student = $this->student('Júlia Ferreira');
        $tcc = $this->tcc($manager, 'Gamificação no ensino de lógica de programação', 'Experimento com turmas do primeiro período usando missões e rankings.', $student, $professor, ['Interação Humano-Computador'], ['educação']);
        $this->toDefense($tcc, $student, $professor, [$evaluator1, $evaluator2]);
        $tcc->getCurrentDefense()?->schedule(new \DateTimeImmutable('+7 days 14:00', new \DateTimeZone($this->timezone)), 'Auditório do bloco B', null, $professor);
        $manager->persist($tcc);

        // Cronograma do semestre
        foreach ([['Projeto', 'Problema, objetivos e metodologia.', '-30 days'], ['Qualificação', 'Apresentação parcial ao orientador.', '+10 days'], ['Versão final', 'PDF completo para a banca.', '+45 days']] as [$name, $description, $when]) {
            $manager->persist(new Milestone($name, $description, new \DateTimeImmutable($when, new \DateTimeZone($this->timezone))));
        }

        $manager->flush();
        $this->index->rebuild();
    }

    private function user(ObjectManager $manager, string $email): User
    {
        $user = $manager->getRepository(User::class)->findOneBy(['email' => $email]);
        \assert($user instanceof User);

        return $user;
    }

    private function student(string $name): User
    {
        $email = strtolower(strtr(explode(' ', $name)[0], ['é' => 'e', 'ú' => 'u'])).'@hubtcc.dev';

        return UserFactory::createOne(['name' => $name, 'email' => $email, 'roles' => [Role::Student]]);
    }

    /**
     * @param list<string> $areas
     * @param list<string> $keywords
     */
    private function tcc(ObjectManager $manager, string $title, string $abstract, User $student, User $advisor, array $areas, array $keywords): Tcc
    {
        $tcc = new Tcc($title, $abstract, $student);
        $tcc->changeAdvisor($advisor);
        $tcc->replaceThematicAreas(array_map(fn (string $n): ThematicArea => $this->find($manager, ThematicArea::class, $n), $areas));
        $tcc->replaceKeywords(array_map(fn (string $n): Keyword => $this->find($manager, Keyword::class, $n), $keywords));
        $tcc->recordStatusChange(null, TccStatus::Draft, 'create', $student, 'TCC criado');
        $this->addPdf($tcc, $student);

        return $tcc;
    }

    /**
     * @template T of ThematicArea|Keyword
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function find(ObjectManager $manager, string $class, string $name): object
    {
        $item = $manager->getRepository($class)->findOneBy(['name' => $name]);
        \assert($item instanceof $class);

        return $item;
    }

    private function addPdf(Tcc $tcc, User $student): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'demo');
        file_put_contents($path, self::pdf($tcc->getTitle(), $student->getName()));
        $file = new File($path);
        $tcc->addDocument($this->storage->store($tcc, $file), 'tcc.pdf', (int) filesize($path), (string) hash_file('sha256', $path), $student);
        unlink($path);
    }

    private function move(Tcc $tcc, TccStatus $to, string $transition, ?User $by, ?string $comment = null): void
    {
        $tcc->recordStatusChange($tcc->getStatus(), $to, $transition, $by, $comment);
        $tcc->setMarking($to->value);
    }

    private function submit(Tcc $tcc, User $student): void
    {
        $this->move($tcc, TccStatus::Submitted, 'submit', $student);
    }

    /**
     * @param list<User> $evaluators
     *
     * @return list<BoardMember>
     */
    private function toBoard(Tcc $tcc, User $student, User $advisor, array $evaluators): array
    {
        $this->submit($tcc, $student);
        $board = array_map(static fn (User $e): BoardMember => $tcc->addBoardMember($e), $evaluators);
        $this->move($tcc, TccStatus::SentToEvaluation, 'advisor_approve', $advisor);

        return $board;
    }

    /** @param list<User> $evaluators */
    private function toDefense(Tcc $tcc, User $student, User $advisor, array $evaluators): void
    {
        foreach ($this->toBoard($tcc, $student, $advisor, $evaluators) as $member) {
            $member->giveVerdict(EvaluationVerdict::Approved, null);
        }
        $this->move($tcc, TccStatus::AdvisorFinalReview, 'board_approve', null, 'Banca concluída: todos aprovaram.');
        $this->move($tcc, TccStatus::AwaitingDefense, 'final_approve', $advisor);
        $tcc->openDefense();
    }

    /**
     * @param list<User>                      $evaluators
     * @param list<array{0: float, 1: float}> $grades
     */
    private function approve(Tcc $tcc, User $student, array $evaluators, array $grades, User $coordination, \DateTimeImmutable $defenseAt): void
    {
        $advisor = $tcc->getAdvisor();
        \assert(null !== $advisor);
        $this->toDefense($tcc, $student, $advisor, $evaluators);
        $defense = $tcc->getCurrentDefense();
        \assert(null !== $defense);
        $defense->schedule($defenseAt, 'Sala 101', null, $coordination);
        foreach ($tcc->getBoardMembers()->getValues() as $j => $member) {
            $defense->grade($member, $grades[$j][0], $grades[$j][1], null);
        }

        /** @var non-empty-list<string> $projects */
        $projects = array_map(static fn (array $g): string => Grade::normalize($g[0]), $grades);
        /** @var non-empty-list<string> $presentations */
        $presentations = array_map(static fn (array $g): string => Grade::normalize($g[1]), $grades);
        $final = Grade::average([...$projects, ...$presentations]);
        $defense->close(Grade::average($projects), Grade::average($presentations), $final, DefenseOutcome::Passed);
        $tcc->recordFinalGrade($final);
        $this->move($tcc, TccStatus::Approved, 'defense_pass', null, \sprintf('Defesa aprovada com média %s.', str_replace('.', ',', $final)));
    }

    /** PDF mínimo de uma página com o título, para o download funcionar na demonstração. */
    private static function pdf(string $title, string $author): string
    {
        $text = static fn (string $s): string => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) iconv('UTF-8', 'windows-1252//TRANSLIT', $s));
        $content = \sprintf('BT /F1 18 Tf 60 760 Td (%s) Tj 0 -30 Td /F1 12 Tf (%s) Tj 0 -20 Td (Documento de demonstracao do hubtcc.) Tj ET', $text($title), $text($author));
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            \sprintf("<< /Length %d >>\nstream\n%s\nendstream", \strlen($content), $content),
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $n => $object) {
            $offsets[] = \strlen($pdf);
            $pdf .= \sprintf("%d 0 obj\n%s\nendobj\n", $n + 1, $object);
        }
        $xref = \strlen($pdf);
        $pdf .= \sprintf("xref\n0 %d\n0000000000 65535 f \n", \count($objects) + 1);
        foreach ($offsets as $offset) {
            $pdf .= \sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.\sprintf("trailer << /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n", \count($objects) + 1, $xref);
    }
}
