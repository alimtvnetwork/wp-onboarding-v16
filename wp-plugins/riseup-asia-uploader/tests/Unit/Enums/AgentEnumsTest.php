<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\AgentFieldType;
use RiseupAsia\Enums\AgentStatusType;
use RiseupAsia\Enums\SyncActionType;
use RiseupAsia\Enums\SyncEntryStatusType;
use RiseupAsia\Enums\PluginSelectionType;

final class AgentEnumsTest extends TestCase
{
    public function testAgentFieldValuesAreNonEmpty(): void
    {
        foreach (AgentFieldType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testAgentStatusValuesAreNonEmpty(): void
    {
        foreach (AgentStatusType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testSyncActionValuesAreNonEmpty(): void
    {
        foreach (SyncActionType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testSyncEntryStatusValuesAreNonEmpty(): void
    {
        foreach (SyncEntryStatusType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testPluginSelectionValuesAreNonEmpty(): void
    {
        foreach (PluginSelectionType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testNoDuplicateAgentStatuses(): void
    {
        $values = array_map(fn($c) => $c->value, AgentStatusType::cases());
        $this->assertCount(count($values), array_unique($values));
    }
}
