<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Keyword;
use App\Entity\Tcc;
use App\Entity\ThematicArea;
use App\Entity\User;
use App\Enum\TccStatus;
use App\Factory\KeywordFactory;
use App\Factory\ThematicAreaFactory;
use App\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Fase 7: autorização do aluno, publicação pela coordenação e busca no repositório.
 */
final class RepositoryTest extends ApiTestCase
{
    private User $student;
    private User $advisor;
    private User $coordination;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = UserFactory::new()->student()->create(['name' => 'Ana Souza']);
        $this->advisor = UserFactory::new()->advisor()->create(['name' => 'Olga Lima']);
        $this->coordination = UserFactory::new()->coordination()->create(['name' => 'Carla']);
        // Qualquer pessoa logada, sem vínculo com o TCC
        $this->outsider = UserFactory::new()->student()->create(['name' => 'Bruno']);
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * TCC aprovado (atalho direto no banco: o fluxo até aqui é testado em DefenseFlowTest).
     *
     * @param list<ThematicArea> $areas
     * @param list<Keyword>      $keywords
     */
    private function approvedTcc(string $title, string $abstract, ?User $student = null, array $areas = [], array $keywords = []): string
    {
        $em = $this->em();
        $student = $em->find(User::class, ($student ?? $this->student)->getId());
        \assert($student instanceof User);
        $tcc = new Tcc($title, $abstract, $student);
        $tcc->changeAdvisor($em->find(User::class, $this->advisor->getId()));
        $tcc->replaceThematicAreas($areas);
        $tcc->replaceKeywords($keywords);
        $tcc->addDocument('tcc/x/v1.pdf', 'versao-1.pdf', 10, str_repeat('a', 64), $student);
        $tcc->addDocument('tcc/x/v2.pdf', 'versao-final.pdf', 20, str_repeat('b', 64), $student);
        $tcc->setMarking(TccStatus::Approved->value);
        $tcc->recordFinalGrade('8.63');
        $em->persist($tcc);
        $em->flush();

        return $tcc->getId()->toRfc4122();
    }

    private function publish(string $id, ?User $student = null): void
    {
        $this->request('PUT', "/api/tcc/$id/publication-authorization", ['authorized' => true], $this->loginAs($student ?? $this->student));
        self::assertResponseIsSuccessful();
        $this->request('POST', "/api/tcc/$id/publish", token: $this->loginAs($this->coordination));
        self::assertResponseIsSuccessful();
    }

    /** @return list<string> títulos encontrados */
    private function searchTitles(string $query = ''): array
    {
        $this->request('GET', '/api/repository'.('' === $query ? '' : '?'.$query), token: $this->loginAs($this->outsider));
        self::assertResponseIsSuccessful();

        return array_column($this->json()['items'], 'title');
    }

    public function testStudentAuthorizesAndCoordinationPublishes(): void
    {
        $id = $this->approvedTcc('Detecção de fraudes', 'Modelos de aprendizado de máquina');

        // Antes da autorização a coordenação não consegue publicar
        $coordToken = $this->loginAs($this->coordination);
        $this->request('GET', "/api/tcc/$id", token: $coordToken);
        self::assertTrue($this->json()['permissions']['publish']);
        $this->request('POST', "/api/tcc/$id/publish", token: $coordToken);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('O aluno ainda não autorizou a publicação.', $this->json()['message']);

        $studentToken = $this->loginAs($this->student);
        $this->request('PUT', "/api/tcc/$id/publication-authorization", ['authorized' => true], $studentToken);
        self::assertTrue($this->json()['publicationAuthorized']);
        self::assertNull($this->json()['publishedAt']);

        // A coordenação é avisada
        $this->request('GET', '/api/notifications', token: $coordToken);
        self::assertSame('PUBLICATION_REQUESTED', $this->json()['items'][0]['type']);

        $this->request('POST', "/api/tcc/$id/publish", token: $coordToken);
        self::assertNotNull($this->json()['publishedAt']);
        $this->request('GET', '/api/notifications', token: $studentToken);
        self::assertSame('TCC publicado no repositório', $this->json()['items'][0]['title']);

        self::assertSame(['Detecção de fraudes'], $this->searchTitles());
    }

