<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Database;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tests database seeding patterns following Phase 9 §9.14.
 *
 * Uses in-memory SQLite to verify seed fixture loading,
 * version tracking, and strategy behavior.
 */
final class DatabaseSeederTest extends TestCase
{
    private PDO $pdo;
    private string $seedDir;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        // Create seed_history tracking table
        $this->pdo->exec('
            CREATE TABLE seed_history (
                Id              INTEGER PRIMARY KEY AUTOINCREMENT,
                SeedFile        TEXT    NOT NULL UNIQUE,
                LastSeededVer   TEXT    NOT NULL,
                SeededAt        TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Create target table for seed data
        $this->pdo->exec('
            CREATE TABLE test_settings (
                Id           INTEGER PRIMARY KEY AUTOINCREMENT,
                SettingKey   TEXT NOT NULL UNIQUE,
                SettingValue TEXT NOT NULL
            )
        ');

        $this->seedDir = __DIR__ . '/../../Fixtures/seeds';
    }

    // ── Fixture files exist ─────────────────────────────────

    public function testManifestFixtureExists(): void
    {
        $path = $this->seedDir . '/manifest.json';

        $this->assertFileExists($path);

        $manifest = json_decode(file_get_contents($path), true);

        $this->assertArrayHasKey('version', $manifest);
        $this->assertArrayHasKey('seeds', $manifest);
        $this->assertNotEmpty($manifest['seeds']);
    }

    public function testSettingsFixtureExists(): void
    {
        $path = $this->seedDir . '/settings.json';

        $this->assertFileExists($path);

        $data = json_decode(file_get_contents($path), true);

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    // ── Manual seeding simulation ───────────────────────────

    public function testInsertIfEmptyPopulatesEmptyTable(): void
    {
        $seedData = $this->loadSeedData('settings.json');
        $count = $this->getRowCount('test_settings');

        // Only insert if table is empty
        $isEmpty = ($count === 0);
        $this->assertTrue($isEmpty);

        $this->insertSeedRows('test_settings', $seedData);

        $this->assertSame(2, $this->getRowCount('test_settings'));
    }

    public function testInsertIfEmptySkipsNonEmptyTable(): void
    {
        // Pre-populate
        $this->pdo->exec(
            "INSERT INTO test_settings (SettingKey, SettingValue)
             VALUES ('ExistingKey', 'ExistingValue')"
        );

        $seedData = $this->loadSeedData('settings.json');
        $count = $this->getRowCount('test_settings');
        $isEmpty = ($count === 0);

        // Should NOT insert
        $this->assertFalse($isEmpty);

        if ($isEmpty) {
            $this->insertSeedRows('test_settings', $seedData);
        }

        $this->assertSame(1, $this->getRowCount('test_settings'), 'insert_if_empty must skip non-empty tables');
    }

    // ── Version tracking ────────────────────────────────────

    public function testSeedingRecordsVersionInHistory(): void
    {
        $this->recordSeedVersion('settings.json', '1.0.0');

        $stmt = $this->pdo->prepare(
            "SELECT LastSeededVer FROM seed_history WHERE SeedFile = ?"
        );
        $stmt->execute(['settings.json']);
        $version = $stmt->fetchColumn();

        $this->assertSame('1.0.0', $version);
    }

    public function testSameVersionDoesNotReseed(): void
    {
        // Seed and record version
        $seedData = $this->loadSeedData('settings.json');
        $this->insertSeedRows('test_settings', $seedData);
        $this->recordSeedVersion('settings.json', '1.0.0');

        // Modify data manually
        $this->pdo->exec(
            "UPDATE test_settings SET SettingValue = 'CHANGED'
             WHERE SettingKey = 'ColorPrimary'"
        );

        // Check version — same version should skip
        $shouldReseed = $this->shouldReseed('settings.json', '1.0.0');
        $this->assertFalse($shouldReseed, 'Same version must NOT trigger re-seed');

        // Verify manual change preserved
        $stmt = $this->pdo->prepare(
            "SELECT SettingValue FROM test_settings WHERE SettingKey = 'ColorPrimary'"
        );
        $stmt->execute();
        $value = $stmt->fetchColumn();

        $this->assertSame('CHANGED', $value);
    }

    public function testNewerVersionTriggersReseed(): void
    {
        $this->recordSeedVersion('settings.json', '1.0.0');

        $shouldReseed = $this->shouldReseed('settings.json', '1.1.0');

        $this->assertTrue($shouldReseed, 'Newer version must trigger re-seed');
    }

    public function testOlderVersionDoesNotReseed(): void
    {
        $this->recordSeedVersion('settings.json', '2.0.0');

        $shouldReseed = $this->shouldReseed('settings.json', '1.0.0');

        $this->assertFalse($shouldReseed, 'Older version must NOT trigger re-seed');
    }

    // ── Seed data integrity ─────────────────────────────────

    public function testSeedDataHasExpectedColumns(): void
    {
        $seedData = $this->loadSeedData('settings.json');

        foreach ($seedData as $row) {
            $this->assertArrayHasKey('SettingKey', $row);
            $this->assertArrayHasKey('SettingValue', $row);
        }
    }

    public function testSeedKeysAreUnique(): void
    {
        $seedData = $this->loadSeedData('settings.json');
        $keys = array_column($seedData, 'SettingKey');
        $uniqueKeys = array_unique($keys);

        $this->assertCount(count($keys), $uniqueKeys, 'Seed keys must be unique');
    }

    public function testManifestReferencesExistingFiles(): void
    {
        $manifest = json_decode(
            file_get_contents($this->seedDir . '/manifest.json'),
            true,
        );

        foreach ($manifest['seeds'] as $entry) {
            $filePath = $this->seedDir . '/' . $entry['file'];
            $this->assertFileExists($filePath, "Manifest references missing file: {$entry['file']}");
        }
    }

    // ── Edge cases ──────────────────────────────────────────

    public function testEmptySeedArrayInsertsNothing(): void
    {
        $this->insertSeedRows('test_settings', []);

        $this->assertSame(0, $this->getRowCount('test_settings'));
    }

    public function testMalformedJsonReturnsNull(): void
    {
        $tmpFile = sys_get_temp_dir() . '/bad_seed_' . uniqid() . '.json';
        file_put_contents($tmpFile, '{ invalid json !!!');

        $data = json_decode(file_get_contents($tmpFile), true);

        $this->assertNull($data, 'Malformed JSON must return null from json_decode');

        unlink($tmpFile);
    }

    // ── Helpers ─────────────────────────────────────────────

    private function loadSeedData(string $filename): array
    {
        $path = $this->seedDir . '/' . $filename;
        $json = file_get_contents($path);

        return json_decode($json, true) ?? [];
    }

    private function insertSeedRows(string $table, array $rows): void
    {
        foreach ($rows as $row) {
            $columns = array_keys($row);
            $placeholders = array_map(fn($c) => ':' . $c, $columns);

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $table,
                implode(', ', $columns),
                implode(', ', $placeholders),
            );

            $stmt = $this->pdo->prepare($sql);

            foreach ($row as $col => $val) {
                $stmt->bindValue(':' . $col, $val);
            }

            $stmt->execute();
        }
    }

    private function getRowCount(string $table): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$table}");

        return (int) $stmt->fetchColumn();
    }

    private function recordSeedVersion(string $seedFile, string $version): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT OR REPLACE INTO seed_history (SeedFile, LastSeededVer)
             VALUES (?, ?)'
        );
        $stmt->execute([$seedFile, $version]);
    }

    private function shouldReseed(string $seedFile, string $currentVersion): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT LastSeededVer FROM seed_history WHERE SeedFile = ?'
        );
        $stmt->execute([$seedFile]);
        $lastVersion = $stmt->fetchColumn();

        if ($lastVersion === false) {
            return true;
        }

        return version_compare($currentVersion, $lastVersion, '>');
    }
}
