<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Factory\SubmissionPeriodFactory;
use App\Factory\UserFactory;

/** Reuniões de orientação: o orientador registra, o aluno confirma. */
final class MeetingTest extends ApiTestCase
{
    private User $student;
    private User $advisor;
    private User $otherAdvisor;
    private User $coordination;
    private string $tccId;

    protected function setUp(): void
    {
        parent::setUp();
        SubmissionPeriodFactory::new()->submission()->create();
        $this->student = UserFactory::new()->student()->create(['name' => 'Ana']);
        $this->advisor = UserFactory::new()->advisor()->create(['name' => 'Olga']);
        $this->otherAdvisor = UserFactory::new()->advisor()->create(['name' => 'Otto']);
        $this->coordination = UserFactory::new()->coordination()->create(['name' => 'Carla']);

        $this->request('POST', '/api/tcc', ['title' => 'Detecção de fraudes', 'abstract' => 'Resumo', 'advisorId' => $this->advisor->getId()->toRfc4122()], $this->loginAs($this->student));
        $this->tccId = $this->json()['id'];
    }

    public function testAdvisorRecordsAndStudentConfirms(): void
    {
        $advisorToken = $this->loginAs($this->advisor);
        $this->request('GET', "/api/tcc/{$this->tccId}", token: $advisorToken);
        self::assertTrue($this->json()['permissions']['recordMeeting']);

        $this->request('POST', "/api/tcc/{$this->tccId}/meetings", ['heldOn' => '2026-11-10', 'summary' => '  Definimos o recorte do problema.  ', 'nextSteps' => 'Ler 3 artigos'], $advisorToken);
        self::assertResponseStatusCodeSame(201);
        $meeting = $this->json();
        self::assertSame('2026-11-10', $meeting['heldOn']);
        self::assertSame('Definimos o recorte do problema.', $meeting['summary']);
        self::assertNull($meeting['confirmedAt']);
        self::assertSame(['confirm' => false, 'delete' => true], $meeting['permissions']);

        $studentToken = $this->loginAs($this->student);
        $this->request('GET', '/api/notifications', token: $studentToken);
        self::assertSame('MEETING_RECORDED', $this->json()['items'][0]['type']);
        self::assertStringContainsString('10/11/2026', $this->json()['items'][0]['body']);

        $this->request('GET', "/api/tcc/{$this->tccId}/meetings", token: $studentToken);
        self::assertCount(1, $this->json());
        self::assertSame(['confirm' => true, 'delete' => false], $this->json()[0]['permissions']);

        $this->request('POST', "/api/tcc/{$this->tccId}/meetings/{$meeting['id']}/confirm", token: $studentToken);
        self::assertResponseIsSuccessful();
        self::assertNotNull($this->json()['confirmedAt']);

        // Confirmada: não confirma de novo nem pode ser apagada
        $this->request('POST', "/api/tcc/{$this->tccId}/meetings/{$meeting['id']}/confirm", token: $studentToken);
        self::assertResponseStatusCodeSame(422);
        $this->request('DELETE', "/api/tcc/{$this->tccId}/meetings/{$meeting['id']}", token: $advisorToken);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('O aluno já confirmou esta reunião; ela não pode mais ser apagada.', $this->json()['message']);

        $this->request('GET', '/api/notifications', token: $advisorToken);
        self::assertSame('MEETING_CONFIRMED', $this->json()['items'][0]['type']);

        // A coordenação acompanha
        $this->request('GET', "/api/tcc/{$this->tccId}/meetings", token: $this->loginAs($this->coordination));
        self::assertCount(1, $this->json());
    }

    public function testOnlyTheAdvisorRecordsAndOnlyTheStudentConfirms(): void
    {
        $this->request('POST', "/api/tcc/{$this->tccId}/meetings", ['heldOn' => '2026-11-10', 'summary' => 'x'], $this->loginAs($this->student));
        self::assertResponseStatusCodeSame(403);
        $this->request('POST', "/api/tcc/{$this->tccId}/meetings", ['heldOn' => '2026-11-10', 'summary' => 'x'], $this->loginAs($this->coordination));
        self::assertResponseStatusCodeSame(403);
        // Quem não vê o TCC nem sabe que ele existe
        $this->request('GET', "/api/tcc/{$this->tccId}/meetings", token: $this->loginAs($this->otherAdvisor));
        self::assertResponseStatusCodeSame(404);

        $advisorToken = $this->loginAs($this->advisor);
        $this->request('POST', "/api/tcc/{$this->tccId}/meetings", ['heldOn' => '2026-11-10', 'summary' => 'Conversa'], $advisorToken);
        $id = $this->json()['id'];
        $this->request('POST', "/api/tcc/{$this->tccId}/meetings/$id/confirm", token: $advisorToken);
        self::assertResponseStatusCodeSame(403);

        // Antes de o aluno confirmar, o orientador pode apagar
        $this->request('DELETE', "/api/tcc/{$this->tccId}/meetings/$id", token: $advisorToken);
        self::assertResponseStatusCodeSame(204);
        $this->request('GET', "/api/tcc/{$this->tccId}/meetings", token: $advisorToken);
        self::assertSame([], $this->json());
    }

    public function testValidation(): void
    {
        $token = $this->loginAs($this->advisor);

        $this->request('POST', "/api/tcc/{$this->tccId}/meetings", ['heldOn' => '', 'summary' => ''], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Informe a data da reunião.', 'Escreva o que foi conversado na reunião.'], $this->json()['message']);

        $this->request('POST', "/api/tcc/{$this->tccId}/meetings", ['heldOn' => '2026-02-30', 'summary' => 'x'], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Data da reunião inválida.', $this->json()['message']);

        $this->request('POST', "/api/tcc/{$this->tccId}/meetings", ['heldOn' => (new \DateTimeImmutable('+3 days'))->format('Y-m-d'), 'summary' => 'x'], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('A reunião não pode ter data no futuro.', $this->json()['message']);
    }
}
