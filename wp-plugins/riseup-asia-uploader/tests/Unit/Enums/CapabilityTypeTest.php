<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\CapabilityType;

final class CapabilityTypeTest extends TestCase
{
    public function testAllValuesAreSnakeCase(): void
    {
        foreach (CapabilityType::cases() as $case) {
            $this->assertMatchesRegularExpression(
                '/^[a-z]+(_[a-z]+)*$/',
                $case->value,
                "Capability {$case->name} value '{$case->value}' must be snake_case",
            );
        }
    }

    public function testManageOptionsExists(): void
    {
        $this->assertSame('manage_options', CapabilityType::ManageOptions->value);
    }

    public function testIsEqualComparison(): void
    {
        $this->assertTrue(CapabilityType::ManageOptions->isEqual(CapabilityType::ManageOptions));
        $this->assertFalse(CapabilityType::ManageOptions->isEqual(CapabilityType::EditPosts));
    }

    public function testIsAnyOfComparison(): void
    {
        $this->assertTrue(CapabilityType::ManageOptions->isAnyOf(
            CapabilityType::ManageOptions,
            CapabilityType::ActivatePlugins,
        ));
    }
}
