# 13 - Snapshot Service

> **Status:** Complete  
> **Priority:** Critical  
> **Updated:** 2026-01-31

---

## Purpose

Provides full system backup and restore capabilities through SQLite database snapshots. Enables recovery from catastrophic changes by capturing complete state of all content tables at a point in time.

---

## Snapshot Storage Structure

```
wp-content/uploads/link-manager/
├── snapshots/
│   ├── 001-initial-backup-2026-01-15.db
│   ├── 002-before-bulk-removal-2026-01-20.db
│   └── 003-manual-checkpoint-2026-01-31.db
└── link-manager.db (main database)
```

### Snapshot Naming Convention

```
{sequence}-{name}-{date}.db

Examples:
- 001-initial-2026-01-15.db
- 002-pre-title-cleanup-2026-01-20.db
- 003-user-snapshot-2026-01-31.db
```

---

## Core Interfaces

```php
<?php
declare(strict_types=1);

namespace LinkManager\Snapshot;

/**
 * Represents a saved snapshot
 */
interface SnapshotInterface
{
    public function getId(): int;
    public function getSequence(): int;
    public function getName(): string;
    public function getFilePath(): string;
    public function getCreatedAt(): \DateTimeImmutable;
    public function getSizeBytes(): int;
    public function getContentCounts(): array; // ['posts' => 50, 'pages' => 10, 'categories' => 5]
    public function isAutoSnapshot(): bool;
    public function getRestoredAt(): ?\DateTimeImmutable;
}

/**
 * Snapshot creation options
 */
interface SnapshotOptionsInterface
{
    public function getName(): string;
    public function includeHistory(): bool;
    public function getContentTypes(): array; // ['posts', 'pages', 'categories'] or empty for all
}

/**
 * Result of restore operation
 */
interface RestoreResultInterface
{
    public function isSuccess(): bool;
    public function getRestoredCounts(): array;
    public function getErrors(): array;
    public function getBackupPath(): string; // Pre-restore backup
}

/**
 * Main snapshot service
 */
interface SnapshotServiceInterface
{
    /**
     * Create a new snapshot
     */
    public function create(SnapshotOptionsInterface $options): SnapshotInterface;
    
    /**
     * List all available snapshots
     */
    public function list(): array; // SnapshotInterface[]
    
    /**
     * Get snapshot by ID
     */
    public function get(int $id): ?SnapshotInterface;
    
    /**
     * Restore from snapshot
     */
    public function restore(int $snapshotId, array $contentTypes = []): RestoreResultInterface;
    
    /**
     * Delete a snapshot
     */
    public function delete(int $snapshotId): bool;
    
    /**
     * Get auto-snapshot setting
     */
    public function isAutoSnapshotEnabled(): bool;
    
    /**
     * Enable/disable auto-snapshot
     */
    public function setAutoSnapshot(bool $enabled): void;
}
```

---

## Database Schema

### Snapshots Table (in link-manager.db)

```sql
CREATE TABLE IF NOT EXISTS Snapshots (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    Sequence INTEGER NOT NULL,
    Name TEXT NOT NULL,
    FileName TEXT NOT NULL UNIQUE,
    FilePath TEXT NOT NULL,
    SizeBytes INTEGER NOT NULL DEFAULT 0,
    PostCount INTEGER NOT NULL DEFAULT 0,
    PageCount INTEGER NOT NULL DEFAULT 0,
    CategoryCount INTEGER NOT NULL DEFAULT 0,
    IsAutoSnapshot INTEGER NOT NULL DEFAULT 0,
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    RestoredAt TEXT DEFAULT NULL
);

CREATE INDEX idx_snapshots_sequence ON Snapshots(Sequence DESC);
CREATE INDEX idx_snapshots_created ON Snapshots(CreatedAt DESC);
```

### Restore History Table

```sql
CREATE TABLE IF NOT EXISTS RestoreHistory (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    SnapshotId INTEGER NOT NULL,
    PreRestoreBackupPath TEXT NOT NULL,
    RestoredPostCount INTEGER NOT NULL DEFAULT 0,
    RestoredPageCount INTEGER NOT NULL DEFAULT 0,
    RestoredCategoryCount INTEGER NOT NULL DEFAULT 0,
    RestoredAt TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (SnapshotId) REFERENCES Snapshots(Id)
);
```

