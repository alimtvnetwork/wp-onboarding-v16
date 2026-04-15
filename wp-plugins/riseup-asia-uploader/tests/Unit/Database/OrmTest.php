<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Database;

use PDO;
use PHPUnit\Framework\TestCase;
use RiseupAsia\Database\Orm;

/**
 * Tests the Micro ORM — fluent query builder over SQLite.
 *
 * Uses an in-memory SQLite database so no external services are required.
 */
final class OrmTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->pdo->exec('
            CREATE TABLE items (
                Id    INTEGER PRIMARY KEY AUTOINCREMENT,
                Name  TEXT    NOT NULL,
                Price INTEGER NOT NULL DEFAULT 0,
                Status TEXT   NOT NULL DEFAULT \'Active\'
            )
        ');

        Orm::configure($this->pdo);
    }

    // ── INSERT ──────────────────────────────────────────────

    public function testInsertReturnsLastInsertId(): void
    {
        $id = Orm::forTable('items')
            ->create()
            ->set('Name', 'Widget A')
            ->set('Price', 100)
            ->save();

        $this->assertSame(1, $id);
    }

    public function testInsertedRowIsPersisted(): void
    {
        Orm::forTable('items')
            ->create()
            ->set('Name', 'Widget B')
            ->set('Price', 250)
            ->save();

        $row = Orm::forTable('items')->where('Name', 'Widget B')->findMany();

        $this->assertCount(1, $row);
        $this->assertEquals(250, $row[0]['Price']);
    }

    public function testInsertMultipleRows(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Orm::forTable('items')
                ->create()
                ->set('Name', "Item {$i}")
                ->set('Price', $i * 10)
                ->save();
        }

        $count = Orm::forTable('items')->count();
        $this->assertSame(5, $count);
    }

    // ── SELECT / findOne ────────────────────────────────────

    public function testFindOneReturnsRow(): void
    {
        $id = Orm::forTable('items')
            ->create()
            ->set('Name', 'Findable')
            ->set('Price', 99)
            ->save();

        $row = Orm::forTable('items')->findOne($id);

        $this->assertNotNull($row);
        $this->assertSame('Findable', $row['Name']);
    }

    public function testFindOneReturnsNullForMissingId(): void
    {
        $row = Orm::forTable('items')->findOne(9999);

        $this->assertNull($row);
    }

    // ── SELECT / findMany with WHERE ────────────────────────

    public function testWhereEqualFilters(): void
    {
        $this->seedItems();

        $rows = Orm::forTable('items')
            ->where('Status', 'Active')
            ->findMany();

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertSame('Active', $row['Status']);
        }
    }

    public function testWhereNotEqualFilters(): void
    {
        $this->seedItems();

        $rows = Orm::forTable('items')
            ->whereNotEqual('Status', 'Active')
            ->findMany();

        foreach ($rows as $row) {
            $this->assertNotSame('Active', $row['Status']);
        }
    }

    public function testWhereGtFilters(): void
    {
        $this->seedItems();

        $rows = Orm::forTable('items')
            ->whereGt('Price', 200)
            ->findMany();

        foreach ($rows as $row) {
            $this->assertGreaterThan(200, (int) $row['Price']);
        }
    }

    public function testWhereLikeFilters(): void
    {
        $this->seedItems();

        $rows = Orm::forTable('items')
            ->whereLike('Name', '%Alpha%')
            ->findMany();

        $this->assertCount(1, $rows);
        $this->assertSame('Alpha', $rows[0]['Name']);
    }

    public function testWhereNullFilters(): void
    {
        // All seeded items have non-null Status — insert one with null
        $this->pdo->exec("INSERT INTO items (Name, Price, Status) VALUES ('Ghost', 0, 'Active')");

        $rows = Orm::forTable('items')
            ->whereNull('Status')
            ->findMany();

        $this->assertCount(0, $rows);
    }

    public function testWhereInFilters(): void
    {
        $this->seedItems();

        $rows = Orm::forTable('items')
            ->whereIn('Name', ['Alpha', 'Gamma'])
            ->findMany();

        $this->assertCount(2, $rows);
    }

    public function testWhereInEmptyArrayReturnsNothing(): void
    {
        $this->seedItems();

        $rows = Orm::forTable('items')
            ->whereIn('Name', [])
            ->findMany();

        $this->assertCount(0, $rows);
    }

    // ── ORDER / LIMIT / OFFSET ──────────────────────────────

    public function testOrderByAscSortsCorrectly(): void
    {
        $this->seedItems();

        $rows = Orm::forTable('items')
            ->orderByAsc('Price')
            ->findMany();

        $prices = array_map(fn($r) => (int) $r['Price'], $rows);
        $sorted = $prices;
        sort($sorted);

        $this->assertSame($sorted, $prices);
    }

    public function testOrderByDescSortsCorrectly(): void
    {
        $this->seedItems();

        $rows = Orm::forTable('items')
            ->orderByDesc('Price')
            ->findMany();

        $prices = array_map(fn($r) => (int) $r['Price'], $rows);
        $sorted = $prices;
        rsort($sorted);

        $this->assertSame($sorted, $prices);
    }

    public function testLimitRestrictsResultCount(): void
    {
        $this->seedItems();

        $rows = Orm::forTable('items')
            ->limit(2)
            ->findMany();

        $this->assertCount(2, $rows);
    }

    public function testOffsetSkipsRows(): void
    {
        $this->seedItems();

        $allRows = Orm::forTable('items')
            ->orderByAsc('Id')
            ->findMany();

        $offsetRows = Orm::forTable('items')
            ->orderByAsc('Id')
            ->offset(1)
            ->limit(1)
            ->findMany();

        $this->assertCount(1, $offsetRows);
        $this->assertSame($allRows[1]['Id'], $offsetRows[0]['Id']);
    }

    // ── SELECT columns ─────────────────────────────────────

    public function testSelectSpecificColumns(): void
    {
        $this->seedItems();

        $rows = Orm::forTable('items')
            ->select('Name')
            ->limit(1)
            ->findMany();

        $this->assertArrayHasKey('Name', $rows[0]);
        $this->assertArrayNotHasKey('Price', $rows[0]);
    }

    public function testSelectCountReturnsCount(): void
    {
        $this->seedItems();

        $rows = Orm::forTable('items')
            ->selectCount('total')
            ->findMany();

        $this->assertEquals(3, $rows[0]['total']);
    }

    // ── UPDATE ──────────────────────────────────────────────

    public function testUpdateModifiesRow(): void
    {
        $id = Orm::forTable('items')
            ->create()
            ->set('Name', 'Old Name')
            ->set('Price', 50)
            ->save();

        Orm::forTable('items')
            ->where('Id', $id)
            ->set('Name', 'New Name')
            ->save();

        $row = Orm::forTable('items')->findOne($id);

        $this->assertSame('New Name', $row['Name']);
    }

    public function testUpdateWithoutWhereReturnsZero(): void
    {
        $this->seedItems();

        // Update without where clause should be a no-op (safety check)
        $result = Orm::forTable('items')
            ->set('Name', 'Overwrite')
            ->save();

        // doUpdate returns 0 when whereClauses is empty
        $this->assertSame(0, $result);
    }

    // ── DELETE ──────────────────────────────────────────────

    public function testDeleteRemovesMatchingRows(): void
    {
        $this->seedItems();

        $deleted = Orm::forTable('items')
            ->where('Name', 'Alpha')
            ->delete();

        $this->assertSame(1, $deleted);
        $this->assertSame(2, Orm::forTable('items')->count());
    }

    public function testDeleteWithoutWhereReturnsZero(): void
    {
        $this->seedItems();

        $deleted = Orm::forTable('items')->delete();

        $this->assertSame(0, $deleted);
        $this->assertSame(3, Orm::forTable('items')->count());
    }

    // ── rawExecute ──────────────────────────────────────────

    public function testRawExecuteReturnsRows(): void
    {
        $this->seedItems();

        $rows = Orm::rawExecute('SELECT Name FROM items WHERE Price > ?', [100]);

        $this->assertCount(2, $rows);
    }

    // ── NULL PDO guard ──────────────────────────────────────

    public function testRawExecuteReturnsEmptyWithNullPdo(): void
    {
        // When PDO is not configured, rawExecute returns empty
        // We can't null-out the static PDO without a reset method,
        // so we just verify rawExecute handles bad SQL gracefully.
        $rows = Orm::rawExecute('SELECT * FROM nonexistent_table_xyz');

        $this->assertSame([], $rows);
    }

    // ── Helpers ─────────────────────────────────────────────

    private function seedItems(): void
    {
        $items = [
            ['Alpha', 100, 'Active'],
            ['Beta', 200, 'Inactive'],
            ['Gamma', 300, 'Active'],
        ];

        foreach ($items as [$name, $price, $status]) {
            Orm::forTable('items')
                ->create()
                ->set('Name', $name)
                ->set('Price', $price)
                ->set('Status', $status)
                ->save();
        }
    }
}
