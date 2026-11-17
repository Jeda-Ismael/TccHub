<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\UserFactory;

/** Cronograma do TCC: a coordenação define as etapas; todos veem qual é a próxima. */
final class MilestoneTest extends ApiTestCase
{
    public function testCoordinationManagesAndEveryoneSeesTheNextOne(): void
    {
        $token = $this->loginAs(UserFactory::new()->coordination()->create());
        $day = static fn (string $modifier): string => (new \DateTimeImmutable($modifier, new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');

        $this->request('POST', '/api/milestones', ['name' => 'Versão final', 'dueDate' => $day('+60 days')], $token);
        self::assertResponseStatusCodeSame(201);
        $this->request('POST', '/api/milestones', ['name' => 'Projeto', 'description' => '  Problema, objetivos e método.  ', 'dueDate' => $day('-10 days')], $token);
        $this->request('POST', '/api/milestones', ['name' => 'Qualificação', 'dueDate' => $day('today')], $token);
        $qualification = $this->json()['id'];

        $this->request('GET', '/api/milestones', token: $this->loginAs(UserFactory::new()->student()->create()));
        self::assertSame(
            [['Projeto', 'DONE', 'Problema, objetivos e método.'], ['Qualificação', 'NEXT', null], ['Versão final', 'UPCOMING', null]],
            array_map(static fn (array $m): array => [$m['name'], $m['status'], $m['description']], $this->json()),
        );

        $this->request('PUT', "/api/milestones/$qualification", ['name' => 'Qualificação (banca)', 'dueDate' => $day('+5 days')], $token);
        self::assertSame('Qualificação (banca)', $this->json()['name']);
        $this->request('DELETE', "/api/milestones/$qualification", token: $token);
        self::assertResponseStatusCodeSame(204);
        $this->request('GET', '/api/milestones', token: $token);
        self::assertSame(['DONE', 'NEXT'], array_column($this->json(), 'status'));
    }

    public function testOnlyCoordinationWritesAndValidation(): void
    {
        $this->request('POST', '/api/milestones', ['name' => 'Projeto', 'dueDate' => '2026-12-01'], $this->loginAs(UserFactory::new()->advisor()->create()));
        self::assertResponseStatusCodeSame(403);

        $token = $this->loginAs(UserFactory::new()->coordination()->create());
        $this->request('POST', '/api/milestones', ['name' => '', 'dueDate' => ''], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Informe o nome da etapa.', 'Informe a data limite.'], $this->json()['message']);
        $this->request('POST', '/api/milestones', ['name' => 'Projeto', 'dueDate' => '01/12/2026'], $token);
        self::assertSame('Data limite inválida.', $this->json()['message']);
        $this->request('PUT', '/api/milestones/0190a8c0-0000-7000-8000-000000000000', ['name' => 'X', 'dueDate' => '2026-12-01'], $token);
        self::assertResponseStatusCodeSame(404);
    }
}