---

## Snapshot Creation

```php
<?php
declare(strict_types=1);

namespace LinkManager\Snapshot;

use LinkManager\Database\ConnectionManager;
use LinkManager\Logger\LoggerInterface;

final class SnapshotCreator
{
    private const MAX_SNAPSHOTS = 50;
    
    public function __construct(
        private readonly ConnectionManager $db,
        private readonly LoggerInterface $logger,
        private readonly string $snapshotDir
    ) {}
    
    /**
     * Create a complete snapshot
     */
    public function create(SnapshotOptionsInterface $options): SnapshotInterface
    {
        // 1. Generate sequence number
        $sequence = $this->getNextSequence();
        
        // 2. Build filename
        $date = date('Y-m-d');
        $safeName = $this->sanitizeName($options->getName());
        $fileName = sprintf('%03d-%s-%s.db', $sequence, $safeName, $date);
        $filePath = $this->snapshotDir . '/' . $fileName;
        
        // 3. Create snapshot database
        $this->createSnapshotDatabase($filePath, $options);
        
        // 4. Record in main database
        $snapshot = $this->recordSnapshot($sequence, $options, $fileName, $filePath);
        
        // 5. Cleanup old snapshots if needed
        $this->cleanupOldSnapshots();
        
        $this->logger->info('Snapshot created', [
            'id' => $snapshot->getId(),
            'name' => $snapshot->getName(),
            'path' => $filePath
        ]);
        
        return $snapshot;
    }
    
    private function createSnapshotDatabase(
        string $path, 
        SnapshotOptionsInterface $options
    ): void {
        // Create new SQLite database
        $snapshot = new \SQLite3($path);
        $snapshot->enableExceptions(true);
        
        // Copy schema
        $this->copySchema($snapshot);
        
        // Copy data
        $contentTypes = $options->getContentTypes() ?: ['posts', 'pages', 'categories'];
        
        foreach ($contentTypes as $type) {
            $this->copyContentData($snapshot, $type);
        }
        
        // Copy links data
        $this->copyLinksData($snapshot);
        
        // Optionally copy history references
        if ($options->includeHistory()) {
            $this->copyHistoryReferences($snapshot);
        }
        
        $snapshot->close();
    }
    
    private function copySchema(\SQLite3 $snapshot): void
    {
        // Create identical tables
        $tables = [
            'Posts', 'Pages', 'Categories', 'Links', 'LinkCategories'
        ];
        
        foreach ($tables as $table) {
            $schema = $this->db->querySingle(
                "SELECT sql FROM sqlite_master WHERE type='table' AND name=?",
                [$table]
            );
            if ($schema) {
                $snapshot->exec($schema);
            }
        }
    }
    
    private function copyContentData(\SQLite3 $snapshot, string $type): void
    {
        $table = match($type) {
            'posts' => 'Posts',
            'pages' => 'Pages',
            'categories' => 'Categories',
            default => throw new \InvalidArgumentException("Unknown type: {$type}")
        };
        
        $rows = $this->db->query("SELECT * FROM {$table}");
        
        $snapshot->exec('BEGIN TRANSACTION');
        while ($row = $rows->fetchArray(SQLITE3_ASSOC)) {
            $columns = implode(', ', array_keys($row));
            $placeholders = implode(', ', array_fill(0, count($row), '?'));
            $snapshot->query(
                "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})",
                array_values($row)
            );
        }
        $snapshot->exec('COMMIT');
    }
    
    private function getNextSequence(): int
    {
        $result = $this->db->querySingle(
            'SELECT MAX(Sequence) FROM Snapshots'
        );
        return ($result ?? 0) + 1;
    }
    
    private function sanitizeName(string $name): string
    {
        // Convert to lowercase, replace spaces with hyphens, remove special chars
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9\-]/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);
        return substr(trim($name, '-'), 0, 50);
    }
    
    private function cleanupOldSnapshots(): void
    {
        $count = $this->db->querySingle('SELECT COUNT(*) FROM Snapshots');
        
        if ($count > self::MAX_SNAPSHOTS) {
            // Delete oldest non-restored snapshots
            $toDelete = $this->db->query(
                'SELECT Id, FilePath FROM Snapshots 
                 WHERE RestoredAt IS NULL 
                 ORDER BY CreatedAt ASC 
                 LIMIT ?',
                [$count - self::MAX_SNAPSHOTS]
            );
            
            while ($row = $toDelete->fetchArray(SQLITE3_ASSOC)) {
                @unlink($row['FilePath']);
                $this->db->execute('DELETE FROM Snapshots WHERE Id = ?', [$row['Id']]);
            }
        }
    }
}
```