    public function testOnlyTheAuthorAuthorizesAndOnlyCoordinationPublishes(): void
    {
        $id = $this->approvedTcc('TCC', 'Resumo');

        $this->request('PUT', "/api/tcc/$id/publication-authorization", ['authorized' => true], $this->loginAs($this->advisor));
        self::assertResponseStatusCodeSame(403);
        $this->request('PUT', "/api/tcc/$id/publication-authorization", ['authorized' => true], $this->loginAs($this->outsider));
        self::assertResponseStatusCodeSame(404);

        $this->request('PUT', "/api/tcc/$id/publication-authorization", ['authorized' => true], $this->loginAs($this->student));
        $this->request('POST', "/api/tcc/$id/publish", token: $this->loginAs($this->advisor));
        self::assertResponseStatusCodeSame(403);
        $this->request('POST', "/api/tcc/$id/publish", token: $this->loginAs($this->student));
        self::assertResponseStatusCodeSame(403);

        $this->request('PUT', "/api/tcc/$id/publication-authorization", [], $this->loginAs($this->student));
        self::assertResponseStatusCodeSame(422);
    }

    public function testOnlyApprovedTccsCanBePublished(): void
    {
        $id = $this->approvedTcc('Em andamento', 'Resumo');
        $tcc = $this->em()->find(Tcc::class, $id);
        \assert($tcc instanceof Tcc);
        $tcc->setMarking(TccStatus::AwaitingDefense->value);
        $this->em()->flush();

        $token = $this->loginAs($this->student);
        $this->request('GET', "/api/tcc/$id", token: $token);
        self::assertFalse($this->json()['permissions']['authorizePublication']);
        $this->request('PUT', "/api/tcc/$id/publication-authorization", ['authorized' => true], $token);
        self::assertResponseStatusCodeSame(403);
        $this->request('POST', "/api/tcc/$id/publish", token: $this->loginAs($this->coordination));
        self::assertResponseStatusCodeSame(403);
    }

    public function testWithdrawingTheAuthorizationRemovesItFromTheRepository(): void
    {
        $id = $this->approvedTcc('Detecção de fraudes', 'Resumo');
        $this->publish($id);

        $this->request('PUT', "/api/tcc/$id/publication-authorization", ['authorized' => false], $this->loginAs($this->student));
        self::assertNull($this->json()['publishedAt']);

        self::assertSame([], $this->searchTitles());
        $this->request('GET', "/api/repository/$id", token: $this->loginAs($this->outsider));
        self::assertResponseStatusCodeSame(404);
        $this->request('GET', '/api/notifications', token: $this->loginAs($this->coordination));
        self::assertSame('PUBLICATION_WITHDRAWN', $this->json()['items'][0]['type']);
    }

    public function testCoordinationCanUnpublish(): void
    {
        $id = $this->approvedTcc('Detecção de fraudes', 'Resumo');
        $this->publish($id);

        $this->request('DELETE', "/api/tcc/$id/publish", token: $this->loginAs($this->coordination));
        self::assertNull($this->json()['publishedAt']);
        // A autorização do aluno continua: dá para publicar de novo
        self::assertTrue($this->json()['publicationAuthorized']);
        self::assertSame([], $this->searchTitles());
    }

    public function testUnpublishedTccsNeverShowUp(): void
    {
        $this->approvedTcc('Aprovado sem autorização', 'Resumo');
        $authorized = $this->approvedTcc('Autorizado mas não publicado', 'Resumo');
        $this->request('PUT', "/api/tcc/$authorized/publication-authorization", ['authorized' => true], $this->loginAs($this->student));

        self::assertSame([], $this->searchTitles());
        self::assertSame([], $this->searchTitles('q=autorizado'));
        $this->request('GET', "/api/repository/$authorized", token: $this->loginAs($this->outsider));
        self::assertResponseStatusCodeSame(404);
    }

