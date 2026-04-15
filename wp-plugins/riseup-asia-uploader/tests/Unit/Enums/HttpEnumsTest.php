<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\HttpConfigType;
use RiseupAsia\Enums\HttpHeaderType;
use RiseupAsia\Enums\ContentTypeValueType;

final class HttpEnumsTest extends TestCase
{
    public function testHttpConfigHasIntValues(): void
    {
        foreach (HttpConfigType::cases() as $case) {
            $this->assertIsInt($case->value);
            $this->assertGreaterThan(0, $case->value, "{$case->name} must be positive");
        }
    }

    public function testHttpHeaderValuesAreNonEmpty(): void
    {
        foreach (HttpHeaderType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testContentTypeValuesContainSlash(): void
    {
        foreach (ContentTypeValueType::cases() as $case) {
            $this->assertStringContainsString(
                '/',
                $case->value,
                "ContentType {$case->name} value '{$case->value}' must contain a slash (type/subtype)",
            );
        }
    }
}
