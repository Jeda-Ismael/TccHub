<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\Role;
use App\Factory\TccFactory;
use App\Factory\UserFactory;
use App\Repository\UserRepository;

final class UserManagementTest extends ApiTestCase
{
    private User $coordination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coordination = UserFactory::new()->coordination()->create(['name' => 'Carla']);
    }

    private function coordinationToken(): string
    {
        return $this->loginAs($this->coordination);
    }

    public function testStudentListsAdvisorsByRoleSeeingOnlyNameAndEmail(): void
    {
        UserFactory::new()->advisor()->create(['name' => 'Olga']);
        UserFactory::new()->withRoles([Role::Advisor, Role::Evaluator])->create(['name' => 'Paulo']);
        UserFactory::new()->evaluator()->create(['name' => 'Arthur']);
        $student = UserFactory::new()->student()->create();

        $this->request('GET', '/api/users?role=ADVISOR', token: $this->loginAs($student));

        self::assertResponseIsSuccessful();
        self::assertSame(['Olga', 'Paulo'], array_column($this->json(), 'name'));
        self::assertSame(['id', 'name', 'email'], array_keys($this->json()[0]));
    }

    public function testOnlyCoordinationListsEveryoneWithRoles(): void
    {
        $student = UserFactory::new()->student()->create();

        $this->request('GET', '/api/users', token: $this->loginAs($student));
        self::assertResponseStatusCodeSame(403);
        self::assertSame('Você não tem permissão para executar esta ação.', $this->json()['message']);

        $this->request('GET', '/api/users', token: $this->coordinationToken());
        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->json());
        self::assertArrayHasKey('roles', $this->json()[0]);
    }

    public function testUnknownRoleFilterIsABadRequest(): void
    {
        $this->request('GET', '/api/users?role=ADMIN', token: $this->coordinationToken());

        self::assertResponseStatusCodeSame(400);
    }

    public function testCoordinationCreatesUserWithSeveralRoles(): void
    {
        $this->request('POST', '/api/users', ['name' => 'Paulo', 'email' => 'paulo@hubtcc.dev', 'password' => 'senha-forte', 'roles' => ['EVALUATOR', 'ADVISOR']], $this->coordinationToken());

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['ADVISOR', 'EVALUATOR'], $this->json()['roles']);
        $this->login('paulo@hubtcc.dev', 'senha-forte');
    }

    public function testCreatingUserValidatesRoles(): void
    {
        $token = $this->coordinationToken();

        $this->request('POST', '/api/users', ['name' => 'X', 'email' => 'x@hubtcc.dev', 'password' => 'senha-forte', 'roles' => ['ADMIN']], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Papel inválido.'], $this->json()['message']);

        $this->request('POST', '/api/users', ['name' => 'X', 'email' => 'x@hubtcc.dev', 'password' => 'senha-forte', 'roles' => ['STUDENT', 'EVALUATOR']], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('O papel Aluno não pode ser combinado com outros papéis.', $this->json()['message']);
    }

    public function testOnlyCoordinationManagesUsers(): void
    {
        $advisor = UserFactory::new()->advisor()->create();
        $token = $this->loginAs($advisor);
        $id = $this->coordination->getId()->toRfc4122();

        $this->request('POST', '/api/users', ['name' => 'X', 'email' => 'x@hubtcc.dev', 'password' => 'senha-forte', 'roles' => ['ADVISOR']], $token);
        self::assertResponseStatusCodeSame(403);
        $this->request('PUT', "/api/users/$id/roles", ['roles' => ['ADVISOR']], $token);
        self::assertResponseStatusCodeSame(403);
        $this->request('DELETE', "/api/users/$id", token: $token);
        self::assertResponseStatusCodeSame(403);
    }

    public function testChangesRoles(): void
    {
        $user = UserFactory::new()->advisor()->create();

        $this->request('PUT', '/api/users/'.$user->getId()->toRfc4122().'/roles', ['roles' => ['ADVISOR', 'COORDINATION']], $this->coordinationToken());

        self::assertResponseIsSuccessful();
        self::assertSame(['ADVISOR', 'COORDINATION'], $this->json()['roles']);
    }

    public function testCannotRemoveTheLastCoordination(): void
    {
        $id = $this->coordination->getId()->toRfc4122();
        $token = $this->coordinationToken();

        $this->request('PUT', "/api/users/$id/roles", ['roles' => ['ADVISOR']], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('O sistema precisa ter pelo menos um usuário da Coordenação.', $this->json()['message']);

        // Com um segundo coordenador (o professor com três papéis), a troca é permitida
        UserFactory::new()->withRoles([Role::Advisor, Role::Coordination])->create();
        $this->request('PUT', "/api/users/$id/roles", ['roles' => ['ADVISOR']], $token);
        self::assertResponseIsSuccessful();
    }

    public function testDeletesUser(): void
    {
        $user = UserFactory::new()->evaluator()->create();
        $id = $user->getId();

        $this->request('DELETE', '/api/users/'.$id->toRfc4122(), token: $this->coordinationToken());

        self::assertResponseStatusCodeSame(204);
        self::assertNull(self::getContainer()->get(UserRepository::class)->find($id));
    }

    public function testCannotDeleteYourself(): void
    {
        $this->request('DELETE', '/api/users/'.$this->coordination->getId()->toRfc4122(), token: $this->coordinationToken());

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Você não pode excluir o seu próprio usuário.', $this->json()['message']);
    }

    public function testCannotDeleteUserWithLinkedTcc(): void
    {
        $tcc = TccFactory::createOne();

        $this->request('DELETE', '/api/users/'.$tcc->getStudent()->getId()->toRfc4122(), token: $this->coordinationToken());

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Não é possível excluir', $this->json()['message']);
    }

    public function testUnknownUserIs404(): void
    {
        $this->request('DELETE', '/api/users/01a15bf5-0000-7000-8000-000000000000', token: $this->coordinationToken());

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Recurso não encontrado.', $this->json()['message']);
    }
}
