# 04 - Database Schema

> **Phase:** Foundation  
> **Dependencies:** `03-plugin-structure.md`  
> **Estimated Time:** 4-6 hours  
> **Last Updated:** 2026-01-31

---

## ⚠️ NAMING CONVENTION (CRITICAL)

> **Database columns use PascalCase** (e.g., `PostId`, `CreatedAt`, `IsEnabled`)  
> **ORM properties use camelCase** (e.g., `postId`, `createdAt`, `isEnabled`)  
> This distinction prevents confusion between database layer and application layer.

---

## 📋 Scope

Create SQLite database connection wrapper and define all table schemas for both the main database and per-content history databases.

---

## 🗄️ Database Architecture

### Three-Tier Database System

```
┌─────────────────────────────────────────────────────────────────────┐
│                        MAIN DATABASE                                 │
│                    link-manager.db                                   │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐    │
│  │    Post     │ │    Page     │ │  Category   │ │    Link     │    │
│  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘    │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐                    │
│  │ ScanHistory │ │  Snapshot   │ │  Settings   │                    │
│  └─────────────┘ └─────────────┘ └─────────────┘                    │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                     HISTORY DATABASES                                │
│             history-manage/{type}/{id}-{slug}.db                     │
│                                                                      │
│  ┌───────────────────────┐  ┌───────────────────────┐               │
│  │    ContentVersion     │  │   ModificationLog     │               │
│  │  (Full content        │  │  (What changed,       │               │
│  │   snapshots)          │  │   when, by whom)      │               │
│  └───────────────────────┘  └───────────────────────┘               │
│                                                                      │
│  One DB per post/page/category with modification history             │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                     SNAPSHOT DATABASES                               │
│              snapshots/{number}-{name}-{date}.db                     │
│                                                                      │
│  Complete copy of main database at point-in-time                     │
│  Used for full system restoration                                    │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 🗄️ Main Database Connection

**File:** `src/Database/Connection.php`

```php
<?php
namespace LinkManager\Database;

use PDO;
use PDOException;
use LinkManager\Utils\Logger;

class Connection
{
    private static ?PDO $instance = null;
    
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                self::$instance = new PDO(
                    'sqlite:' . LM_DB_PATH,
                    null,
                    null,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
                
                // Enable foreign keys and WAL mode
                self::$instance->exec('PRAGMA foreign_keys = ON');
                self::$instance->exec('PRAGMA journal_mode = WAL');
                
            } catch (PDOException $e) {
                Logger::error('Database connection failed', [
                    'file' => __FILE__,
                    'error' => $e->getMessage(),
                    'stack_trace' => $e->getTraceAsString()
                ]);
                throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }
        
        return self::$instance;
    }
    
    public static function close(): void
    {
        self::$instance = null;
    }
    
    public static function exists(): bool
    {
        return file_exists(LM_DB_PATH);
    }
}
```

---

## 📊 Main Database Schema

**File:** `src/Database/Schema.php`

```php
<?php
namespace LinkManager\Database;

use PDO;
use LinkManager\Utils\Logger;

class Schema
{
    /**
     * Initialize all tables
     */
    public static function initialize(): void
    {
        $pdo = Connection::getInstance();
        
        // Create tables in dependency order
        self::createPostTable($pdo);
        self::createPageTable($pdo);
        self::createCategoryTable($pdo);
        self::createLinkTable($pdo);
        self::createScanHistoryTable($pdo);
        self::createSnapshotTable($pdo);
        self::createSettingsTable($pdo);
        self::createScanJobsTable($pdo);
        self::createJobQueueTable($pdo);
        
        // Internal Linking tables
        self::createLinkTargetTable($pdo);
        self::createLinkTemplateTable($pdo);
        self::createLinkVariableTable($pdo);
        self::createInternalLinkTable($pdo);
        
        // Health Monitor tables
        self::createLinkHealthChecksTable($pdo);
        self::createHealthAlertsTable($pdo);
        self::createHealthCheckJobsTable($pdo);
        self::createHealthExclusionsTable($pdo);
        
        // Notification tables
        self::createNotificationQueueTable($pdo);
        self::createNotificationRecipientsTable($pdo);
        self::createWebhookEndpointsTable($pdo);
        self::createNotificationLogTable($pdo);
        self::createNotificationSettingsTable($pdo);
        
        Logger::info('Main database schema initialized', [
            'function' => __FUNCTION__,
            'file' => __FILE__
        ]);
    }
    
