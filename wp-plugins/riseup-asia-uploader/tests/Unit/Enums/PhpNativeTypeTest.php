<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\PhpNativeType;

final class PhpNativeTypeTest extends TestCase
{
    #[DataProvider('matchesProvider')]
    public function testIsMatchesReturnsCorrectResult(
        PhpNativeType $type,
        mixed $value,
        bool $expected,
    ): void {
        $this->assertSame($expected, $type->isMatches($value));
    }

    /**
     * @return array<string, array{PhpNativeType, mixed, bool}>
     */
    public static function matchesProvider(): array
    {
        return [
            'array matches array'       => [PhpNativeType::PhpArray, [1, 2], true],
            'array rejects string'      => [PhpNativeType::PhpArray, 'hello', false],
            'string matches string'     => [PhpNativeType::PhpString, 'hello', true],
            'string rejects int'        => [PhpNativeType::PhpString, 42, false],
            'integer matches int'       => [PhpNativeType::PhpInteger, 42, true],
            'integer rejects float'     => [PhpNativeType::PhpInteger, 3.14, false],
            'double matches float'      => [PhpNativeType::PhpDouble, 3.14, true],
            'double rejects int'        => [PhpNativeType::PhpDouble, 42, false],
            'boolean matches bool'      => [PhpNativeType::PhpBoolean, true, true],
            'boolean rejects string'    => [PhpNativeType::PhpBoolean, 'true', false],
            'null matches null'         => [PhpNativeType::PhpNull, null, true],
            'null rejects empty string' => [PhpNativeType::PhpNull, '', false],
            'object matches object'     => [PhpNativeType::PhpObject, new \stdClass(), true],
            'object rejects array'      => [PhpNativeType::PhpObject, [], false],
        ];
    }

    public function testAllCasesHaveValidGettypeValue(): void
    {
        $validTypes = ['array', 'string', 'integer', 'double', 'boolean', 'object', 'NULL'];

        foreach (PhpNativeType::cases() as $case) {
            $this->assertContains(
                $case->value,
                $validTypes,
                "Enum case {$case->name} has invalid gettype value: {$case->value}",
            );
        }
    }
}
