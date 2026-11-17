<?php

declare(strict_types=1);

namespace App\Tests\Unit\Util;

use App\Exception\DomainRuleViolation;
use App\Util\Grade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GradeTest extends TestCase
{
    /** @return iterable<string, array{string|int|float, string}> */
    public static function validGrades(): iterable
    {
        yield 'zero' => [0, '0.00'];
        yield 'dez' => [10, '10.00'];
        yield 'float' => [8.5, '8.50'];
        yield 'string' => ['7.25', '7.25'];
        yield 'arredonda' => ['6.999', '7.00'];
    }

    #[DataProvider('validGrades')]
    public function testNormalizesToTwoDecimals(string|int|float $input, string $expected): void
    {
        self::assertSame($expected, Grade::normalize($input));
    }

    /** @return iterable<string, array{string|int|float}> */
    public static function invalidGrades(): iterable
    {
        yield 'negativa' => [-0.01];
        yield 'acima de 10' => [10.01];
        yield 'texto' => ['dez'];
    }

    #[DataProvider('invalidGrades')]
    public function testRejectsInvalidGrades(string|int|float $input): void
    {
        $this->expectException(DomainRuleViolation::class);

        Grade::normalize($input);
    }

    public function testMessageNamesTheField(): void
    {
        $this->expectExceptionMessage('A nota do projeto deve estar entre 0 e 10.');

        Grade::normalize(11, 'nota do projeto');
    }
}
