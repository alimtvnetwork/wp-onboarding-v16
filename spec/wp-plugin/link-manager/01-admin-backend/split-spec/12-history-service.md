# 12 - History Service

> **Phase:** History System  
> **Dependencies:** `04-database-schema.md`, `11-elementor-integration.md`  
> **Estimated Time:** 5-6 hours  
> **Last Updated:** 2026-01-31

---

## 📋 Scope

Implement the version history management system that tracks all content modifications, enables rollback to any previous version, and maintains a complete audit trail per post/page/category.

---

## 🎯 Purpose

The HistoryService provides:
- **Per-content history databases**: Isolated history for each post/page/category
- **Full content versioning**: Before/after snapshots of every modification
- **Rollback capability**: Restore content to any previous version
- **Audit trail**: Who made what changes and when

---

## 🔧 Core Interface

```php
<?php
namespace LinkManager\Services;

use LinkManager\Enums\{ContentType, ModificationType};

interface HistoryServiceInterface
{
    /**
     * Create a new version record before modification
     *
     * @param ContentType $type POST, PAGE, or CATEGORY
     * @param int $wpContentId WordPress content ID
     * @param string $slug Content slug
     * @param string $contentBefore Full content before modification
     * @param ModificationType $modificationType Type of modification
     * @param int|null $userId WordPress user ID
     * @return int Version number created
     */
    public function createVersion(
        ContentType $type,
        int $wpContentId,
        string $slug,
        string $contentBefore,
        ModificationType $modificationType,
        ?int $userId = null
    ): int;
    
    /**
     * Complete version with after content
     *
     * @param ContentType $type
     * @param int $wpContentId
     * @param int $versionNumber
     * @param string $contentAfter Full content after modification
     * @param array $modificationDetails Additional modification info
     */
    public function completeVersion(
        ContentType $type,
        int $wpContentId,
        int $versionNumber,
        string $contentAfter,
        array $modificationDetails = []
    ): void;
    
    /**
     * Get version history for content
     */
    public function getHistory(
        ContentType $type,
        int $wpContentId
    ): array;
    
    /**
     * Get specific version details
     */
    public function getVersion(
        ContentType $type,
        int $wpContentId,
        int $versionNumber
    ): ?array;
    
    /**
     * Rollback to a specific version
     *
     * @param ContentType $type
     * @param int $wpContentId
     * @param int $targetVersion Version to restore
     * @param int|null $userId User performing rollback
     * @return bool Success status
     */
    public function rollbackToVersion(
        ContentType $type,
        int $wpContentId,
        int $targetVersion,
        ?int $userId = null
    ): bool;
    
    /**
     * Get history count for content
     */
    public function getHistoryCount(
        ContentType $type,
        int $wpContentId
    ): int;
    
    /**
     * Check if content has history
     */
    public function hasHistory(
        ContentType $type,
        int $wpContentId,
        string $slug
    ): bool;
}
```

---

## 🏗️ Implementation

**File:** `src/Services/HistoryService.php`

