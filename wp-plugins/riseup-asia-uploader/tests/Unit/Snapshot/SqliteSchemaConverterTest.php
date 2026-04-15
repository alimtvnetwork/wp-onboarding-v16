<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Snapshot;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Snapshot\SqliteSchemaConverter;

final class SqliteSchemaConverterTest extends TestCase
{
    public function testConvertReplacesIntTypes(): void
    {
        $mysql = "CREATE TABLE `wp_posts` (\n  `ID` BIGINT(20) NOT NULL,\n  `post_status` TINYINT(1) DEFAULT 0\n) ENGINE=InnoDB";
        $result = SqliteSchemaConverter::convert($mysql, 'wp_posts');

        $this->assertStringContainsString('INTEGER', $result);
        $this->assertStringNotContainsString('BIGINT', $result);
        $this->assertStringNotContainsString('TINYINT', $result);
    }

    public function testConvertReplacesVarcharWithText(): void
    {
        $mysql = "CREATE TABLE `t` (\n  `name` VARCHAR(255) NOT NULL\n)";
        $result = SqliteSchemaConverter::convert($mysql, 't');

        $this->assertStringContainsString('TEXT', $result);
        $this->assertStringNotContainsString('VARCHAR', $result);
    }

    public function testConvertRemovesEngineAttributes(): void
    {
        $mysql = "CREATE TABLE `t` (\n  `id` INT(11)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $result = SqliteSchemaConverter::convert($mysql, 't');

        $this->assertStringNotContainsString('ENGINE', $result);
        $this->assertStringNotContainsString('CHARSET', $result);
        $this->assertStringNotContainsString('COLLATE', $result);
    }

    public function testConvertReplacesAutoIncrement(): void
    {
        $mysql = "CREATE TABLE `t` (\n  `id` INT(11) AUTO_INCREMENT\n)";
        $result = SqliteSchemaConverter::convert($mysql, 't');

        $this->assertStringContainsString('AUTOINCREMENT', $result);
        $this->assertStringNotContainsString('AUTO_INCREMENT', $result);
    }

    public function testConvertReplacesFloatTypes(): void
    {
        $mysql = "CREATE TABLE `t` (\n  `price` DOUBLE,\n  `rate` FLOAT\n)";
        $result = SqliteSchemaConverter::convert($mysql, 't');

        $this->assertStringContainsString('REAL', $result);
        $this->assertStringNotContainsString('DOUBLE', $result);
        $this->assertStringNotContainsString('FLOAT', $result);
    }

    public function testConvertReplacesDatetimeWithText(): void
    {
        $mysql = "CREATE TABLE `t` (\n  `created` DATETIME,\n  `updated` TIMESTAMP\n)";
        $result = SqliteSchemaConverter::convert($mysql, 't');

        $this->assertStringNotContainsString('DATETIME', $result);
        $this->assertStringNotContainsString('TIMESTAMP', $result);
    }

    public function testConvertReplacesEnumWithText(): void
    {
        $mysql = "CREATE TABLE `t` (\n  `status` ENUM('draft','publish','trash')\n)";
        $result = SqliteSchemaConverter::convert($mysql, 't');

        $this->assertStringNotContainsString('ENUM', $result);
        $this->assertStringContainsString('TEXT', $result);
    }

    public function testConvertRemovesIndexDefinitions(): void
    {
        $mysql = "CREATE TABLE `t` (\n  `id` INT(11),\n  KEY `idx_id` (`id`),\n  UNIQUE KEY `uk_id` (`id`)\n)";
        $result = SqliteSchemaConverter::convert($mysql, 't');

        $this->assertStringNotContainsString('KEY `idx_id`', $result);
        $this->assertStringNotContainsString('UNIQUE KEY', $result);
    }

    public function testConvertCleansTrailingCommas(): void
    {
        $mysql = "CREATE TABLE `t` (\n  `id` INT(11),\n)";
        $result = SqliteSchemaConverter::convert($mysql, 't');

        $this->assertStringNotContainsString(',)', $result);
    }

    public function testConvertRemovesUnsigned(): void
    {
        $mysql = "CREATE TABLE `t` (\n  `id` INT(11) UNSIGNED\n)";
        $result = SqliteSchemaConverter::convert($mysql, 't');

        $this->assertStringNotContainsString('UNSIGNED', $result);
    }

    public function testConvertReplacesBlobTypes(): void
    {
        $mysql = "CREATE TABLE `t` (\n  `data` LONGBLOB,\n  `thumb` MEDIUMBLOB\n)";
        $result = SqliteSchemaConverter::convert($mysql, 't');

        $this->assertStringContainsString('BLOB', $result);
        $this->assertStringNotContainsString('LONGBLOB', $result);
        $this->assertStringNotContainsString('MEDIUMBLOB', $result);
    }

    public function testConvertReplacesBooleanWithInteger(): void
    {
        $mysql = "CREATE TABLE `t` (\n  `active` BOOLEAN DEFAULT 0\n)";
        $result = SqliteSchemaConverter::convert($mysql, 't');

        $this->assertStringContainsString('INTEGER', $result);
    }
}
