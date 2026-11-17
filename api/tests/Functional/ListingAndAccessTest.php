<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Tcc;
use App\Entity\User;
use App\Enum\Role;
use App\Exception\DomainRuleViolation;
use App\Factory\SubmissionPeriodFactory;
use App\Factory\TccFactory;
use App\Factory\UserFactory;
use App\Service\UserManager;

/**
 * Etapa 2 do plano de melhorias: busca e paginação nas listas e desativação
 * de usuários (tira o acesso, mantém o histórico).
 */
final class ListingAndAccessTest extends ApiTestCase
{
    private User $coordination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coordination = UserFactory::new()->coordination()->create(['name' => 'Carla']);
    }

    private function header(string $name): ?string
    {
        return $this->client->getResponse()->headers->get($name);
    }

    public function testTccListIsPaginatedWithTotalInHeaders(): void
    {
        TccFactory::createMany(5);
        $token = $this->loginAs($this->coordination);

        $this->request('GET', '/api/tcc?as=COORDINATION&page=1&perPage=2', token: $token);
        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->json());
        self::assertSame('5', $this->header('X-Total-Count'));
        self::assertSame('1', $this->header('X-Page'));
        self::assertSame('2', $this->header('X-Per-Page'));
        $first = array_column($this->json(), 'id');

        $this->request('GET', '/api/tcc?as=COORDINATION&page=3&perPage=2', token: $token);
        self::assertCount(1, $this->json());
        self::assertSame([], array_intersect($first, array_column($this->json(), 'id')));

        // Sem page, a lista vem inteira (compatível com as telas antigas)
        $this->request('GET', '/api/tcc?as=COORDINATION', token: $token);
        self::assertCount(5, $this->json());
        self::assertSame('5', $this->header('X-Total-Count'));
        self::assertNull($this->header('X-Page'));
    }

    public function testTccListSearchesTitleStudentAndAdvisor(): void
    {
        $advisor = UserFactory::new()->advisor()->create(['name' => 'Zuleika Orientadora']);
        TccFactory::createOne(['title' => 'Detecção de fraudes em cartões']);
        TccFactory::createOne(['title' => 'Outro tema', 'student' => UserFactory::new()->student()->create(['name' => 'Xerxes Lima'])]);
        TccFactory::new()->afterInstantiate(static fn (Tcc $t) => $t->changeAdvisor($advisor))->create(['title' => 'Terceiro tema']);
        $token = $this->loginAs($this->coordination);

        $this->request('GET', '/api/tcc?as=COORDINATION&q=FRAUDES', token: $token);
        self::assertSame(['Detecção de fraudes em cartões'], array_column($this->json(), 'title'));

        $this->request('GET', '/api/tcc?as=COORDINATION&q=xerxes', token: $token);
        self::assertSame(['Outro tema'], array_column($this->json(), 'title'));

        $this->request('GET', '/api/tcc?as=COORDINATION&q=zuleika', token: $token);
        self::assertSame(['Terceiro tema'], array_column($this->json(), 'title'));

        // Curinga do LIKE é texto comum
        $this->request('GET', '/api/tcc?as=COORDINATION&q=%25', token: $token);
        self::assertSame([], $this->json());
    }

    public function testInvalidPaginationIsABadRequest(): void
    {
        $token = $this->loginAs($this->coordination);

        $this->request('GET', '/api/tcc?as=COORDINATION&page=0', token: $token);
        self::assertResponseStatusCodeSame(400);

        $this->request('GET', '/api/users?page=1&perPage=500', token: $token);
        self::assertResponseStatusCodeSame(400);
    }

    public function testCoordinationSearchesAndPaginatesUsers(): void
    {
        UserFactory::new()->advisor()->create(['name' => 'Olga Orientadora', 'email' => 'olga@hubtcc.dev']);
        UserFactory::new()->evaluator()->create(['name' => 'Arthur', 'email' => 'arthur@hubtcc.dev']);
        UserFactory::new()->evaluator()->create(['name' => 'Alice', 'email' => 'alice@hubtcc.dev']);
        $token = $this->loginAs($this->coordination);

        $this->request('GET', '/api/users?q=OLGA', token: $token);
        self::assertSame(['Olga Orientadora'], array_column($this->json(), 'name'));

        $this->request('GET', '/api/users?q=arthur@', token: $token);
        self::assertSame(['Arthur'], array_column($this->json(), 'name'));

        $this->request('GET', '/api/users?page=2&perPage=3', token: $token);
        self::assertSame(['Olga Orientadora'], array_column($this->json(), 'name'));
        self::assertSame('4', $this->header('X-Total-Count'));
    }

    public function testDeactivatedUserCannotLogInAndKeepsHistory(): void
    {
        $advisor = UserFactory::new()->advisor()->create(['name' => 'Olga']);
        TccFactory::new()->afterInstantiate(static fn (Tcc $t) => $t->changeAdvisor($advisor))->create();
        $advisorToken = $this->loginAs($advisor);

        $this->request('PUT', '/api/users/'.$advisor->getId().'/active', ['active' => false], $this->loginAs($this->coordination));
        self::assertResponseIsSuccessful();
        self::assertFalse($this->json()['active']);

        // O token que já existia para de valer
        $this->request('GET', '/api/tcc?as=ADVISOR', token: $advisorToken);
        self::assertResponseStatusCodeSame(401);

        $this->request('POST', '/api/auth/login', ['email' => $advisor->getEmail(), 'password' => UserFactory::DEFAULT_PASSWORD]);
        self::assertResponseStatusCodeSame(401);
        self::assertSame('Este usuário foi desativado. Fale com a coordenação.', $this->json()['message']);

        // Com a senha errada, a resposta é a de sempre (não revela que a conta existe)
        $this->request('POST', '/api/auth/login', ['email' => $advisor->getEmail(), 'password' => 'senha-errada']);
        self::assertSame('E-mail ou senha incorretos.', $this->json()['message']);

        // O TCC continua com o orientador
        $this->request('GET', '/api/tcc?as=COORDINATION', token: $this->loginAs($this->coordination));
        self::assertSame('Olga', $this->json()[0]['advisor']['name']);

        // Reativado, volta a entrar
        $this->request('PUT', '/api/users/'.$advisor->getId().'/active', ['active' => true], $this->loginAs($this->coordination));
        self::assertTrue($this->json()['active']);
        $this->loginAs($advisor);
    }

    public function testDeactivatedUserCannotRenewTheSession(): void
    {
        $advisor = UserFactory::new()->advisor()->create();
        $this->loginAs($advisor);
        $cookie = $this->client->getCookieJar()->get('refresh_token', '/api/auth');
        self::assertNotNull($cookie);

        $coordinationToken = $this->loginAs($this->coordination);
        $this->request('PUT', '/api/users/'.$advisor->getId().'/active', ['active' => false], $coordinationToken);
        self::assertResponseIsSuccessful();

        // Volta o cookie do orientador (o login da coordenação trocou o cookie do cliente)
        $this->client->getCookieJar()->set($cookie);
        $this->request('POST', '/api/auth/refresh');
        self::assertResponseStatusCodeSame(401);
    }

    public function testDeactivatedUsersAreLeftOutOfPickersButCoordinationCanSeeThem(): void
    {
        SubmissionPeriodFactory::new()->submission()->create();
        UserFactory::new()->advisor()->create(['name' => 'Olga']);
        $old = UserFactory::new()->advisor()->afterInstantiate(static fn (User $u) => $u->deactivate())->create(['name' => 'Rui']);
        $student = UserFactory::new()->student()->create();

        $this->request('GET', '/api/users?role=ADVISOR', token: $this->loginAs($student));
        self::assertSame(['Olga'], array_column($this->json(), 'name'));

        $token = $this->loginAs($this->coordination);
        $this->request('GET', '/api/users?role=ADVISOR', token: $token);
        self::assertSame(['Olga'], array_column($this->json(), 'name'));

        $this->request('GET', '/api/users?role=ADVISOR&includeInactive=1', token: $token);
        self::assertSame(['Olga', 'Rui'], array_column($this->json(), 'name'));
        self::assertFalse($this->json()[1]['active']);

        // Não entra como orientador de um TCC novo
        $this->request('POST', '/api/tcc', ['title' => 'Um TCC', 'abstract' => 'Resumo do TCC', 'advisorId' => (string) $old->getId()], $this->loginAs($student));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('desativado', $this->json()['message']);
    }

    public function testDeactivatedEvaluatorCannotJoinABoard(): void
    {
        $tcc = TccFactory::createOne();
        $evaluator = UserFactory::new()->evaluator()->afterInstantiate(static fn (User $u) => $u->deactivate())->create(['name' => 'Rui']);

        $this->request('PUT', '/api/tcc/'.$tcc->getId().'/evaluators', ['evaluatorIds' => [(string) $evaluator->getId()]], $this->loginAs($this->coordination));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Rui está desativado e não pode entrar na banca.', $this->json()['message']);
    }

    public function testCannotDeactivateYourselfOrTheLastActiveCoordination(): void
    {
        $token = $this->loginAs($this->coordination);

        $this->request('PUT', '/api/users/'.$this->coordination->getId().'/active', ['active' => false], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Você não pode desativar o seu próprio usuário.', $this->json()['message']);

        $other = UserFactory::new()->withRoles([Role::Coordination])->create();
        $this->request('PUT', '/api/users/'.$other->getId().'/active', ['active' => false], $token);
        self::assertResponseIsSuccessful();

        // Agora só a Carla está ativa na coordenação. Pela API isso nunca chega
        // a acontecer (quem desativa é outra coordenação ativa), mas o serviço
        // também protege o caso
        $this->expectException(DomainRuleViolation::class);
        $this->expectExceptionMessage('O sistema precisa ter pelo menos um usuário ativo da Coordenação.');
        static::getContainer()->get(UserManager::class)->setActive($this->coordination, false, $other);
    }

    public function testOnlyCoordinationChangesAccessAndPayloadIsValidated(): void
    {
        $student = UserFactory::new()->student()->create();

        $this->request('PUT', '/api/users/'.$this->coordination->getId().'/active', ['active' => false], $this->loginAs($student));
        self::assertResponseStatusCodeSame(403);

        $this->request('PUT', '/api/users/'.$student->getId().'/active', [], $this->loginAs($this->coordination));
        self::assertResponseStatusCodeSame(422);
    }
}