```php
<?php
namespace LinkManager\Services;

use LinkManager\Database\HistoryConnection;
use LinkManager\Database\Models\{Post, Page, Category};
use LinkManager\Enums\{ContentType, ModificationType};
use LinkManager\Utils\Logger;

class HistoryService implements HistoryServiceInterface
{
    private ElementorParser $elementorParser;
    
    public function __construct(ElementorParser $elementorParser)
    {
        $this->elementorParser = $elementorParser;
    }
    
    /**
     * Create a new version record before modification
     */
    public function createVersion(
        ContentType $type,
        int $wpContentId,
        string $slug,
        string $contentBefore,
        ModificationType $modificationType,
        ?int $userId = null
    ): int {
        try {
            $pdo = HistoryConnection::getConnection($type, $wpContentId, $slug);
            
            // Get next version number
            $versionNumber = $this->getNextVersionNumber($pdo);
            
            // Get Elementor data if applicable
            $elementorDataBefore = null;
            if ($type !== ContentType::CATEGORY) {
                $elementorDataBefore = get_post_meta($wpContentId, '_elementor_data', true);
            }
            
            // Create version record
            $stmt = $pdo->prepare("
                INSERT INTO ContentVersion (
                    VersionNumber,
                    ContentBefore,
                    ContentAfter,
                    ElementorDataBefore,
                    ElementorDataAfter,
                    ModificationType,
                    ModifiedBy,
                    CreatedAt
                ) VALUES (?, ?, '', ?, NULL, ?, ?, ?)
            ");
            
            $stmt->execute([
                $versionNumber,
                $contentBefore,
                $elementorDataBefore,
                $modificationType->value,
                $userId,
                gmdate('Y-m-d H:i:s'),
            ]);
            
            // Update main database HasHistory flag
            $this->markContentHasHistory($type, $wpContentId, $slug);
            
            Logger::info('Version created', [
                'type' => $type->value,
                'wp_id' => $wpContentId,
                'version' => $versionNumber,
                'modification' => $modificationType->value
            ]);
            
            return $versionNumber;
            
        } catch (\Throwable $e) {
            Logger::error('Failed to create version', [
                'file' => __FILE__,
                'action' => 'createVersion',
                'type' => $type->value,
                'wp_id' => $wpContentId,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Complete version with after content
     */
    public function completeVersion(
        ContentType $type,
        int $wpContentId,
        int $versionNumber,
        string $contentAfter,
        array $modificationDetails = []
    ): void {
        try {
            $slug = $this->getSlug($type, $wpContentId);
            $pdo = HistoryConnection::getConnection($type, $wpContentId, $slug);
            
            // Get Elementor data after if applicable
            $elementorDataAfter = null;
            if ($type !== ContentType::CATEGORY) {
                $elementorDataAfter = get_post_meta($wpContentId, '_elementor_data', true);
            }
            
            // Update version record
            $stmt = $pdo->prepare("
                UPDATE ContentVersion 
                SET ContentAfter = ?,
                    ElementorDataAfter = ?,
                    LinkUrl = ?,
                    AnchorTextBefore = ?,
                    AnchorTextAfter = ?
                WHERE VersionNumber = ?
            ");
            
            $stmt->execute([
                $contentAfter,
                $elementorDataAfter,
                $modificationDetails['linkUrl'] ?? null,
                $modificationDetails['anchorTextBefore'] ?? null,
                $modificationDetails['anchorTextAfter'] ?? null,
                $versionNumber,
            ]);
            
            // Add modification log entry
            $this->addModificationLog($pdo, $versionNumber, $modificationDetails);
            
            Logger::info('Version completed', [
                'type' => $type->value,
                'wp_id' => $wpContentId,
                'version' => $versionNumber
            ]);
            
        } catch (\Throwable $e) {
            Logger::error('Failed to complete version', [
                'file' => __FILE__,
                'action' => 'completeVersion',
                'type' => $type->value,
                'wp_id' => $wpContentId,
                'version' => $versionNumber,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get version history for content
     */
    public function getHistory(ContentType $type, int $wpContentId): array
    {
        try {
            $slug = $this->getSlug($type, $wpContentId);
            
            if (!HistoryConnection::historyExists($type, $wpContentId, $slug)) {
                return [];
            }
            
            $pdo = HistoryConnection::getConnection($type, $wpContentId, $slug);
            
            $stmt = $pdo->query("
                SELECT 
                    VersionNumber,
                    ModificationType,
                    LinkUrl,
                    AnchorTextBefore,
                    AnchorTextAfter,
                    ModifiedBy,
                    IsRolledBack,
                    RolledBackAt,
                    RolledBackToVersion,
                    CreatedAt
                FROM ContentVersion
                ORDER BY VersionNumber DESC
            ");
            
            $versions = $stmt->fetchAll();
            
            // Enrich with user info
            foreach ($versions as &$version) {
                if ($version['ModifiedBy']) {
                    $user = get_userdata($version['ModifiedBy']);
                    $version['ModifiedByName'] = $user ? $user->display_name : 'Unknown';
                }
            }
            
            return $versions;
            
        } catch (\Throwable $e) {
            Logger::error('Failed to get history', [
                'file' => __FILE__,
                'action' => 'getHistory',
                'type' => $type->value,
                'wp_id' => $wpContentId,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
    
    /**
     * Get specific version details
     */
    public function getVersion(
        ContentType $type,
        int $wpContentId,
        int $versionNumber
    ): ?array {
        try {
            $slug = $this->getSlug($type, $wpContentId);
            $pdo = HistoryConnection::getConnection($type, $wpContentId, $slug);
            
            $stmt = $pdo->prepare("
                SELECT * FROM ContentVersion WHERE VersionNumber = ?
            ");
            $stmt->execute([$versionNumber]);
            
            $version = $stmt->fetch();
            
            if (!$version) {
                return null;
            }
            
            // Get modification logs for this version
            $logStmt = $pdo->prepare("
                SELECT * FROM ModificationLog WHERE ContentVersionId = ?
            ");
            $logStmt->execute([$version['Id']]);
            $version['modifications'] = $logStmt->fetchAll();
            
            return $version;
            
        } catch (\Throwable $e) {
            Logger::error('Failed to get version', [
                'file' => __FILE__,
                'action' => 'getVersion',
                'type' => $type->value,
                'wp_id' => $wpContentId,
                'version' => $versionNumber,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    /**
     * Rollback to a specific version
     */
    public function rollbackToVersion(
        ContentType $type,
        int $wpContentId,
        int $targetVersion,
        ?int $userId = null
    ): bool {
        try {
            $slug = $this->getSlug($type, $wpContentId);
            $pdo = HistoryConnection::getConnection($type, $wpContentId, $slug);
            
            // Get target version
            $version = $this->getVersion($type, $wpContentId, $targetVersion);
            
            if ($version === null) {
                Logger::error('Target version not found', [
                    'type' => $type->value,
                    'wp_id' => $wpContentId,
                    'target_version' => $targetVersion
                ]);
                throw new \RuntimeException('Target version not found', 14403);
            }
            
            // Get current content for new version record
            $currentContent = $this->getCurrentContent($type, $wpContentId);
            
            // Create a new version for the rollback itself
            $rollbackVersion = $this->createVersion(
                $type,
                $wpContentId,
                $slug,
                $currentContent,
                ModificationType::REMOVE_LINK_KEEP_TEXT, // Use appropriate type
                $userId
            );
            
            // Restore content to target version's "ContentBefore"
            // (ContentBefore of target = state before that modification was made)
            $restoredContent = $version['ContentBefore'];
            
            $this->updateWordPressContent($type, $wpContentId, $restoredContent);
            
            // Restore Elementor data if applicable
            if (!empty($version['ElementorDataBefore']) && $type !== ContentType::CATEGORY) {
                update_post_meta($wpContentId, '_elementor_data', $version['ElementorDataBefore']);
            }
            
            // Mark rollback in version table
            $stmt = $pdo->prepare("
                UPDATE ContentVersion 
                SET IsRolledBack = 1,
                    RolledBackAt = ?,
                    RolledBackBy = ?,
                    RolledBackToVersion = ?
                WHERE VersionNumber = ?
            ");
            
            $stmt->execute([
                gmdate('Y-m-d H:i:s'),
                $userId,
                $targetVersion,
                $rollbackVersion,
            ]);
            
            // Complete the rollback version
            $this->completeVersion(
                $type,
                $wpContentId,
                $rollbackVersion,
                $restoredContent,
                ['rollbackFrom' => $rollbackVersion, 'rollbackTo' => $targetVersion]
            );
            
            Logger::info('Rollback completed', [
                'type' => $type->value,
                'wp_id' => $wpContentId,
                'from_version' => $rollbackVersion,
                'to_version' => $targetVersion,
                'user_id' => $userId
            ]);
            
            return true;
            
        } catch (\Throwable $e) {
            Logger::error('Rollback failed', [
                'file' => __FILE__,
                'action' => 'rollbackToVersion',
                'type' => $type->value,
                'wp_id' => $wpContentId,
                'target_version' => $targetVersion,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get history count for content
     */
    public function getHistoryCount(ContentType $type, int $wpContentId): int
    {
        try {
            $slug = $this->getSlug($type, $wpContentId);
            
            if (!HistoryConnection::historyExists($type, $wpContentId, $slug)) {
                return 0;
            }
            
            $pdo = HistoryConnection::getConnection($type, $wpContentId, $slug);
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM ContentVersion");
            return (int) $stmt->fetchColumn();
            
        } catch (\Throwable $e) {
            Logger::error('Failed to get history count', [
                'file' => __FILE__,
                'action' => 'getHistoryCount',
                'type' => $type->value,
                'wp_id' => $wpContentId,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }
    
    /**
     * Check if content has history
     */
    public function hasHistory(ContentType $type, int $wpContentId, string $slug): bool
    {
        return HistoryConnection::historyExists($type, $wpContentId, $slug);
    }
    
    // ===== Private Helper Methods =====
    
    private function getNextVersionNumber(\PDO $pdo): int
    {
        $stmt = $pdo->query("SELECT MAX(VersionNumber) FROM ContentVersion");
        $max = $stmt->fetchColumn();
        return ($max ?? 0) + 1;
    }
    
    private function getSlug(ContentType $type, int $wpContentId): string
    {
        return match ($type) {
            ContentType::POST => get_post_field('post_name', $wpContentId),
            ContentType::PAGE => get_post_field('post_name', $wpContentId),
            ContentType::CATEGORY => get_term_field('slug', $wpContentId, 'category'),
        };
    }
    
    private function getCurrentContent(ContentType $type, int $wpContentId): string
    {
        return match ($type) {
            ContentType::POST, ContentType::PAGE => get_post_field('post_content', $wpContentId),
            ContentType::CATEGORY => term_description($wpContentId, 'category'),
        };
    }
    
    private function updateWordPressContent(ContentType $type, int $wpContentId, string $content): void
    {
        match ($type) {
            ContentType::POST, ContentType::PAGE => wp_update_post([
                'ID' => $wpContentId,
                'post_content' => $content,
            ]),
            ContentType::CATEGORY => wp_update_term($wpContentId, 'category', [
                'description' => $content,
            ]),
        };
    }
    
    private function markContentHasHistory(ContentType $type, int $wpContentId, string $slug): void
    {
        $dbPath = HistoryConnection::getDbPath($type, $wpContentId, $slug);
        
        $model = match ($type) {
            ContentType::POST => Post::findByWpId($wpContentId),
            ContentType::PAGE => Page::findByWpId($wpContentId),
            ContentType::CATEGORY => Category::findByWpId($wpContentId),
        };
        
        if ($model) {
            $model->update([
                'HasHistory' => 1,
                'HistoryDbPath' => $dbPath,
            ]);
        }
    }
    
    private function addModificationLog(\PDO $pdo, int $versionNumber, array $details): void
    {
        // Get version ID
        $stmt = $pdo->prepare("SELECT Id FROM ContentVersion WHERE VersionNumber = ?");
        $stmt->execute([$versionNumber]);
        $versionId = $stmt->fetchColumn();
        
        if (!$versionId) {
            return;
        }
        
        $logStmt = $pdo->prepare("
            INSERT INTO ModificationLog (
                ContentVersionId,
                ModificationType,
                TargetSelector,
                ValueBefore,
                ValueAfter,
                WrapperTagsRemoved,
                AttributesModified,
                CreatedAt
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $logStmt->execute([
            $versionId,
            $details['modificationType'] ?? null,
            $details['targetSelector'] ?? null,
            $details['valueBefore'] ?? null,
            $details['valueAfter'] ?? null,
            isset($details['wrapperTagsRemoved']) ? json_encode($details['wrapperTagsRemoved']) : null,
            isset($details['attributesModified']) ? json_encode($details['attributesModified']) : null,
            gmdate('Y-m-d H:i:s'),
        ]);
    }
}
```