    public function testFullTextSearchInPortuguese(): void
    {
        $area = ThematicAreaFactory::createOne(['name' => 'Inteligência Artificial']);
        $keyword = KeywordFactory::createOne(['name' => 'visão computacional']);
        $fraud = $this->approvedTcc('Detecção de fraudes em cartões', 'Classificadores para transações bancárias.', areas: [$area]);
        $vision = $this->approvedTcc('Contagem de veículos', 'Câmeras de trânsito e redes neurais.', keywords: [$keyword]);
        $other = UserFactory::new()->student()->create(['name' => 'Zé Ninguém']);
        $web = $this->approvedTcc('Acessibilidade na web', 'Avaliação de sites de prefeituras.', student: $other);
        foreach ([$fraud, $vision] as $id) {
            $this->publish($id);
        }
        $this->publish($web, $other);

        // Radical: "fraude" acha "fraudes"; sem acento: "transacoes" acha "transações"
        self::assertSame(['Detecção de fraudes em cartões'], $this->searchTitles('q=fraude'));
        self::assertSame(['Detecção de fraudes em cartões'], $this->searchTitles('q=transacoes'));
        // Palavra-chave, área e nome do aluno também entram na busca
        self::assertSame(['Contagem de veículos'], $this->searchTitles('q=visao'));
        self::assertSame(['Detecção de fraudes em cartões'], $this->searchTitles('q=inteligencia artificial'));
        self::assertSame(['Acessibilidade na web'], $this->searchTitles('q=ninguem'));
        self::assertSame([], $this->searchTitles('q=blockchain'));

        // Filtros
        self::assertSame(['Detecção de fraudes em cartões'], $this->searchTitles('area='.$area->getId()->toRfc4122()));
        self::assertSame(['Contagem de veículos'], $this->searchTitles('keyword='.$keyword->getId()->toRfc4122()));
        self::assertCount(3, $this->searchTitles('year='.date('Y')));
        self::assertSame([], $this->searchTitles('year=1999'));
        $this->request('GET', '/api/repository?area=abc', token: $this->loginAs($this->outsider));
        self::assertResponseStatusCodeSame(422);

        // Sem texto: mais recentes primeiro, com os anos disponíveis
        $this->request('GET', '/api/repository', token: $this->loginAs($this->outsider));
        self::assertSame('Acessibilidade na web', $this->json()['items'][0]['title']);
        self::assertSame(3, $this->json()['total']);
        self::assertSame([(int) date('Y')], $this->json()['years']);
    }

    public function testTitleMatchesRankAboveAbstractMatches(): void
    {
        $inAbstract = $this->approvedTcc('Sistemas de recomendação', 'Uso de grafos para recomendação.');
        $inTitle = $this->approvedTcc('Grafos de conhecimento', 'Estudo de caso.');
        $this->publish($inAbstract);
        $this->publish($inTitle);

        self::assertSame(['Grafos de conhecimento', 'Sistemas de recomendação'], $this->searchTitles('q=grafos'));
    }

    public function testRenamingAKeywordUpdatesTheSearch(): void
    {
        $keyword = KeywordFactory::createOne(['name' => 'nuvem']);
        $id = $this->approvedTcc('Custos de infraestrutura', 'Resumo', keywords: [$keyword]);
        $this->publish($id);

        $this->request('PUT', '/api/keywords/'.$keyword->getId()->toRfc4122(), ['name' => 'computação serverless'], $this->loginAs($this->coordination));
        self::assertResponseIsSuccessful();

        self::assertSame(['Custos de infraestrutura'], $this->searchTitles('q=serverless'));
    }

    public function testPagination(): void
    {
        for ($i = 1; $i <= 14; ++$i) {
            $this->publish($this->approvedTcc("TCC $i", 'Resumo'));
        }

        $this->request('GET', '/api/repository?page=2', token: $this->loginAs($this->outsider));
        self::assertCount(2, $this->json()['items']);
        self::assertSame(14, $this->json()['total']);
        self::assertSame(12, $this->json()['pageSize']);
        $this->request('GET', '/api/repository?page=9', token: $this->loginAs($this->outsider));
        self::assertSame([], $this->json()['items']);
        self::assertSame(14, $this->json()['total']);
    }

    public function testDetailShowsOnlyPublicDataAndTheFinalPdf(): void
    {
        $id = $this->approvedTcc('Detecção de fraudes', 'Resumo');
        $this->publish($id);

        $this->request('GET', "/api/repository/$id", token: $this->loginAs($this->outsider));
        self::assertResponseIsSuccessful();
        $tcc = $this->json();
        self::assertSame('Ana Souza', $tcc['student']['name']);
        self::assertArrayNotHasKey('email', $tcc['student']);
        self::assertSame('Olga Lima', $tcc['advisor']['name']);
        self::assertEquals(8.63, $tcc['finalGrade']);
        self::assertSame(2, $tcc['document']['version']);
        self::assertSame('versao-final.pdf', $tcc['document']['originalName']);
        self::assertStringContainsString("/api/tcc/$id/documents/2/download", $tcc['document']['downloadUrl']);
        self::assertStringContainsString('_hash=', $tcc['document']['downloadUrl']);
        foreach (['board', 'statusHistory', 'documents', 'defense', 'permissions'] as $private) {
            self::assertArrayNotHasKey($private, $tcc);
        }
    }

    public function testRequiresLogin(): void
    {
        $this->request('GET', '/api/repository');
        self::assertResponseStatusCodeSame(401);
    }
}
