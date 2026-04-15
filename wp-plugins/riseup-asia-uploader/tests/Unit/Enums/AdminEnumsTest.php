<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\AdminTabType;
use RiseupAsia\Enums\AjaxActionType;
use RiseupAsia\Enums\NonceType;
use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Enums\SettingsKeyType;
use RiseupAsia\Enums\UserMetaKeyType;
use RiseupAsia\Enums\UserRoleType;

final class AdminEnumsTest extends TestCase
{
    public function testAdminPageValuesAreNonEmpty(): void
    {
        foreach (AdminPageType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testAdminTabValuesAreNonEmpty(): void
    {
        foreach (AdminTabType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testAjaxActionValuesAreNonEmpty(): void
    {
        foreach (AjaxActionType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testNonceValuesAreNonEmpty(): void
    {
        foreach (NonceType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testOptionNameValuesAreNonEmpty(): void
    {
        foreach (OptionNameType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testSettingsKeyValuesAreNonEmpty(): void
    {
        foreach (SettingsKeyType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testUserMetaKeyValuesAreNonEmpty(): void
    {
        foreach (UserMetaKeyType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testUserRoleValuesAreNonEmpty(): void
    {
        foreach (UserRoleType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testNoDuplicateAjaxActions(): void
    {
        $values = array_map(fn($c) => $c->value, AjaxActionType::cases());
        $this->assertCount(count($values), array_unique($values), 'AjaxActionType has duplicate values');
    }

    public function testNoDuplicateNonceValues(): void
    {
        $values = array_map(fn($c) => $c->value, NonceType::cases());
        $this->assertCount(count($values), array_unique($values), 'NonceType has duplicate values');
    }
}