---

## Snapshot Restoration

```php
<?php
declare(strict_types=1);

namespace LinkManager\Snapshot;

final class SnapshotRestorer
{
    public function __construct(
        private readonly ConnectionManager $db,
        private readonly SnapshotCreator $creator,
        private readonly LoggerInterface $logger
    ) {}
    
    /**
     * Restore from a snapshot
     */
    public function restore(
        SnapshotInterface $snapshot,
        array $contentTypes = []
    ): RestoreResultInterface {
        // 1. Create pre-restore backup (safety net)
        $preRestoreBackup = $this->creator->create(
            new SnapshotOptions('pre-restore-backup', false, [])
        );
        
        $this->logger->info('Pre-restore backup created', [
            'backup_id' => $preRestoreBackup->getId()
        ]);
        
        // 2. Open snapshot database
        $snapshotDb = new \SQLite3($snapshot->getFilePath());
        $snapshotDb->enableExceptions(true);
        
        // 3. Determine what to restore
        $types = empty($contentTypes) 
            ? ['posts', 'pages', 'categories'] 
            : $contentTypes;
        
        // 4. Begin transaction
        $this->db->execute('BEGIN TRANSACTION');
        
        try {
            $counts = [];
            
            foreach ($types as $type) {
                $counts[$type] = $this->restoreContentType($snapshotDb, $type);
            }
            
            // Restore links
            $counts['links'] = $this->restoreLinks($snapshotDb);
            
            $this->db->execute('COMMIT');
            
            // 5. Record restore in history
            $this->recordRestore($snapshot, $counts, $preRestoreBackup->getFilePath());
            
            $this->logger->info('Snapshot restored successfully', [
                'snapshot_id' => $snapshot->getId(),
                'counts' => $counts
            ]);
            
            return new RestoreResult(true, $counts, [], $preRestoreBackup->getFilePath());
            
        } catch (\Exception $e) {
            $this->db->execute('ROLLBACK');
            
            $this->logger->error('Restore failed', [
                'snapshot_id' => $snapshot->getId(),
                'error' => $e->getMessage()
            ]);
            
            return new RestoreResult(
                false, 
                [], 
                [$e->getMessage()], 
                $preRestoreBackup->getFilePath()
            );
        } finally {
            $snapshotDb->close();
        }
    }
    
    private function restoreContentType(\SQLite3 $snapshotDb, string $type): int
    {
        $table = match($type) {
            'posts' => 'Posts',
            'pages' => 'Pages', 
            'categories' => 'Categories',
            default => throw new \InvalidArgumentException("Unknown type: {$type}")
        };
        
        // Clear current data
        $this->db->execute("DELETE FROM {$table}");
        
        // Copy from snapshot
        $rows = $snapshotDb->query("SELECT * FROM {$table}");
        $count = 0;
        
        while ($row = $rows->fetchArray(SQLITE3_ASSOC)) {
            $columns = implode(', ', array_keys($row));
            $placeholders = implode(', ', array_fill(0, count($row), '?'));
            $this->db->execute(
                "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})",
                array_values($row)
            );
            $count++;
        }
        
        return $count;
    }
    
    private function recordRestore(
        SnapshotInterface $snapshot,
        array $counts,
        string $backupPath
    ): void {
        $this->db->execute(
            'INSERT INTO RestoreHistory 
             (SnapshotId, PreRestoreBackupPath, RestoredPostCount, RestoredPageCount, RestoredCategoryCount) 
             VALUES (?, ?, ?, ?, ?)',
            [
                $snapshot->getId(),
                $backupPath,
                $counts['posts'] ?? 0,
                $counts['pages'] ?? 0,
                $counts['categories'] ?? 0
            ]
        );
        
        // Update snapshot's RestoredAt
        $this->db->execute(
            "UPDATE Snapshots SET RestoredAt = datetime('now') WHERE Id = ?",
            [$snapshot->getId()]
        );
    }
}
```

