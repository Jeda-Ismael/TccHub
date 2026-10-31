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

    /** @return iterable<string, array{non-empty-list<string>, string}> */
    public static function averages(): iterable
    {
        yield 'exata' => [['8.00', '9.50'], '8.75'];
        yield 'arredonda para cima no meio' => [['7.00', '7.00', '7.01'], '7.00'];
        yield 'dízima' => [['10.00', '10.00', '9.00'], '9.67'];
        yield 'meio centésimo sobe' => [['6.00', '6.01'], '6.01'];
        yield 'uma nota' => [['5.25'], '5.25'];
        yield 'zero' => [['0.00', '0.00'], '0.00'];
    }

    /** @param non-empty-list<string> $grades */
    #[DataProvider('averages')]
    public function testAverageInHundredths(array $grades, string $expected): void
    {
        self::assertSame($expected, Grade::average($grades));
    }

    public function testComparesWithTheMinimum(): void
    {
        self::assertTrue(Grade::isAtLeast('6.00', '6.00'));
        self::assertTrue(Grade::isAtLeast('10.00', '6.00'));
        self::assertFalse(Grade::isAtLeast('5.99', '6.00'));
    }
}