    /**
     * Post Table
     * Stores scanned WordPress posts
     */
    private static function createPostTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS Post (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- WordPress Reference
                WpPostId INTEGER NOT NULL UNIQUE,
                
                -- Post Metadata
                Title VARCHAR(255) NOT NULL,
                Slug VARCHAR(255) NOT NULL,
                MetaDescription TEXT DEFAULT NULL,
                PostType VARCHAR(50) NOT NULL DEFAULT 'post',
                PostStatus VARCHAR(20) NOT NULL DEFAULT 'publish',
                
                -- Link Counts (cached)
                TotalLinks INTEGER NOT NULL DEFAULT 0,
                BrokenLinks INTEGER NOT NULL DEFAULT 0,
                WorkingLinks INTEGER NOT NULL DEFAULT 0,
                UnknownLinks INTEGER NOT NULL DEFAULT 0,
                
                -- Scan Status
                LastScannedAt DATETIME DEFAULT NULL,
                HasBrokenHtml BOOLEAN NOT NULL DEFAULT 0,
                
                -- History Reference
                HasHistory BOOLEAN NOT NULL DEFAULT 0,
                HistoryDbPath VARCHAR(255) DEFAULT NULL,
                
                -- Timestamps
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Post_WpPostId ON Post(WpPostId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Post_Slug ON Post(Slug)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Post_BrokenLinks ON Post(BrokenLinks)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Post_LastScannedAt ON Post(LastScannedAt)");
    }
    
    /**
     * Page Table
     * Stores scanned WordPress pages
     */
    private static function createPageTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS Page (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- WordPress Reference
                WpPageId INTEGER NOT NULL UNIQUE,
                
                -- Page Metadata
                Title VARCHAR(255) NOT NULL,
                Slug VARCHAR(255) NOT NULL,
                MetaDescription TEXT DEFAULT NULL,
                PageStatus VARCHAR(20) NOT NULL DEFAULT 'publish',
                
                -- Link Counts (cached)
                TotalLinks INTEGER NOT NULL DEFAULT 0,
                BrokenLinks INTEGER NOT NULL DEFAULT 0,
                WorkingLinks INTEGER NOT NULL DEFAULT 0,
                UnknownLinks INTEGER NOT NULL DEFAULT 0,
                
                -- Scan Status
                LastScannedAt DATETIME DEFAULT NULL,
                HasBrokenHtml BOOLEAN NOT NULL DEFAULT 0,
                
                -- History Reference
                HasHistory BOOLEAN NOT NULL DEFAULT 0,
                HistoryDbPath VARCHAR(255) DEFAULT NULL,
                
                -- Timestamps
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Page_WpPageId ON Page(WpPageId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Page_Slug ON Page(Slug)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Page_BrokenLinks ON Page(BrokenLinks)");
    }
    
    /**
     * Category Table
     * Stores scanned WordPress categories with descriptions
     */
    private static function createCategoryTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS Category (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- WordPress Reference
                WpCategoryId INTEGER NOT NULL UNIQUE,
                
                -- Category Metadata
                Name VARCHAR(255) NOT NULL,
                Slug VARCHAR(255) NOT NULL,
                Description TEXT DEFAULT NULL,
                
                -- Link Counts (cached)
                TotalLinks INTEGER NOT NULL DEFAULT 0,
                BrokenLinks INTEGER NOT NULL DEFAULT 0,
                WorkingLinks INTEGER NOT NULL DEFAULT 0,
                
                -- Scan Status
                LastScannedAt DATETIME DEFAULT NULL,
                
                -- History Reference
                HasHistory BOOLEAN NOT NULL DEFAULT 0,
                HistoryDbPath VARCHAR(255) DEFAULT NULL,
                
                -- Timestamps
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Category_WpCategoryId ON Category(WpCategoryId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Category_Slug ON Category(Slug)");
    }
    
    /**
     * Link Table
     * Stores all discovered links across content
     */
    private static function createLinkTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS Link (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Parent Reference
                ContentType VARCHAR(20) NOT NULL,  -- POST, PAGE, CATEGORY
                ContentId INTEGER NOT NULL,        -- References Post.Id, Page.Id, or Category.Id
                WpContentId INTEGER NOT NULL,      -- References WpPostId, WpPageId, etc.
                
                -- Link Data
                Url VARCHAR(2048) NOT NULL,
                AnchorText VARCHAR(500) DEFAULT NULL,
                TitleAttribute VARCHAR(255) DEFAULT NULL,
                
                -- Link Status
                Status VARCHAR(20) NOT NULL DEFAULT 'UNKNOWN',  -- WORKING, BROKEN, UNKNOWN, TIMEOUT, REDIRECT
                HttpStatusCode INTEGER DEFAULT NULL,
                LastCheckedAt DATETIME DEFAULT NULL,
                
                -- Link Context
                LinkSource VARCHAR(50) NOT NULL DEFAULT 'POST_CONTENT',  -- POST_CONTENT, JSON_LD, SCHEMA_MARKUP, ELEMENTOR
                WordCount VARCHAR(20) NOT NULL DEFAULT 'THREE_PLUS',     -- ONE_WORD, TWO_WORDS, THREE_PLUS
                
                -- Wrapper Information (JSON array for nested wrappers)
                WrapperTags TEXT DEFAULT NULL,      -- e.g., ['H2', 'STRONG'] for <h2><strong><a>
                HasHeadingWrapper BOOLEAN NOT NULL DEFAULT 0,
                HasEmphasisWrapper BOOLEAN NOT NULL DEFAULT 0,
                
                -- Position in content (for identification)
                PositionIndex INTEGER NOT NULL DEFAULT 0,  -- nth occurrence in content
                ElementorWidgetId VARCHAR(100) DEFAULT NULL,
                
                -- Scan Reference
                ScanHistoryId INTEGER DEFAULT NULL,
                
                -- Timestamps
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (ScanHistoryId) REFERENCES ScanHistory(Id) ON DELETE SET NULL
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Link_ContentType_ContentId ON Link(ContentType, ContentId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Link_Status ON Link(Status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Link_Url ON Link(Url)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Link_WordCount ON Link(WordCount)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Link_HasHeadingWrapper ON Link(HasHeadingWrapper)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Link_ScanHistoryId ON Link(ScanHistoryId)");
    }
    
    /**
     * Scan History Table
     * Records each scan operation
     */
    private static function createScanHistoryTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ScanHistory (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Scan Configuration
                ScanMode VARCHAR(30) NOT NULL DEFAULT 'ALL_LINKS',  -- ALL_LINKS, BROKEN_ONLY, CSV_IMPORT
                ContentTypes TEXT NOT NULL DEFAULT '[\"POST\", \"PAGE\"]',  -- JSON array
                
                -- Scan Status
                Status VARCHAR(20) NOT NULL DEFAULT 'PENDING',  -- PENDING, RUNNING, COMPLETED, FAILED, CANCELLED
                
                -- Progress Tracking
                TotalItems INTEGER NOT NULL DEFAULT 0,
                ProcessedItems INTEGER NOT NULL DEFAULT 0,
                TotalLinksFound INTEGER NOT NULL DEFAULT 0,
                BrokenLinksFound INTEGER NOT NULL DEFAULT 0,
                
                -- Timing
                StartedAt DATETIME DEFAULT NULL,
                CompletedAt DATETIME DEFAULT NULL,
                DurationSeconds INTEGER DEFAULT NULL,
                
                -- Error Information
                ErrorMessage TEXT DEFAULT NULL,
                ErrorDetails TEXT DEFAULT NULL,
                
                -- Metadata
                InitiatedBy INTEGER DEFAULT NULL,  -- WordPress user ID
                IsCronJob BOOLEAN NOT NULL DEFAULT 0,
                
                -- Timestamps
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ScanHistory_Status ON ScanHistory(Status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ScanHistory_CreatedAt ON ScanHistory(CreatedAt)");
    }
    
    /**
     * Snapshot Table
     * Records snapshot metadata (actual snapshot is separate DB file)
     */
    private static function createSnapshotTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS Snapshot (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Snapshot Identification
                SnapshotNumber INTEGER NOT NULL,
                Name VARCHAR(100) NOT NULL,
                FileName VARCHAR(255) NOT NULL UNIQUE,
                FilePath VARCHAR(500) NOT NULL,
                
                -- Snapshot Type
                Type VARCHAR(30) NOT NULL DEFAULT 'MANUAL',  -- MANUAL, AUTO_BEFORE_MODIFY, SCHEDULED
                
                -- Content Counts at Snapshot Time
                PostCount INTEGER NOT NULL DEFAULT 0,
                PageCount INTEGER NOT NULL DEFAULT 0,
                CategoryCount INTEGER NOT NULL DEFAULT 0,
                LinkCount INTEGER NOT NULL DEFAULT 0,
                
                -- Size Information
                FileSizeBytes INTEGER DEFAULT NULL,
                
                -- Restoration Tracking
                RestoredAt DATETIME DEFAULT NULL,
                RestoredBy INTEGER DEFAULT NULL,
                
                -- Metadata
                CreatedBy INTEGER DEFAULT NULL,
                Notes TEXT DEFAULT NULL,
                
                -- Timestamps
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Snapshot_SnapshotNumber ON Snapshot(SnapshotNumber)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Snapshot_Type ON Snapshot(Type)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Snapshot_CreatedAt ON Snapshot(CreatedAt)");
    }
    
    /**
     * Settings Table
     * Plugin configuration stored in SQLite
     */
    private static function createSettingsTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS Settings (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                SettingKey VARCHAR(100) NOT NULL UNIQUE,
                SettingValue TEXT DEFAULT NULL,
                SettingType VARCHAR(20) NOT NULL DEFAULT 'string',  -- string, integer, boolean, json
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Settings_SettingKey ON Settings(SettingKey)");
    }
    
    /**
     * ScanJobs Table
     * Records background cron scan jobs
     */
    private static function createScanJobsTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ScanJobs (
                Id TEXT PRIMARY KEY,
                
                -- Job Configuration
                Type TEXT NOT NULL,              -- scan_all, scan_broken, scan_posts, etc.
                Status TEXT NOT NULL DEFAULT 'pending',  -- pending, running, paused, completed, failed, cancelled
                Options TEXT DEFAULT NULL,       -- JSON configuration options
                
                -- Progress Tracking
                TotalItems INTEGER NOT NULL DEFAULT 0,
                CompletedItems INTEGER NOT NULL DEFAULT 0,
                CurrentItem TEXT DEFAULT NULL,   -- Currently processing item title
                LinksFound INTEGER NOT NULL DEFAULT 0,
                BrokenFound INTEGER NOT NULL DEFAULT 0,
                
                -- Error Tracking
                Errors TEXT DEFAULT NULL,        -- JSON array of error objects
                
                -- Timing
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                StartedAt DATETIME DEFAULT NULL,
                CompletedAt DATETIME DEFAULT NULL,
                LastActivityAt DATETIME DEFAULT NULL
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ScanJobs_Status ON ScanJobs(Status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ScanJobs_CreatedAt ON ScanJobs(CreatedAt DESC)");
    }
    
    /**
     * JobQueue Table
     * Queue of items to process for each scan job
     */
    private static function createJobQueueTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS JobQueue (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Job Reference
                JobId TEXT NOT NULL,
                
                -- Item to Process
                ItemType TEXT NOT NULL,          -- 'post', 'page', 'category'
                ItemId INTEGER NOT NULL,
                
                -- Processing Status
                Processed INTEGER NOT NULL DEFAULT 0,
                ProcessedAt DATETIME DEFAULT NULL,
                
                FOREIGN KEY (JobId) REFERENCES ScanJobs(Id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_JobQueue_JobId_Processed ON JobQueue(JobId, Processed)");
    }
    
    // ========== Internal Linking Tables ==========
    
    /**
     * LinkTarget Table
     * Stores URLs available for internal linking (from CSV/JSON import or manual entry)
     */
    private static function createLinkTargetTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS LinkTarget (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Target Information
                Url VARCHAR(2048) NOT NULL UNIQUE,
                Title VARCHAR(500) NOT NULL,
                Category VARCHAR(100) DEFAULT NULL,
                Priority INTEGER NOT NULL DEFAULT 0,
                Keywords TEXT DEFAULT NULL,           -- JSON array of additional match keywords
                
                -- Source Tracking
                Source VARCHAR(50) NOT NULL DEFAULT 'MANUAL_CREATE',  -- AUTO_TITLE_MATCH, AUTO_CATEGORY, MANUAL_IMPORT, MANUAL_CREATE
                
                -- Usage Statistics
                TimesLinked INTEGER NOT NULL DEFAULT 0,
                LastLinkedAt DATETIME DEFAULT NULL,
                
                -- Status
                IsActive BOOLEAN NOT NULL DEFAULT 1,
                
                -- Timestamps
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_LinkTarget_Category ON LinkTarget(Category)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_LinkTarget_Priority ON LinkTarget(Priority DESC)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_LinkTarget_IsActive ON LinkTarget(IsActive)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_LinkTarget_Source ON LinkTarget(Source)");
        
        Logger::info('LinkTarget table created', [
            'function' => __FUNCTION__,
            'file' => __FILE__
        ]);
    }
    
    /**
     * LinkTemplate Table
     * Stores HTML templates for link generation with variable placeholders
     */
    private static function createLinkTemplateTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS LinkTemplate (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Template Identification
                Name VARCHAR(100) NOT NULL UNIQUE,
                
                -- Template Content
                Template TEXT NOT NULL,               -- HTML with {{variable}} placeholders
                
                -- Settings
                IsDefault BOOLEAN NOT NULL DEFAULT 0,
                IsActive BOOLEAN NOT NULL DEFAULT 1,
                
                -- Timestamps
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_LinkTemplate_IsDefault ON LinkTemplate(IsDefault)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_LinkTemplate_IsActive ON LinkTemplate(IsActive)");
        
        // Insert default template
        $pdo->exec("
            INSERT OR IGNORE INTO LinkTemplate (Name, Template, IsDefault, IsActive, CreatedAt, UpdatedAt)
            VALUES (
                'Basic Link',
                '<a href=\"{{url}}\" title=\"{{title}}\">{{anchor_text}}</a>',
                1,
                1,
                datetime('now'),
                datetime('now')
            )
        ");
        
        Logger::info('LinkTemplate table created', [
            'function' => __FUNCTION__,
            'file' => __FILE__
        ]);
    }
    
    /**
     * LinkVariable Table
     * Stores dynamic variables for template injection from CSV/JSON sources
     */
    private static function createLinkVariableTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS LinkVariable (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Variable Identification
                Name VARCHAR(100) NOT NULL UNIQUE,    -- e.g., 'title_attr', 'heading_tag'
                
                -- Source Information
                SourceType VARCHAR(20) NOT NULL DEFAULT 'manual',  -- csv, json, manual
                SourceFile VARCHAR(500) DEFAULT NULL,              -- Path to source file
                ColumnOrKey VARCHAR(100) DEFAULT NULL,             -- CSV column or JSON key
                
                -- Cached Values
                Values TEXT NOT NULL DEFAULT '[]',    -- JSON array of available values
                
                -- Selection Configuration
                SelectionMode VARCHAR(20) NOT NULL DEFAULT 'SEQUENTIAL',  -- SEQUENTIAL, RANDOM, WEIGHTED
                CurrentIndex INTEGER NOT NULL DEFAULT 0,                   -- For sequential mode
                
                -- Timestamps
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                LastRefreshedAt DATETIME DEFAULT NULL
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_LinkVariable_Name ON LinkVariable(Name)");
        
        Logger::info('LinkVariable table created', [
            'function' => __FUNCTION__,
            'file' => __FILE__
        ]);
    }
    
    /**
     * InternalLink Table
     * Tracks internal links created by the auto-linking system
     */
    private static function createInternalLinkTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS InternalLink (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Source Content Reference
                ContentType VARCHAR(20) NOT NULL,     -- POST, PAGE, CATEGORY
                ContentId INTEGER NOT NULL,           -- References Post.Id, Page.Id, etc.
                WpContentId INTEGER NOT NULL,         -- WordPress content ID
                
                -- Link Information
                TargetUrl VARCHAR(2048) NOT NULL,
                AnchorText VARCHAR(500) NOT NULL,
                TemplateId INTEGER DEFAULT NULL,      -- Template used for generation
                
                -- Generation Metadata
                Source VARCHAR(50) NOT NULL DEFAULT 'MANUAL_CREATE',  -- AUTO_TITLE_MATCH, AUTO_CATEGORY, MANUAL_IMPORT, MANUAL_CREATE
                MatchedPhrase VARCHAR(500) DEFAULT NULL,              -- Original phrase that was matched
                
                -- Position
                PositionIndex INTEGER NOT NULL DEFAULT 0,
                
                -- Status
                IsActive BOOLEAN NOT NULL DEFAULT 1,
                RemovedAt DATETIME DEFAULT NULL,
                
                -- Timestamps
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (TemplateId) REFERENCES LinkTemplate(Id) ON DELETE SET NULL
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_InternalLink_ContentType_ContentId ON InternalLink(ContentType, ContentId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_InternalLink_WpContentId ON InternalLink(WpContentId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_InternalLink_TargetUrl ON InternalLink(TargetUrl)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_InternalLink_Source ON InternalLink(Source)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_InternalLink_IsActive ON InternalLink(IsActive)");
        
        Logger::info('InternalLink table created', [
            'function' => __FUNCTION__,
            'file' => __FILE__
        ]);
    }
    
    // ================================================================
    // HEALTH MONITOR TABLES
    // ================================================================
    
    /**
     * LinkHealthChecks Table
     * Stores health check results for each link
     */
    private static function createLinkHealthChecksTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS LinkHealthChecks (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Link Reference
                LinkId INTEGER NOT NULL,
                Url TEXT NOT NULL,
                
                -- Health Status
                Status TEXT NOT NULL DEFAULT 'UNKNOWN',  -- HEALTHY, REDIRECT, BROKEN, SLOW, UNKNOWN, EXCLUDED
                HttpCode INTEGER DEFAULT NULL,
                ResponseTimeMs INTEGER DEFAULT NULL,
                RedirectCount INTEGER DEFAULT 0,
                FinalUrl TEXT DEFAULT NULL,
                
                -- Error Information
                ErrorMessage TEXT DEFAULT NULL,
                
                -- SSL Information
                SslValid INTEGER DEFAULT 1,
                SslExpiry TEXT DEFAULT NULL,
                
                -- Scheduling
                Priority TEXT DEFAULT 'NORMAL',  -- HIGH, NORMAL, LOW
                LastCheckedAt TEXT DEFAULT NULL,
                NextCheckAt TEXT DEFAULT NULL,
                CheckCount INTEGER DEFAULT 0,
                ConsecutiveFailures INTEGER DEFAULT 0,
                
                -- Timestamps
                CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (LinkId) REFERENCES Link(Id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_LinkHealthChecks_Status ON LinkHealthChecks(Status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_LinkHealthChecks_NextCheckAt ON LinkHealthChecks(NextCheckAt)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_LinkHealthChecks_ConsecutiveFailures ON LinkHealthChecks(ConsecutiveFailures)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_LinkHealthChecks_LinkId ON LinkHealthChecks(LinkId)");
        
        Logger::info('LinkHealthChecks table created', [
            'function' => __FUNCTION__,
            'file' => __FILE__
        ]);
    }
    
    /**
     * HealthAlerts Table
     * Stores alerts generated from health checks
     */
    private static function createHealthAlertsTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS HealthAlerts (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Health Check Reference
                HealthCheckId INTEGER NOT NULL,
                
                -- Alert Information
                AlertType TEXT NOT NULL,     -- BROKEN_LINK, REDIRECT_CHAIN, SLOW_RESPONSE, SSL_ERROR, DNS_ERROR, TIMEOUT
                Severity TEXT NOT NULL,       -- INFO, WARNING, ERROR, CRITICAL
                Message TEXT NOT NULL,
                Details TEXT DEFAULT NULL,    -- JSON: additional context
                
                -- Content Reference
                ContentId INTEGER DEFAULT NULL,
                ContentType TEXT DEFAULT NULL,
                
                -- Alert Status
                Acknowledged INTEGER DEFAULT 0,
                AcknowledgedBy TEXT DEFAULT NULL,
                AcknowledgedAt TEXT DEFAULT NULL,
                ResolvedAt TEXT DEFAULT NULL,
                
                -- Timestamps
                CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (HealthCheckId) REFERENCES LinkHealthChecks(Id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_HealthAlerts_Severity ON HealthAlerts(Severity)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_HealthAlerts_Acknowledged ON HealthAlerts(Acknowledged)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_HealthAlerts_CreatedAt ON HealthAlerts(CreatedAt)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_HealthAlerts_HealthCheckId ON HealthAlerts(HealthCheckId)");
        
        Logger::info('HealthAlerts table created', [
            'function' => __FUNCTION__,
            'file' => __FILE__
        ]);
    }
    
    /**
     * HealthCheckJobs Table
     * Tracks batch health check job progress
     */
    private static function createHealthCheckJobsTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS HealthCheckJobs (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Job Status
                Status TEXT NOT NULL DEFAULT 'PENDING',  -- PENDING, RUNNING, COMPLETED, FAILED, CANCELLED
                
                -- Progress Tracking
                TotalLinks INTEGER DEFAULT 0,
                ProcessedLinks INTEGER DEFAULT 0,
                HealthyCount INTEGER DEFAULT 0,
                BrokenCount INTEGER DEFAULT 0,
                SlowCount INTEGER DEFAULT 0,
                RedirectCount INTEGER DEFAULT 0,
                
                -- Timing
                StartedAt TEXT DEFAULT NULL,
                CompletedAt TEXT DEFAULT NULL,
                
                -- Error Information
                ErrorMessage TEXT DEFAULT NULL,
                
                -- Timestamps
                CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_HealthCheckJobs_Status ON HealthCheckJobs(Status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_HealthCheckJobs_CreatedAt ON HealthCheckJobs(CreatedAt)");
        
        Logger::info('HealthCheckJobs table created', [
            'function' => __FUNCTION__,
            'file' => __FILE__
        ]);
    }
    
    /**
     * HealthExclusions Table
     * Patterns to exclude from health monitoring
     */
    private static function createHealthExclusionsTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS HealthExclusions (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Exclusion Pattern
                Pattern TEXT NOT NULL,
                PatternType TEXT NOT NULL,  -- 'domain', 'url', 'regex'
                Reason TEXT DEFAULT NULL,
                
                -- Metadata
                CreatedBy TEXT DEFAULT NULL,
                CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS IX_HealthExclusions_Pattern ON HealthExclusions(Pattern, PatternType)");
        
        Logger::info('HealthExclusions table created', [
            'function' => __FUNCTION__,
            'file' => __FILE__
        ]);
    }
    
    // ================================================================
    // NOTIFICATION TABLES
    // ================================================================
    
    /**
     * NotificationQueue Table
     * Stores pending and sent notifications
     */
    private static function createNotificationQueueTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS NotificationQueue (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Notification Type
                Type TEXT NOT NULL,           -- NotificationType enum value
                Channel TEXT NOT NULL,        -- EMAIL, WEBHOOK, ADMIN_NOTICE, LOG
                Priority TEXT NOT NULL DEFAULT 'NORMAL',  -- IMMEDIATE, HIGH, NORMAL, LOW
                Status TEXT NOT NULL DEFAULT 'PENDING',   -- PENDING, SENT, FAILED, RETRYING, CANCELLED
                
                -- Recipient
                Recipient TEXT NOT NULL,      -- Email address or webhook URL
                Subject TEXT DEFAULT NULL,
                
                -- Content
                Payload TEXT NOT NULL,        -- JSON: notification data
                
                -- Delivery Tracking
                Attempts INTEGER DEFAULT 0,
                LastAttemptAt TEXT DEFAULT NULL,
                LastError TEXT DEFAULT NULL,
                ScheduledFor TEXT DEFAULT NULL,  -- Future delivery time
                SentAt TEXT DEFAULT NULL,
                
                -- Timestamps
                CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_NotificationQueue_Status ON NotificationQueue(Status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_NotificationQueue_ScheduledFor ON NotificationQueue(ScheduledFor)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_NotificationQueue_Channel ON NotificationQueue(Channel)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_NotificationQueue_Type ON NotificationQueue(Type)");
        
        Logger::info('NotificationQueue table created', [
            'function' => __FUNCTION__,
            'file' => __FILE__
        ]);
    }
    
    /**
     * NotificationRecipients Table
     * Stores email recipients and their preferences
     */
    private static function createNotificationRecipientsTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS NotificationRecipients (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Recipient Info
                Email TEXT NOT NULL,
                Name TEXT DEFAULT NULL,
                IsActive INTEGER DEFAULT 1,
                
                -- Preferences (JSON arrays)
                NotificationTypes TEXT DEFAULT NULL,  -- JSON: enabled notification types
                Channels TEXT DEFAULT NULL,           -- JSON: preferred channels
                DigestPreference TEXT DEFAULT 'DAILY', -- IMMEDIATE, DAILY, WEEKLY
                
                -- Timestamps
                CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS IX_NotificationRecipients_Email ON NotificationRecipients(Email)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_NotificationRecipients_IsActive ON NotificationRecipients(IsActive)");
        
        Logger::info('NotificationRecipients table created', [
            'function' => __FUNCTION__,
            'file' => __FILE__
        ]);
    }
    
    /**
     * WebhookEndpoints Table
     * Stores configured webhook endpoints
     */
    private static function createWebhookEndpointsTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS WebhookEndpoints (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Endpoint Info
                Name TEXT NOT NULL,
                Url TEXT NOT NULL,
                
                -- Authentication
                AuthType TEXT DEFAULT 'NONE',  -- NONE, HMAC_SHA256, BEARER_TOKEN, BASIC_AUTH
                AuthSecret TEXT DEFAULT NULL,  -- Encrypted secret/token
                
                -- Configuration
                IsActive INTEGER DEFAULT 1,
                NotificationTypes TEXT DEFAULT NULL,  -- JSON: subscribed notification types
                Headers TEXT DEFAULT NULL,            -- JSON: custom headers
                RetryEnabled INTEGER DEFAULT 1,
                
                -- Status Tracking
                LastSuccessAt TEXT DEFAULT NULL,
                LastFailureAt TEXT DEFAULT NULL,
                ConsecutiveFailures INTEGER DEFAULT 0,
                
                -- Timestamps
                CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS IX_WebhookEndpoints_Url ON WebhookEndpoints(Url)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_WebhookEndpoints_IsActive ON WebhookEndpoints(IsActive)");
        
        Logger::info('WebhookEndpoints table created', [
            'function' => __FUNCTION__,
            'file' => __FILE__
        ]);
    }
    
    /**
     * NotificationLog Table
     * Stores delivery history for auditing
     */
    private static function createNotificationLogTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS NotificationLog (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Notification Reference
                NotificationId INTEGER DEFAULT NULL,
                
                -- Delivery Details
                Channel TEXT NOT NULL,
                Recipient TEXT NOT NULL,
                Type TEXT NOT NULL,
                Status TEXT NOT NULL,  -- SENT, FAILED
                
                -- Response Details
                ResponseCode INTEGER DEFAULT NULL,
                ResponseBody TEXT DEFAULT NULL,
                DurationMs INTEGER DEFAULT NULL,
                ErrorMessage TEXT DEFAULT NULL,
                
                -- Timestamps
                CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (NotificationId) REFERENCES NotificationQueue(Id) ON DELETE SET NULL
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_NotificationLog_CreatedAt ON NotificationLog(CreatedAt)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_NotificationLog_Status ON NotificationLog(Status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_NotificationLog_Channel ON NotificationLog(Channel)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_NotificationLog_NotificationId ON NotificationLog(NotificationId)");
        
        Logger::info('NotificationLog table created', [
            'function' => __FUNCTION__,
            'file' => __FILE__
        ]);
    }
    
    /**
     * NotificationSettings Table
     * Stores notification configuration settings
     */
    private static function createNotificationSettingsTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS NotificationSettings (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Setting Key-Value
                SettingKey TEXT NOT NULL UNIQUE,
                SettingValue TEXT NOT NULL,
                
                -- Timestamps
                UpdatedAt TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Insert default settings
        $pdo->exec("
            INSERT OR IGNORE INTO NotificationSettings (SettingKey, SettingValue) VALUES
                ('email_enabled', 'true'),
                ('webhook_enabled', 'true'),
                ('admin_notice_enabled', 'true'),
                ('digest_enabled', 'true'),
                ('digest_time', '09:00'),
                ('broken_threshold', '5'),
                ('slow_threshold', '10'),
                ('ssl_warning_days', '30')
        ");
        
        Logger::info('NotificationSettings table created', [
            'function' => __FUNCTION__,
            'file' => __FILE__
        ]);
    }
}
```

---

## 📜 History Database Schema

**File:** `src/Database/HistorySchema.php`

```php
<?php
namespace LinkManager\Database;

use PDO;
use LinkManager\Utils\Logger;

/**
 * Schema for per-content history databases
 * Path: history-manage/{type}/{id}-{slug}.db
 */
class HistorySchema
{
    /**
     * Initialize history database tables
     */
    public static function initialize(PDO $pdo): void
    {
        self::createContentVersionTable($pdo);
        self::createModificationLogTable($pdo);
        
        // Enable WAL mode for better concurrency
        $pdo->exec('PRAGMA journal_mode = WAL');
    }
    
    /**
     * Content Version Table
     * Stores full content snapshots for each modification
     */
    private static function createContentVersionTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ContentVersion (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Version Identification
                VersionNumber INTEGER NOT NULL,
                
                -- Content Snapshot
                ContentBefore TEXT NOT NULL,       -- Full content before modification
                ContentAfter TEXT NOT NULL,        -- Full content after modification
                
                -- Elementor Data (if applicable)
                ElementorDataBefore TEXT DEFAULT NULL,
                ElementorDataAfter TEXT DEFAULT NULL,
                
                -- Change Summary
                ModificationType VARCHAR(50) NOT NULL,
                LinkUrl VARCHAR(2048) DEFAULT NULL,      -- Which link was modified
                AnchorTextBefore VARCHAR(500) DEFAULT NULL,
                AnchorTextAfter VARCHAR(500) DEFAULT NULL,
                
                -- Metadata
                ModifiedBy INTEGER DEFAULT NULL,         -- WordPress user ID
                
                -- Rollback Tracking
                IsRolledBack BOOLEAN NOT NULL DEFAULT 0,
                RolledBackAt DATETIME DEFAULT NULL,
                RolledBackBy INTEGER DEFAULT NULL,
                RolledBackToVersion INTEGER DEFAULT NULL,
                
                -- Timestamps
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                
                UNIQUE(VersionNumber)
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ContentVersion_VersionNumber ON ContentVersion(VersionNumber)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ContentVersion_CreatedAt ON ContentVersion(CreatedAt)");
    }
    
    /**
     * Modification Log Table
     * Detailed log of each change made
     */
    private static function createModificationLogTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ModificationLog (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Version Reference
                ContentVersionId INTEGER NOT NULL,
                
                -- Modification Details
                ModificationType VARCHAR(50) NOT NULL,
                TargetSelector VARCHAR(500) DEFAULT NULL,  -- CSS selector or XPath for the element
                
                -- Before/After Values
                ValueBefore TEXT DEFAULT NULL,
                ValueAfter TEXT DEFAULT NULL,
                
                -- Additional Context
                WrapperTagsRemoved TEXT DEFAULT NULL,      -- JSON array of removed tags
                AttributesModified TEXT DEFAULT NULL,      -- JSON object of attribute changes
                
                -- Timestamps
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (ContentVersionId) REFERENCES ContentVersion(Id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ModificationLog_ContentVersionId ON ModificationLog(ContentVersionId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ModificationLog_ModificationType ON ModificationLog(ModificationType)");
    }
}
```

---

## 🔗 History Database Connection

**File:** `src/Database/HistoryConnection.php`

```php
<?php
namespace LinkManager\Database;

use PDO;
use PDOException;
use LinkManager\Utils\Logger;
use LinkManager\Utils\FileManager;
use LinkManager\Enums\ContentType;

/**
 * Manages connections to per-content history databases
 */
class HistoryConnection
{
    private static array $connections = [];
    
    /**
     * Get or create history database for specific content
     *
     * @param ContentType $type POST, PAGE, or CATEGORY
     * @param int $contentId WordPress post/page/category ID
     * @param string $slug Content slug
     */
    public static function getConnection(
        ContentType $type,
        int $contentId,
        string $slug
    ): PDO {
        $key = self::buildKey($type, $contentId);
        
        if (isset(self::$connections[$key])) {
            return self::$connections[$key];
        }
        
        $dbPath = self::getDbPath($type, $contentId, $slug);
        $isNewDb = !file_exists($dbPath);
        
        try {
            // Ensure directory exists
            $dir = dirname($dbPath);
            FileManager::ensureDirectory($dir);
            
            $pdo = new PDO(
                'sqlite:' . $dbPath,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            
            // Initialize schema if new database
            if ($isNewDb) {
                HistorySchema::initialize($pdo);
                Logger::info('History database created', [
                    'type' => $type->value,
                    'content_id' => $contentId,
                    'path' => $dbPath
                ]);
            }
            
            self::$connections[$key] = $pdo;
            return $pdo;
            
        } catch (PDOException $e) {
            Logger::error('History database connection failed', [
                'file' => __FILE__,
                'type' => $type->value,
                'content_id' => $contentId,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException('History database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Build database file path
     */
    public static function getDbPath(ContentType $type, int $contentId, string $slug): string
    {
        $folder = match ($type) {
            ContentType::POST => 'posts',
            ContentType::PAGE => 'pages',
            ContentType::CATEGORY => 'categories',
        };
        
        // Sanitize slug for filename
        $safeSlug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
        $safeSlug = substr($safeSlug, 0, 50); // Limit length
        
        $filename = sprintf('%d-%s.db', $contentId, $safeSlug);
        
        return LM_HISTORY_PATH . $folder . '/' . $filename;
    }
    
    /**
     * Check if history exists for content
     */
    public static function historyExists(ContentType $type, int $contentId, string $slug): bool
    {
        $dbPath = self::getDbPath($type, $contentId, $slug);
        return file_exists($dbPath);
    }
    
    /**
     * Close specific connection
     */
    public static function close(ContentType $type, int $contentId): void
    {
        $key = self::buildKey($type, $contentId);
        unset(self::$connections[$key]);
    }
    
    /**
     * Close all connections
     */
    public static function closeAll(): void
    {
        self::$connections = [];
    }
    
    private static function buildKey(ContentType $type, int $contentId): string
    {
        return $type->value . '_' . $contentId;
    }
}
```

---

## 📊 Entity Relationship Summary

```
┌──────────────────────────────────────────────────────────────────────────┐
│                           MAIN DATABASE                                   │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────┐     ┌─────────┐     ┌───────────┐                          │
│  │  Post   │     │  Page   │     │ Category  │                          │
│  │─────────│     │─────────│     │───────────│                          │
│  │ Id (PK) │     │ Id (PK) │     │ Id (PK)   │                          │
│  │WpPostId │     │WpPageId │     │WpCategoryId│                         │
│  │ Title   │     │ Title   │     │ Name      │                          │
│  │ Slug    │     │ Slug    │     │ Slug      │                          │
│  └────┬────┘     └────┬────┘     └─────┬─────┘                          │
│       │               │                │                                 │
│       └───────────────┼────────────────┘                                 │
│                       │                                                  │
│                       ▼                                                  │
│              ┌─────────────────┐                                         │
│              │      Link       │                                         │
│              │─────────────────│                                         │
│              │ Id (PK)         │                                         │
│              │ ContentType     │──────────────────┐                      │
│              │ ContentId (FK)  │                  │                      │
│              │ Url             │                  ▼                      │
│              │ Status          │         ┌─────────────────┐             │
│              │ WrapperTags     │         │  ScanHistory    │             │
│              │ ScanHistoryId   │◄────────│─────────────────│             │
│              └─────────────────┘         │ Id (PK)         │             │
│                                          │ ScanMode        │             │
│                                          │ Status          │             │
│                                          └─────────────────┘             │
│                                                                          │
│              ┌─────────────────┐         ┌─────────────────┐             │
│              │    Snapshot     │         │    Settings     │             │
│              │─────────────────│         │─────────────────│             │
│              │ Id (PK)         │         │ Id (PK)         │             │
│              │ SnapshotNumber  │         │ SettingKey      │             │
│              │ FilePath        │         │ SettingValue    │             │
│              └─────────────────┘         └─────────────────┘             │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────┐
│                    HISTORY DATABASE (per content)                         │
│                   {id}-{slug}.db                                          │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────────────┐         ┌─────────────────────┐                 │
│  │   ContentVersion    │         │  ModificationLog    │                 │
│  │─────────────────────│         │─────────────────────│                 │
│  │ Id (PK)             │◄────────│ ContentVersionId(FK)│                 │
│  │ VersionNumber       │         │ ModificationType    │                 │
│  │ ContentBefore       │         │ TargetSelector      │                 │
│  │ ContentAfter        │         │ ValueBefore         │                 │
│  │ ModificationType    │         │ ValueAfter          │                 │
│  └─────────────────────┘         └─────────────────────┘                 │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## 🗄️ Yoast SEO Integration Tables

### YoastSettings Table

Stores Yoast integration configuration with seedable defaults.

```sql
CREATE TABLE IF NOT EXISTS YoastSettings (
    Id INTEGER PRIMARY KEY,
    SettingKey TEXT NOT NULL UNIQUE,
    SettingValue TEXT NOT NULL,
    SettingType TEXT NOT NULL DEFAULT 'string',
    Description TEXT,
    IsUserModified INTEGER DEFAULT 0,
    SeedVersion TEXT,
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IX_YoastSettings_Key ON YoastSettings(SettingKey);
```

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | INTEGER | PK | Auto-increment ID |
| SettingKey | TEXT | NOT NULL, UNIQUE | Setting identifier (dot notation) |
| SettingValue | TEXT | NOT NULL | JSON or scalar value |
| SettingType | TEXT | NOT NULL | Type: string, int, bool, array, json |
| Description | TEXT | | Human-readable description |
| IsUserModified | INTEGER | DEFAULT 0 | 1 if user changed from default |
| SeedVersion | TEXT | | Version of config that seeded this |
| CreatedAt | TEXT | | ISO 8601 timestamp |
| UpdatedAt | TEXT | | ISO 8601 timestamp |

### YoastAuditLog Table

Tracks all Yoast SEO field modifications for revert capability.

```sql
CREATE TABLE IF NOT EXISTS YoastAuditLog (
    Id INTEGER PRIMARY KEY,
    WpPostId INTEGER NOT NULL,
    PostType TEXT NOT NULL,
    ActionType TEXT NOT NULL,
    FieldModified TEXT NOT NULL,
    OldValue TEXT,
    NewValue TEXT,
    AutoGenerated INTEGER DEFAULT 1,
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IX_YoastAuditLog_PostId ON YoastAuditLog(WpPostId);
CREATE INDEX IX_YoastAuditLog_ActionType ON YoastAuditLog(ActionType);
CREATE INDEX IX_YoastAuditLog_CreatedAt ON YoastAuditLog(CreatedAt);
```

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | INTEGER | PK | Auto-increment ID |
| WpPostId | INTEGER | NOT NULL | WordPress post/page/term ID |
| PostType | TEXT | NOT NULL | post, page, category, etc. |
| ActionType | TEXT | NOT NULL | focus_keyword, multiple_keywords, meta_description |
| FieldModified | TEXT | NOT NULL | WordPress meta key modified |
| OldValue | TEXT | | Previous value (for revert) |
| NewValue | TEXT | | New value applied |
| AutoGenerated | INTEGER | DEFAULT 1 | 1 = auto, 0 = manual |
| CreatedAt | TEXT | | ISO 8601 timestamp |

### YoastOptimizationQueue Table

Background processing queue for batch Yoast optimizations.

```sql
CREATE TABLE IF NOT EXISTS YoastOptimizationQueue (
    Id INTEGER PRIMARY KEY,
    WpPostId INTEGER NOT NULL,
    PostType TEXT NOT NULL,
    OptimizationType TEXT NOT NULL,
    Status TEXT DEFAULT 'pending',
    Priority INTEGER DEFAULT 0,
    ScheduledAt TEXT,
    ProcessedAt TEXT,
    ErrorMessage TEXT,
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IX_YoastOptimizationQueue_Status ON YoastOptimizationQueue(Status);
CREATE INDEX IX_YoastOptimizationQueue_Type ON YoastOptimizationQueue(OptimizationType);
CREATE INDEX IX_YoastOptimizationQueue_Priority ON YoastOptimizationQueue(Priority DESC);
```

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | INTEGER | PK | Auto-increment ID |
| WpPostId | INTEGER | NOT NULL | WordPress post/page/term ID |
| PostType | TEXT | NOT NULL | post, page, category |
| OptimizationType | TEXT | NOT NULL | focus_keyword, multiple_keywords, meta_description |
| Status | TEXT | DEFAULT 'pending' | pending, processing, completed, failed, cancelled |
| Priority | INTEGER | DEFAULT 0 | Higher = processed first |
| ScheduledAt | TEXT | | When to process (null = immediate) |
| ProcessedAt | TEXT | | When processing completed |
| ErrorMessage | TEXT | | Error details if failed |
| CreatedAt | TEXT | | ISO 8601 timestamp |

---

## 🤖 AI Provider Tables (4 tables)

### AiProviders Table

Main table for AI provider configurations with seedable defaults.

```sql
CREATE TABLE IF NOT EXISTS AiProviders (
    Id INTEGER PRIMARY KEY,
    ProviderKey TEXT NOT NULL UNIQUE,
    DisplayName TEXT NOT NULL,
    ProviderType TEXT NOT NULL,
    BaseUrl TEXT NOT NULL,
    AuthType TEXT NOT NULL,
    IsEnabled INTEGER DEFAULT 0,
    IsSeeded INTEGER DEFAULT 0,
    IsUserModified INTEGER DEFAULT 0,
    SeedVersion TEXT,
    Priority INTEGER DEFAULT 100,
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IX_AiProviders_ProviderKey ON AiProviders(ProviderKey);
CREATE INDEX IX_AiProviders_IsEnabled ON AiProviders(IsEnabled);
CREATE INDEX IX_AiProviders_Priority ON AiProviders(Priority);
```

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | INTEGER | PK | Auto-increment ID |
| ProviderKey | TEXT | NOT NULL UNIQUE | Provider slug (e.g., `openai`, `gemini`) |
| DisplayName | TEXT | NOT NULL | User-facing name |
| ProviderType | TEXT | NOT NULL | openai, gemini, anthropic, mistral, groq, ollama, custom |
| BaseUrl | TEXT | NOT NULL | API base URL |
| AuthType | TEXT | NOT NULL | bearer, oauth2_client, oauth2_code, api_key_header, custom_header |
| IsEnabled | INTEGER | DEFAULT 0 | 0=disabled, 1=enabled |
| IsSeeded | INTEGER | DEFAULT 0 | 0=user-created, 1=seeded from config |
| IsUserModified | INTEGER | DEFAULT 0 | 0=pristine, 1=user changed |
| SeedVersion | TEXT | | Version from config.json |
| Priority | INTEGER | DEFAULT 100 | Sort order (lower = higher priority) |
| CreatedAt | TEXT | | ISO 8601 timestamp |
| UpdatedAt | TEXT | | ISO 8601 timestamp |

### AiProviderCredentials Table

Credential storage for AI providers (encrypted values).

```sql
CREATE TABLE IF NOT EXISTS AiProviderCredentials (
    Id INTEGER PRIMARY KEY,
    ProviderId INTEGER NOT NULL,
    CredentialKey TEXT NOT NULL,
    CredentialValue TEXT NOT NULL,
    IsRequired INTEGER DEFAULT 1,
    FieldType TEXT NOT NULL,
    FieldLabel TEXT NOT NULL,
    FieldPlaceholder TEXT,
    FieldOrder INTEGER DEFAULT 0,
    ValidationRegex TEXT,
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ProviderId) REFERENCES AiProviders(Id) ON DELETE CASCADE,
    UNIQUE(ProviderId, CredentialKey)
);

CREATE INDEX IX_AiProviderCredentials_ProviderId ON AiProviderCredentials(ProviderId);
```

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | INTEGER | PK | Auto-increment ID |
| ProviderId | INTEGER | FK → AiProviders.Id | Parent provider |
| CredentialKey | TEXT | NOT NULL | Key name (e.g., `api_key`, `client_id`) |
| CredentialValue | TEXT | NOT NULL | Encrypted value |
| IsRequired | INTEGER | DEFAULT 1 | 0=optional, 1=required |
| FieldType | TEXT | NOT NULL | text, password, textarea, select |
| FieldLabel | TEXT | NOT NULL | UI label |
| FieldPlaceholder | TEXT | | Placeholder text |
| FieldOrder | INTEGER | DEFAULT 0 | Display order in form |
| ValidationRegex | TEXT | | Optional validation pattern |
| CreatedAt | TEXT | | ISO 8601 timestamp |
| UpdatedAt | TEXT | | ISO 8601 timestamp |

### AiModels Table

Model configurations per provider with customizable display names.

```sql
CREATE TABLE IF NOT EXISTS AiModels (
    Id INTEGER PRIMARY KEY,
    ProviderId INTEGER NOT NULL,
    ModelId TEXT NOT NULL,
    DisplayName TEXT NOT NULL,
    ModelCategory TEXT NOT NULL,
    IsDefault INTEGER DEFAULT 0,
    IsEnabled INTEGER DEFAULT 1,
    MaxTokens INTEGER,
    CostPer1kInput REAL,
    CostPer1kOutput REAL,
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ProviderId) REFERENCES AiProviders(Id) ON DELETE CASCADE,
    UNIQUE(ProviderId, ModelId)
);

CREATE INDEX IX_AiModels_ProviderId ON AiModels(ProviderId);
CREATE INDEX IX_AiModels_IsDefault ON AiModels(IsDefault);
CREATE INDEX IX_AiModels_Category ON AiModels(ModelCategory);
```

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | INTEGER | PK | Auto-increment ID |
| ProviderId | INTEGER | FK → AiProviders.Id | Parent provider |
| ModelId | TEXT | NOT NULL | Official model ID (e.g., `gpt-4o`) |
| DisplayName | TEXT | NOT NULL | User-customizable name |
| ModelCategory | TEXT | NOT NULL | chat, embedding, vision, code |
| IsDefault | INTEGER | DEFAULT 0 | Default model for this provider |
| IsEnabled | INTEGER | DEFAULT 1 | 0=hidden, 1=available |
| MaxTokens | INTEGER | | Context window size |
| CostPer1kInput | REAL | | Cost tracking (optional) |
| CostPer1kOutput | REAL | | Cost tracking (optional) |
| CreatedAt | TEXT | | ISO 8601 timestamp |
| UpdatedAt | TEXT | | ISO 8601 timestamp |

### AiOAuthSessions Table

OAuth token storage for providers using OAuth 2.0.

```sql
CREATE TABLE IF NOT EXISTS AiOAuthSessions (
    Id INTEGER PRIMARY KEY,
    ProviderId INTEGER NOT NULL,
    AccessToken TEXT NOT NULL,
    RefreshToken TEXT,
    TokenType TEXT DEFAULT 'Bearer',
    ExpiresAt TEXT,
    Scope TEXT,
    State TEXT,
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ProviderId) REFERENCES AiProviders(Id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX IX_AiOAuthSessions_ProviderId ON AiOAuthSessions(ProviderId);
```

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | INTEGER | PK | Auto-increment ID |
| ProviderId | INTEGER | FK → AiProviders.Id | Parent provider |
| AccessToken | TEXT | NOT NULL | Encrypted access token |
| RefreshToken | TEXT | | Encrypted refresh token |
| TokenType | TEXT | DEFAULT 'Bearer' | Token type |
| ExpiresAt | TEXT | | Token expiry (ISO 8601) |
| Scope | TEXT | | Granted scopes |
| State | TEXT | | CSRF state for OAuth flow |
| CreatedAt | TEXT | | ISO 8601 timestamp |
| UpdatedAt | TEXT | | ISO 8601 timestamp |

---

## ✅ Acceptance Criteria

### Main Database
- [ ] All 29 tables created with proper indexes (9 core + 4 internal linking + 4 health monitor + 5 notification + 3 Yoast + 4 AI provider)
- [ ] Foreign key constraints enforced
- [ ] WAL mode enabled for concurrency
- [ ] PascalCase column naming

### Internal Linking Tables
- [ ] LinkTarget table with URL uniqueness constraint
- [ ] LinkTemplate with default template auto-inserted
- [ ] LinkVariable with JSON values storage
- [ ] InternalLink tracking created links

### Health Monitor Tables
- [ ] LinkHealthChecks with scheduling and SSL tracking
- [ ] HealthAlerts with severity and acknowledgment
- [ ] HealthCheckJobs for batch progress tracking
- [ ] HealthExclusions with pattern uniqueness

### Notification Tables
- [ ] NotificationQueue with priority and status tracking
- [ ] NotificationRecipients with digest preferences
- [ ] WebhookEndpoints with auth and retry configuration
- [ ] NotificationLog for delivery auditing
- [ ] NotificationSettings with default values seeded

### Yoast SEO Tables
- [ ] YoastSettings with seedable configuration
- [ ] YoastAuditLog for change tracking and revert
- [ ] YoastOptimizationQueue for batch processing

### AI Provider Tables
- [ ] AiProviders with seedable defaults and user modification tracking
- [ ] AiProviderCredentials with encrypted storage and dynamic fields
- [ ] AiModels with customizable display names per provider
- [ ] AiOAuthSessions with token refresh support

### History Database
- [ ] Dynamically created per content
- [ ] 2 tables with proper indexes
- [ ] Version numbering works correctly
- [ ] Path follows convention: `{type}/{id}-{slug}.db`

### Connection Management
- [ ] Singleton pattern for main DB
- [ ] Connection pooling for history DBs
- [ ] Proper cleanup on deactivation

---

## 📝 Related Specifications

- `05-data-folder-structure.md` - Folder organization
- `08-entity-models.md` - ORM models
- `12-history-service.md` - History management
- `13-snapshot-service.md` - Snapshot management

---

*Database columns use PascalCase following the project convention.*