---

## 📊 History Database Per-Content

```
wp-content/uploads/link-manager/history-manage/
├── posts/
│   ├── 123-my-first-blog-post.db
│   │   └── Tables: ContentVersion, ModificationLog
│   ├── 456-another-post.db
│   └── 789-third-post.db
├── pages/
│   ├── 10-about-us.db
│   ├── 20-contact.db
│   └── 30-services.db
└── categories/
    ├── 5-uncategorized.db
    └── 12-technology.db
```

---

## ✅ Acceptance Criteria

### Version Creation
- [ ] Creates new history DB if doesn't exist
- [ ] Increments version numbers correctly
- [ ] Stores full content before modification
- [ ] Captures Elementor data if present
- [ ] Links to ModificationLog entries

### Rollback
- [ ] Restores content to exact previous state
- [ ] Restores Elementor data
- [ ] Creates new version for rollback action
- [ ] Marks original version as rolled back
- [ ] Updates WordPress post/page correctly

### History Retrieval
- [ ] Lists all versions in descending order
- [ ] Includes user info for each version
- [ ] Returns empty array if no history
- [ ] Correctly counts versions

### Error Handling
- [ ] Full stack traces in logs
- [ ] Graceful handling of missing history DB
- [ ] Proper error codes (14400-14499)

---

## 📝 Related Specifications

- `04-database-schema.md` - History DB schema
- `13-snapshot-service.md` - Full database snapshots
- `14-modification-service.md` - Link modifications
- `11-elementor-integration.md` - Elementor data handling

---

*Critical for zero data loss - every modification must be tracked.*
