<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Factory\SubmissionPeriodFactory;
use App\Factory\UserFactory;
use App\Message\RemindPendingGrades;
use App\MessageHandler\RemindPendingGradesHandler;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Mime\Address;

/**
 * Etapa 3 do plano de melhorias: avisos por e-mail (pela fila) e lembrete de
 * notas pendentes depois da defesa.
 */
final class EmailTest extends ApiTestCase
{
    private User $student;
    private User $advisor;
    private User $evaluator1;
    private User $evaluator2;
    private User $coordination;

    protected function setUp(): void
    {
        parent::setUp();
        SubmissionPeriodFactory::new()->submission()->create();
        SubmissionPeriodFactory::new()->evaluation()->create();
        $this->student = UserFactory::new()->student()->create(['name' => 'Ana', 'email' => 'ana@hubtcc.dev']);
        $this->advisor = UserFactory::new()->advisor()->create(['name' => 'Olga', 'email' => 'olga@hubtcc.dev']);
        $this->evaluator1 = UserFactory::new()->evaluator()->create(['name' => 'Arthur', 'email' => 'arthur@hubtcc.dev']);
        $this->evaluator2 = UserFactory::new()->evaluator()->create(['name' => 'Alice', 'email' => 'alice@hubtcc.dev']);
        $this->coordination = UserFactory::new()->coordination()->create(['name' => 'Carla']);
    }

    private function id(User $user): string
    {
        return $user->getId()->toRfc4122();
    }

    /** @return list<TemplatedEmail> e-mails postos na fila pela última requisição */
    private function queuedEmails(): array
    {
        $transport = $this->client->getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $emails = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof SendEmailMessage && $message->getMessage() instanceof TemplatedEmail) {
                $emails[] = $message->getMessage();
            }
        }

        return $emails;
    }

    /** @return list<string> */
    private static function recipients(TemplatedEmail $email): array
    {
        return array_values(array_map(static fn (Address $a): string => $a->getAddress(), $email->getTo()));
    }

    private function createAndSubmit(): string
    {
        $token = $this->loginAs($this->student);
        $this->request('POST', '/api/tcc', ['title' => 'Detecção de fraudes', 'abstract' => 'Resumo', 'advisorId' => $this->id($this->advisor)], $token);
        $id = $this->json()['id'];
        $path = (string) tempnam(sys_get_temp_dir(), 'tcc');
        file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
        $this->client->request('POST', "/api/tcc/$id/document", [], ['file' => new UploadedFile($path, 'tcc.pdf', 'application/pdf', null, true)], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        $this->request('POST', "/api/tcc/$id/submit", token: $token);
        self::assertResponseIsSuccessful();

        return $id;
    }

    public function testNotificationAlsoGoesByEmailThroughTheQueue(): void
    {
        $this->createAndSubmit();

        $emails = $this->queuedEmails();
        self::assertCount(1, $emails);
        $email = $emails[0];
        self::assertSame(['olga@hubtcc.dev'], self::recipients($email));
        self::assertSame('[hubtcc] TCC enviado para revisão', $email->getSubject());
        self::assertSame('email/notification.html.twig', $email->getHtmlTemplate());
        $context = $email->getContext();
        self::assertSame('Olga', $context['name']);
        self::assertSame('Detecção de fraudes', $context['tccTitle']);
        self::assertSame('http://localhost:5173/', $context['link']);
        // O worker renderiza o e-mail na hora de enviar, com o mesmo texto do sininho
        $this->client->getContainer()->get('twig.mime_body_renderer')->render($email);
        self::assertStringContainsString('Ana enviou &quot;Detecção de fraudes&quot; para a sua revisão.', (string) $email->getHtmlBody());
        self::assertStringContainsString('Detecção de fraudes', (string) $email->getHtmlBody());
        self::assertStringContainsString('Abra o hubtcc: http://localhost:5173/', (string) $email->getTextBody());
    }

    public function testOnlyTheNewBoardMemberGetsTheEmail(): void
    {
        $id = $this->createAndSubmit();
        $this->request('PUT', '/api/users/'.$this->id($this->evaluator1).'/active', ['active' => false], $this->loginAs($this->coordination));
        self::assertResponseIsSuccessful();
        // Arthur desativado não entra na banca; a Alice entra e só ela recebe o e-mail
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator2)]], $this->loginAs($this->coordination));
        self::assertResponseIsSuccessful();
        self::assertSame([['alice@hubtcc.dev']], array_map(self::recipients(...), $this->queuedEmails()));
    }

    public function testBoardIsRemindedOnceToGradeAfterTheDefenseStarts(): void
    {
        $id = $this->createAndSubmit();
        $this->request('PUT', "/api/tcc/$id/evaluators", ['evaluatorIds' => [$this->id($this->evaluator1), $this->id($this->evaluator2)]], $this->loginAs($this->coordination));
        $advisorToken = $this->loginAs($this->advisor);
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => true], $advisorToken);
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->loginAs($this->evaluator1));
        $this->request('POST', "/api/tcc/$id/evaluate", ['verdict' => 'APPROVED'], $this->loginAs($this->evaluator2));
        $this->request('POST', "/api/tcc/$id/advisor-review", ['approve' => true], $advisorToken);

        $handler = static::getContainer()->get(RemindPendingGradesHandler::class);

        // Defesa amanhã: ainda não lembra ninguém
        $tomorrow = (new \DateTimeImmutable('+1 day'))->format(\DATE_ATOM);
        $this->request('PUT', "/api/tcc/$id/defense", ['scheduledAt' => $tomorrow, 'location' => 'Sala 101'], $advisorToken);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $handler(new RemindPendingGrades()));

        // Reagendada para uma hora atrás; Arthur já lançou a nota
        $past = (new \DateTimeImmutable('-1 hour'))->format(\DATE_ATOM);
        $this->request('PUT', "/api/tcc/$id/defense", ['scheduledAt' => $past, 'location' => 'Sala 101'], $advisorToken);
        $this->request('PUT', "/api/tcc/$id/defense/grade", ['projectGrade' => 9, 'presentationGrade' => 9], $this->loginAs($this->evaluator1));
        self::assertResponseIsSuccessful();

        self::assertSame(1, $handler(new RemindPendingGrades()));
        // Uma vez só
        self::assertSame(0, $handler(new RemindPendingGrades()));

        $this->request('GET', '/api/notifications', token: $this->loginAs($this->evaluator2));
        $latest = $this->json()['items'][0];
        self::assertSame('GRADES_PENDING', $latest['type']);
        self::assertSame('Lance as notas da defesa', $latest['title']);

        $this->request('GET', '/api/notifications', token: $this->loginAs($this->evaluator1));
        self::assertNotContains('GRADES_PENDING', array_column($this->json()['items'], 'type'));
    }
}
