<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\ResponseKeyType;

final class ResponseKeyTypeTest extends TestCase
{
    public function testAllValuesAreNonEmpty(): void
    {
        foreach (ResponseKeyType::cases() as $case) {
            $this->assertNotEmpty($case->value, "ResponseKey {$case->name} has empty value");
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
