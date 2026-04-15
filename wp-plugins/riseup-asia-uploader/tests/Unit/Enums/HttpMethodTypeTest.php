<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\HttpMethodType;

final class HttpMethodTypeTest extends TestCase
{
    public function testAllMethodsAreUpperCase(): void
    {
        foreach (HttpMethodType::cases() as $case) {
            $this->assertSame(
                strtoupper($case->value),
                $case->value,
                "HTTP method {$case->name} must be uppercase",
            );
        }
    }

    public function testStandardMethodsExist(): void
    {
        $expected = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        $actual = array_map(fn($c) => $c->value, HttpMethodType::cases());

        $this->assertSame($expected, $actual);
    }

    public function testEditableReturnsCommaSeparatedString(): void
    {
        $editable = HttpMethodType::editable();

        $this->assertStringContainsString('PUT', $editable);
        $this->assertStringContainsString('PATCH', $editable);
    }

    public function testIsEqualComparison(): void
    {
        $this->assertTrue(HttpMethodType::Get->isEqual(HttpMethodType::Get));
        $this->assertFalse(HttpMethodType::Get->isEqual(HttpMethodType::Post));
    }
}
