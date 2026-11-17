<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\SubmissionPeriodFactory;
use App\Factory\UserFactory;

final class PeriodAndSettingsTest extends ApiTestCase
{
    private function coordination(): string
    {
        return $this->loginAs(UserFactory::new()->coordination()->create());
    }

    public function testDateOnlyPeriodCoversTheWholeDayInTheInstitutionTimezone(): void
    {
        $this->request('POST', '/api/periods', ['type' => 'SUBMISSION', 'label' => '2026/2', 'startDate' => '2026-08-01', 'endDate' => '2026-12-15'], $this->coordination());

        self::assertResponseStatusCodeSame(201);
        self::assertSame('2026-08-01T00:00:00-03:00', $this->json()['startDate']);
        self::assertSame('2026-12-15T23:59:59-03:00', $this->json()['endDate']);
        self::assertSame('SUBMISSION', $this->json()['type']);
    }

    public function testListsPeriodsWithActiveFlag(): void
    {
        SubmissionPeriodFactory::new()->submission()->create();
        SubmissionPeriodFactory::new()->evaluation()->expired()->create();

        $this->request('GET', '/api/periods', token: $this->loginAs(UserFactory::new()->student()->create()));

        self::assertResponseIsSuccessful();
        $active = array_column($this->json(), 'active', 'type');
        self::assertSame(['SUBMISSION' => true, 'EVALUATION' => false], $active);
    }

    public function testUpdatesAndDeletesPeriod(): void
    {
        $period = SubmissionPeriodFactory::createOne();
        $id = $period->getId()->toRfc4122();
        $token = $this->coordination();

        $this->request('PUT', "/api/periods/$id", ['type' => 'EVALUATION', 'label' => 'Bancas', 'startDate' => '2026-01-01', 'endDate' => '2026-02-01'], $token);
        self::assertResponseIsSuccessful();
        self::assertSame('Bancas', $this->json()['label']);

        $this->request('DELETE', "/api/periods/$id", token: $token);
        self::assertResponseStatusCodeSame(204);
    }

    public function testPeriodValidation(): void
    {
        $token = $this->coordination();

        $this->request('POST', '/api/periods', ['type' => 'FERIAS', 'label' => '', 'startDate' => '2026-01-01', 'endDate' => '2026-02-01'], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertEqualsCanonicalizing(['Tipo de prazo inválido.', 'Informe o nome do prazo.'], $this->json()['message']);

        $this->request('POST', '/api/periods', ['type' => 'SUBMISSION', 'label' => 'X', 'startDate' => '2026-03-01', 'endDate' => '2026-02-01'], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('A data de término deve ser depois da data de início.', $this->json()['message']);

        $this->request('POST', '/api/periods', ['type' => 'SUBMISSION', 'label' => 'X', 'startDate' => 'amanhã', 'endDate' => '2026-02-01'], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Data de início inválida.', $this->json()['message']);

        // Datas que não existem não viram outra data em silêncio (31/02 viraria 03/03)
        $this->request('POST', '/api/periods', ['type' => 'SUBMISSION', 'label' => 'X', 'startDate' => '2026-01-01', 'endDate' => '2026-02-31'], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Data de término inválida.', $this->json()['message']);
    }

    public function testOnlyCoordinationManagesPeriods(): void
    {
        $this->request('POST', '/api/periods', ['type' => 'SUBMISSION', 'label' => 'X', 'startDate' => '2026-01-01', 'endDate' => '2026-02-01'], $this->loginAs(UserFactory::new()->advisor()->create()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testReadsAndUpdatesSettings(): void
    {
        $token = $this->coordination();

        $this->request('GET', '/api/settings', token: $token);
        self::assertEquals(['boardMinSize' => 2, 'boardMaxSize' => 3, 'passingGrade' => 6], $this->json());

        $this->request('PUT', '/api/settings', ['boardMinSize' => 1, 'boardMaxSize' => 4, 'passingGrade' => 7.5], $token);
        self::assertResponseIsSuccessful();
        self::assertSame(['boardMinSize' => 1, 'boardMaxSize' => 4, 'passingGrade' => 7.5], $this->json());
    }

    public function testSettingsValidation(): void
    {
        $token = $this->coordination();

        $this->request('PUT', '/api/settings', ['boardMinSize' => 3, 'boardMaxSize' => 2, 'passingGrade' => 6], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('O tamanho máximo da banca não pode ser menor que o mínimo.', $this->json()['message']);

        $this->request('PUT', '/api/settings', ['boardMinSize' => 2, 'boardMaxSize' => 3, 'passingGrade' => 11], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['A nota mínima deve estar entre 0 e 10.'], $this->json()['message']);
    }

    public function testOnlyCoordinationUpdatesSettings(): void
    {
        $this->request('PUT', '/api/settings', ['boardMinSize' => 1, 'boardMaxSize' => 1, 'passingGrade' => 5], $this->loginAs(UserFactory::new()->evaluator()->create()));

        self::assertResponseStatusCodeSame(403);
    }
}
