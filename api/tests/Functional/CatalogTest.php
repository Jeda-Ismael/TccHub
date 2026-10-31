<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\KeywordFactory;
use App\Factory\TccFactory;
use App\Factory\ThematicAreaFactory;
use App\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;

final class CatalogTest extends ApiTestCase
{
    private function coordination(): string
    {
        return $this->loginAs(UserFactory::new()->coordination()->create());
    }

    public function testAnyLoggedUserListsCatalogsWithUsage(): void
    {
        $ia = ThematicAreaFactory::createOne(['name' => 'IA']);
        ThematicAreaFactory::createOne(['name' => 'Banco de Dados']);
        $tcc = TccFactory::createOne();
        $tcc->replaceThematicAreas([$ia]);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->request('GET', '/api/thematic-areas', token: $this->loginAs(UserFactory::new()->student()->create()));

        self::assertResponseIsSuccessful();
        self::assertSame(
            [['name' => 'Banco de Dados', 'tccCount' => 0], ['name' => 'IA', 'tccCount' => 1]],
            array_map(static fn (array $row) => ['name' => $row['name'], 'tccCount' => $row['tccCount']], $this->json()),
        );
    }

    public function testListingRequiresLogin(): void
    {
        $this->request('GET', '/api/keywords');

        self::assertResponseStatusCodeSame(401);
    }

    public function testCoordinationCreatesRenamesAndDeletes(): void
    {
        $token = $this->coordination();

        $this->request('POST', '/api/keywords', ['name' => '  nuvem '], $token);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('nuvem', $this->json()['name']);
        $id = $this->json()['id'];

        $this->request('PUT', "/api/keywords/$id", ['name' => 'computação em nuvem'], $token);
        self::assertResponseIsSuccessful();
        self::assertSame('computação em nuvem', $this->json()['name']);

        $this->request('DELETE', "/api/keywords/$id", token: $token);
        self::assertResponseStatusCodeSame(204);
    }

    public function testRejectsDuplicateNamesIgnoringCase(): void
    {
        ThematicAreaFactory::createOne(['name' => 'Redes']);

        $this->request('POST', '/api/thematic-areas', ['name' => 'REDES'], $this->coordination());

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Já existe uma área temática com este nome.', $this->json()['message']);
    }

    public function testRenamingToItsOwnNameIsAllowed(): void
    {
        $area = ThematicAreaFactory::createOne(['name' => 'Redes']);

        $this->request('PUT', '/api/thematic-areas/'.$area->getId()->toRfc4122(), ['name' => 'Redes'], $this->coordination());

        self::assertResponseIsSuccessful();
    }

    public function testCannotDeleteWhatIsInUse(): void
    {
        $keyword = KeywordFactory::createOne(['name' => 'web']);
        $tcc = TccFactory::createOne();
        $tcc->replaceKeywords([$keyword]);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->request('DELETE', '/api/keywords/'.$keyword->getId()->toRfc4122(), token: $this->coordination());

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Não é possível excluir: esta palavra-chave está em uso por 1 TCC(s).', $this->json()['message']);
    }

    public function testValidatesName(): void
    {
        $this->request('POST', '/api/keywords', ['name' => ''], $this->coordination());

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Informe o nome.'], $this->json()['message']);
    }

    public function testOnlyCoordinationWrites(): void
    {
        $token = $this->loginAs(UserFactory::new()->advisor()->create());

        $this->request('POST', '/api/keywords', ['name' => 'x'], $token);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnknownItemIs404(): void
    {
        $this->request('DELETE', '/api/keywords/01a15bf5-0000-7000-8000-000000000000', token: $this->coordination());

        self::assertResponseStatusCodeSame(404);
    }
}
