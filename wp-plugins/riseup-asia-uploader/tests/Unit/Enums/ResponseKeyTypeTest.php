<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\ResponseKeyType;

final class ResponseKeyTypeTest extends TestCase
{
    public function testAllValuesArePascalCaseOrAlphanumeric(): void
    {
        foreach (ResponseKeyType::cases() as $case) {
            $this->assertMatchesRegularExpression(
                '/^[A-Z][a-zA-Z0-9]*$/',
                $case->value,
                "ResponseKey {$case->name} value '{$case->value}' must be PascalCase (alphanumeric)",
            );
        }
    }

    public function testEnvelopeKeysExist(): void
    {
        $requiredKeys = ['Success', 'Error', 'Message', 'Data', 'Status'];

        foreach ($requiredKeys as $key) {
            $case = ResponseKeyType::tryFrom($key);
            $this->assertNotNull($case, "Required envelope key '{$key}' must exist");
        }
    }

    public function testNoDuplicateCaseNames(): void
    {
        $names = array_map(fn($c) => $c->name, ResponseKeyType::cases());
        $unique = array_unique($names);

        $this->assertCount(
            count($names),
            $unique,
            'ResponseKeyType has duplicate case names',
        );
    }

    public function testIsEqualComparison(): void
    {
        $this->assertTrue(ResponseKeyType::Success->isEqual(ResponseKeyType::Success));
        $this->assertFalse(ResponseKeyType::Success->isEqual(ResponseKeyType::Error));
    }
}
