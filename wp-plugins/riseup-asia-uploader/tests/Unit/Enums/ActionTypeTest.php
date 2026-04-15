<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\ActionType;

final class ActionTypeTest extends TestCase
{
    public function testAllValuesArePascalCase(): void
    {
        foreach (ActionType::cases() as $case) {
            $this->assertMatchesRegularExpression(
                '/^[A-Z][a-zA-Z]+$/',
                $case->value,
                "ActionType {$case->name} value '{$case->value}' must be PascalCase",
            );
        }
    }

    public function testGroupHelperIsSnapshot(): void
    {
        $this->assertTrue(ActionType::SnapshotCreate->isSnapshot());
        $this->assertTrue(ActionType::SnapshotRestore->isSnapshot());
        $this->assertFalse(ActionType::Upload->isSnapshot());
    }

    public function testGroupHelperIsAgent(): void
    {
        $this->assertTrue(ActionType::AgentAdd->isAgent());
        $this->assertTrue(ActionType::AgentSync->isAgent());
        $this->assertFalse(ActionType::Upload->isAgent());
    }

    public function testGroupHelperIsCloudStorage(): void
    {
        $this->assertTrue(ActionType::CloudStorageUpload->isCloudStorage());
        $this->assertFalse(ActionType::Upload->isCloudStorage());
    }

    public function testLifecycleGroup(): void
    {
        $this->assertTrue(ActionType::Enable->isLifecycle());
        $this->assertTrue(ActionType::Disable->isLifecycle());
        $this->assertTrue(ActionType::Delete->isLifecycle());
        $this->assertFalse(ActionType::Upload->isLifecycle());
    }

    public function testContentGroup(): void
    {
        $this->assertTrue(ActionType::PostCreate->isContent());
        $this->assertTrue(ActionType::MediaUpload->isContent());
        $this->assertFalse(ActionType::Enable->isContent());
    }

    public function testPerCaseIsHelpers(): void
    {
        $this->assertTrue(ActionType::Upload->isUpload());
        $this->assertTrue(ActionType::AuthFailed->isAuthFailed());
        $this->assertTrue(ActionType::ExportSelf->isExportSelf());
        $this->assertFalse(ActionType::Upload->isDelete());
    }

    public function testAllCasesHaveLabels(): void
    {
        foreach (ActionType::cases() as $case) {
            $label = $case->label();
            $this->assertNotEmpty($label, "Case {$case->name} must have a label");
        }
    }

    public function testIsEqualComparison(): void
    {
        $this->assertTrue(ActionType::Upload->isEqual(ActionType::Upload));
        $this->assertFalse(ActionType::Upload->isEqual(ActionType::Delete));
    }

    public function testNoDuplicateValues(): void
    {
        $values = array_map(fn($c) => $c->value, ActionType::cases());
        $unique = array_unique($values);

        $this->assertCount(count($values), $unique, 'ActionType has duplicate values');
    }
}