---

## Auto-Snapshot Integration

```php
<?php
declare(strict_types=1);

namespace LinkManager\Snapshot;

final class AutoSnapshotManager
{
    private const SETTING_KEY = 'lm_auto_snapshot_enabled';
    
    public function __construct(
        private readonly SnapshotServiceInterface $snapshots,
        private readonly LoggerInterface $logger
    ) {}
    
    /**
     * Called before any modification operation
     */
    public function beforeModification(string $context): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        
        // Check if we already have a recent snapshot (within 1 hour)
        $recent = $this->getRecentAutoSnapshot();
        if ($recent !== null) {
            $this->logger->debug('Skipping auto-snapshot, recent exists', [
                'recent_id' => $recent->getId()
            ]);
            return;
        }
        
        // Create auto-snapshot
        try {
            $snapshot = $this->snapshots->create(
                new SnapshotOptions(
                    'auto-' . $context,
                    false, // Don't include history in auto-snapshots
                    []     // All content types
                )
            );
            
            $this->logger->info('Auto-snapshot created', [
                'id' => $snapshot->getId(),
                'context' => $context
            ]);
            
        } catch (\Exception $e) {
            // Log but don't block the operation
            $this->logger->warning('Auto-snapshot failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    public function isEnabled(): bool
    {
        return get_option(self::SETTING_KEY, false);
    }
    
    public function setEnabled(bool $enabled): void
    {
        update_option(self::SETTING_KEY, $enabled);
    }
    
    private function getRecentAutoSnapshot(): ?SnapshotInterface
    {
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        // Query for recent auto-snapshots
        $snapshots = $this->snapshots->list();
        
        foreach ($snapshots as $snapshot) {
            if ($snapshot->isAutoSnapshot() && 
                $snapshot->getCreatedAt()->format('Y-m-d H:i:s') > $oneHourAgo) {
                return $snapshot;
            }
        }
        
        return null;
    }
}
```

---

## REST API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `lm/v1/snapshots` | List all snapshots |
| POST | `lm/v1/snapshots` | Create new snapshot |
| GET | `lm/v1/snapshots/{id}` | Get snapshot details |
| POST | `lm/v1/snapshots/{id}/restore` | Restore from snapshot |
| DELETE | `lm/v1/snapshots/{id}` | Delete snapshot |
| GET | `lm/v1/snapshots/settings` | Get auto-snapshot setting |
| PUT | `lm/v1/snapshots/settings` | Update auto-snapshot setting |

---

## Error Codes

> **Note:** Snapshot errors use the 14500 range (per `66-shared-constants.md` SSOT)

| Code | Constant | Description |
|------|----------|-------------|
| 14500 | ERR_SNAPSHOT_CREATE_FAILED | Snapshot creation failed |
| 14501 | ERR_SNAPSHOT_NOT_FOUND | Snapshot ID not found |
| 14502 | ERR_SNAPSHOT_RESTORE_FAILED | Restore operation failed |
| 14503 | ERR_SNAPSHOT_DISK_FULL | Insufficient disk space |
| 14504 | ERR_SNAPSHOT_FILE_MISSING | Snapshot file missing from disk |
| 14505 | ERR_SNAPSHOT_DELETE_PROTECTED | Cannot delete recently restored snapshot |
| 14506 | ERR_SNAPSHOT_CORRUPTED | Snapshot database is corrupted |

---

## Acceptance Criteria

**Done when:**
- [ ] Snapshots capture all three content types (posts, pages, categories)
- [ ] Snapshot files are created with correct naming convention
- [ ] Restore creates pre-restore backup automatically
- [ ] Partial restore (specific content types) works correctly
- [ ] Auto-snapshot triggers before first modification
- [ ] Old snapshots are cleaned up when limit reached
- [ ] Restore history is maintained for audit
- [ ] UI shows snapshot notification on first modification

---

## Dependencies

- `04-database-schema.md` - Main database structure
- `12-history-service.md` - History database references
- WordPress options API for settings
