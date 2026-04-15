<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\PluginConfigType;

final class PluginConfigTypeTest extends TestCase
{
    public function testSlugIsKebabCase(): void
    {
        $slug = PluginConfigType::Slug->value;
        $isKebabCase = (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) === 1);

        $this->assertTrue($isKebabCase, "Slug '{$slug}' must be kebab-case");
    }

    public function testVersionFollowsSemver(): void
    {
        $version = PluginConfigType::Version->value;
        $isSemver = (preg_match('/^\d+\.\d+\.\d+$/', $version) === 1);

        $this->assertTrue($isSemver, "Version '{$version}' must follow semver (x.y.z)");
    }

    public function testApiFullNamespaceFormat(): void
    {
        $namespace = PluginConfigType::apiFullNamespace();

        $this->assertStringContainsString('/', $namespace);
        $this->assertStringEndsWith('/v1', $namespace);
    }

    public function testUploadsSubdirMatchesSlug(): void
    {
        $this->assertSame(PluginConfigType::Slug->value, PluginConfigType::uploadsSubdir());
    }

    public function testMinPhpVersionIsSemver(): void
    {
        $version = PluginConfigType::MinPhpVersion->value;
        $this->assertMatchesRegularExpression('/^\d+\.\d+$/', $version);
    }

    public function testMinWpVersionIsSemver(): void
    {
        $version = PluginConfigType::MinWpVersion->value;
        $this->assertMatchesRegularExpression('/^\d+\.\d+$/', $version);
    }

    public function testIsEqualComparison(): void
    {
        $this->assertTrue(PluginConfigType::Slug->isEqual(PluginConfigType::Slug));
        $this->assertFalse(PluginConfigType::Slug->isEqual(PluginConfigType::Version));
    }

    public function testIsOtherThanComparison(): void
    {
        $this->assertTrue(PluginConfigType::Slug->isOtherThan(PluginConfigType::Version));
        $this->assertFalse(PluginConfigType::Slug->isOtherThan(PluginConfigType::Slug));
    }

    public function testIsAnyOfComparison(): void
    {
        $this->assertTrue(PluginConfigType::Slug->isAnyOf(PluginConfigType::Slug, PluginConfigType::Name));
        $this->assertFalse(PluginConfigType::Slug->isAnyOf(PluginConfigType::Version, PluginConfigType::Name));
    }

    public function testAllCasesHaveNonEmptyValues(): void
    {
        foreach (PluginConfigType::cases() as $case) {
            $this->assertNotEmpty($case->value, "Case {$case->name} has empty value");
        }
    }
}
