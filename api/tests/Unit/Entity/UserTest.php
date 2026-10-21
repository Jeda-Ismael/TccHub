<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Enum\Role;
use App\Exception\DomainRuleViolation;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testNewUserIsStudentByDefault(): void
    {
        $user = new User('  Ana  ', 'ANA@Hubtcc.dev ');

        self::assertSame('Ana', $user->getName());
        self::assertSame('ana@hubtcc.dev', $user->getEmail());
        self::assertSame('ana@hubtcc.dev', $user->getUserIdentifier());
        self::assertSame([Role::Student], $user->getRoleList());
        self::assertSame(['ROLE_STUDENT', 'ROLE_USER'], $user->getRoles());
        self::assertTrue($user->hasRole(Role::Student));
        self::assertFalse($user->hasRole(Role::Advisor));
        self::assertNull($user->getPassword());
        self::assertSame($user->getCreatedAt(), $user->getUpdatedAt());
    }

    public function testProfessorCanCombineRolesInCanonicalOrderWithoutDuplicates(): void
    {
        $user = new User('Paulo', 'paulo@hubtcc.dev', [Role::Coordination, Role::Advisor, Role::Evaluator, Role::Advisor]);

        self::assertSame([Role::Advisor, Role::Evaluator, Role::Coordination], $user->getRoleList());
    }

    public function testStudentRoleIsExclusive(): void
    {
        $user = new User('Ana', 'ana@hubtcc.dev');

        $this->expectException(DomainRuleViolation::class);
        $this->expectExceptionMessage('O papel Aluno não pode ser combinado com outros papéis.');

        $user->changeRoles([Role::Student, Role::Evaluator]);
    }

    public function testUserNeedsAtLeastOneRole(): void
    {
        $this->expectException(DomainRuleViolation::class);

        new User('Ana', 'ana@hubtcc.dev', []);
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectException(DomainRuleViolation::class);

        new User('Ana', 'nao-e-email');
    }

    public function testRejectsBlankName(): void
    {
        $this->expectException(DomainRuleViolation::class);

        new User('   ', 'ana@hubtcc.dev');
    }

    public function testStoresHashedPasswordAndTouchesUpdatedAt(): void
    {
        $user = new User('Ana', 'ana@hubtcc.dev');
        $before = $user->getUpdatedAt();

        $user->setPassword('$2y$hash');
        usleep(1000);
        $user->touch();
        $user->eraseCredentials();

        self::assertSame('$2y$hash', $user->getPassword());
        self::assertGreaterThan($before, $user->getUpdatedAt());
    }
}
