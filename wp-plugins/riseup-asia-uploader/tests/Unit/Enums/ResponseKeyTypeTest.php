<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\ResponseKeyType;

final class ResponseKeyTypeTest extends TestCase
{
    public function testAllValuesArePascalCase(): void
    {
        foreach (ResponseKeyType::cases() as $case) {
            $this->assertMatchesRegularExpression(
                '/^[A-Z][a-zA-Z]*$/',
                $case->value,
                "ResponseKey {$case->name} value '{$case->value}' must be PascalCase",
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

    public function testNoDuplicateValues(): void
    {
        $values = array_map(fn($c) => $c->value, ResponseKeyType::cases());
        $unique = array_unique($values);

        $this->assertCount(
            count($values),
            $unique,
            'ResponseKeyType has duplicate values: ' . implode(', ', array_diff_assoc($values, $unique)),
        );
    }

    public function testIsEqualComparison(): void
    {
        $this->assertTrue(ResponseKeyType::Success->isEqual(ResponseKeyType::Success));
        $this->assertFalse(ResponseKeyType::Success->isEqual(ResponseKeyType::Error));
    }
}
