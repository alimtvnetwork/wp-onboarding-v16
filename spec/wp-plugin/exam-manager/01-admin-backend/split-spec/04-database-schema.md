# 02 - Database Schema

> **Phase:** Foundation  
> **Dependencies:** `01-plugin-structure.md`  
> **Estimated Time:** 4-6 hours  
> **Last Updated:** 2026-01-26

---

## ⚠️ NAMING CONVENTION (CRITICAL)

> **Database columns use PascalCase** (e.g., `UserId`, `CreatedAt`, `IsEnabled`)  
> **ORM properties use camelCase** (e.g., `userId`, `createdAt`, `isEnabled`)  
> This distinction prevents confusion between database layer and application layer.

---

## 📋 Scope

Create SQLite database connection wrapper and define all table schemas.

---

## 🗄️ Database Connection

**File:** `src/Database/Connection.php`

```php
<?php
namespace ExamQuestionsManager\Database;

use PDO;
use PDOException;

class Connection {
    private static ?PDO $instance = null;
    
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                self::$instance = new PDO(
                    'sqlite:' . EQM_DB_PATH,
                    null,
                    null,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
                
                // Enable foreign keys
                self::$instance->exec('PRAGMA foreign_keys = ON');
                
            } catch (PDOException $e) {
                // Log error (Logger will be implemented in 05)
                throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }
        
        return self::$instance;
    }
    
    /**
     * Close connection (for testing/cleanup)
     */
    public static function close(): void {
        self::$instance = null;
    }
    
    /**
     * Check if database file exists
     */
    public static function exists(): bool {
        return file_exists(EQM_DB_PATH);
    }
}
```

---

## 🗄️ Schema Definition

**File:** `src/Database/Schema.php`

```php
<?php
namespace ExamQuestionsManager\Database;

class Schema {
    /**
     * Initialize all tables
     */
    public static function initialize(): void {
        $pdo = Connection::getInstance();
        
        // Create tables in dependency order
        self::createUserRoleTable($pdo);
        self::createExamPresetTable($pdo);  // Must be before exam (for FK)
        self::createExamTable($pdo);
        self::createExamInviteTable($pdo);  // Must be after exam, before participant
        self::createSecretKeyTable($pdo);
        self::createSecretKeyAccessTable($pdo);
        self::createWikiCategoryTable($pdo);
        self::createWikiTable($pdo);
        self::createWikiRevisionTable($pdo);
        self::createEmailTemplateTable($pdo);
        self::createEmailQueueTable($pdo);
        self::createExamPrerequisiteTable($pdo);
        self::createExamChecklistTable($pdo);
        self::createExamRubricTable($pdo);
        self::createParticipantTable($pdo);
        self::createProgressTable($pdo);
        self::createExtensionRequestTable($pdo);
        self::createParticipantChecklistTable($pdo);
        self::createNotificationTable($pdo);
        self::createAuditLogTable($pdo);
        // Rate limiting tables
        self::createRateLimitTable($pdo);
        self::createRateLockoutTable($pdo);
        // Theme tables
        self::createThemeTable($pdo);
        self::createThemeOverrideTable($pdo);
        // Cache metadata table (for file-based cache invalidation)
        self::createCacheMetaTable($pdo);
        // Feature flag tables
        self::createFeatureFlagTable($pdo);
        self::createFeatureFlagOverrideTable($pdo);
    }
    
    /**
     * Exam Preset Table
     * Reusable configuration templates with live reference linking
     */
    private static function createExamPresetTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ExamPreset (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                
                -- Identification
                Name VARCHAR(100) NOT NULL UNIQUE,
                Slug VARCHAR(100) NOT NULL UNIQUE,
                Description TEXT DEFAULT NULL,
                Category VARCHAR(30) NOT NULL DEFAULT 'GENERAL',
                
                -- Access Control Settings
                IsInviteOnly BOOLEAN NOT NULL DEFAULT 0,
                IsSecretKeyEnabled BOOLEAN NOT NULL DEFAULT 0,
                
                -- Deadline Settings
                SoftDeadlineDays INTEGER NOT NULL DEFAULT 7,
                HardDeadlineDays INTEGER NOT NULL DEFAULT 14,
                
                -- Extension Settings
                AllowExtensions BOOLEAN NOT NULL DEFAULT 1,
                MaxExtensionDays INTEGER NOT NULL DEFAULT 30,
                MaxExtensionRequests INTEGER NOT NULL DEFAULT 3,
                
                -- Notification Settings
                EnableNotifications BOOLEAN NOT NULL DEFAULT 1,
                ReminderDaysBefore TEXT DEFAULT '[7, 3, 1]',
                
                -- UI Settings
                ShowProgressBar BOOLEAN NOT NULL DEFAULT 1,
                ShowDeadlineCountdown BOOLEAN NOT NULL DEFAULT 1,
                
                -- Advanced Settings (JSON for extensibility)
                AdvancedSettings TEXT DEFAULT NULL,
                
                -- Seeding Metadata
                IsSeeded BOOLEAN NOT NULL DEFAULT 0,
                SeedVersion VARCHAR(20) DEFAULT NULL,
                
                -- Timestamps
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CreatedBy INTEGER DEFAULT NULL
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ExamPreset_Slug ON ExamPreset(Slug)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ExamPreset_Category ON ExamPreset(Category)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ExamPreset_IsSeeded ON ExamPreset(IsSeeded)");
    }
    
    private static function createUserRoleTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS UserRole (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                UserId INTEGER NOT NULL,
                Role VARCHAR(20) NOT NULL,
                AssignedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                AssignedBy INTEGER DEFAULT NULL,
                UNIQUE(UserId)
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_UserRole_UserId ON UserRole(UserId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_UserRole_Role ON UserRole(Role)");
    }
    
    private static function createExamTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS Exam (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                ParentExamId INTEGER DEFAULT NULL,
                Title VARCHAR(255) NOT NULL,
                Description TEXT NOT NULL,
                Slug VARCHAR(100) NOT NULL UNIQUE,
                MarkdownFilePath VARCHAR(255) NOT NULL,
                SoftDeadlineDays INTEGER NOT NULL DEFAULT 7,
                HardDeadlineDays INTEGER NOT NULL DEFAULT 14,
                
                -- Access Control
                IsInviteOnly BOOLEAN NOT NULL DEFAULT 0,
                IsSecretKeyEnabled BOOLEAN NOT NULL DEFAULT 0,
                IsEnabled BOOLEAN NOT NULL DEFAULT 1,
                
                -- Preset Linking (live reference)
                PresetId INTEGER DEFAULT NULL,
                
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CreatedBy INTEGER DEFAULT NULL,
                FOREIGN KEY (ParentExamId) REFERENCES Exam(Id) ON DELETE CASCADE,
                FOREIGN KEY (PresetId) REFERENCES ExamPreset(Id) ON DELETE SET NULL
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Exam_ParentExamId ON Exam(ParentExamId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Exam_Slug ON Exam(Slug)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Exam_IsEnabled ON Exam(IsEnabled)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Exam_IsInviteOnly ON Exam(IsInviteOnly)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Exam_PresetId ON Exam(PresetId)");
    }
    
    /**
     * Exam Invite Table
     * Stores pre-approved invites for invite-only exams.
     * Both email AND phone must match for the invite to be valid.
     */
    private static function createExamInviteTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ExamInvite (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                ExamId INTEGER NOT NULL,
                
                -- Identification (both required for invite-only validation)
                Email VARCHAR(255) NOT NULL,
                Phone VARCHAR(20) NOT NULL,
                
                -- Optional metadata
                Name VARCHAR(100) DEFAULT NULL,
                Notes TEXT DEFAULT NULL,
                
                -- Invite status
                Status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
                
                -- Tracking
                InvitedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                InvitedBy INTEGER DEFAULT NULL,
                AcceptedAt DATETIME DEFAULT NULL,
                ExpiresAt DATETIME DEFAULT NULL,
                
                -- Link to created participant (after signup)
                ParticipantId INTEGER DEFAULT NULL,
                
                UNIQUE(ExamId, Email),
                UNIQUE(ExamId, Phone),
                FOREIGN KEY (ExamId) REFERENCES Exam(Id) ON DELETE CASCADE,
                FOREIGN KEY (ParticipantId) REFERENCES Participant(Id) ON DELETE SET NULL
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ExamInvite_ExamId ON ExamInvite(ExamId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ExamInvite_Email ON ExamInvite(Email)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ExamInvite_Phone ON ExamInvite(Phone)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ExamInvite_Status ON ExamInvite(Status)");
    }
    
    private static function createSecretKeyTable(PDO $pdo): void {
        // SECURITY NOTE: Only KeyHash is stored. Plaintext key is shown ONCE at generation.
        // Never store KeyValue in database - it's a security risk.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS SecretKey (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                ExamId INTEGER NOT NULL,
                KeyHash VARCHAR(64) NOT NULL UNIQUE,
                KeyPrefix VARCHAR(8) DEFAULT NULL,
                Label VARCHAR(255) DEFAULT NULL,
                IsActive BOOLEAN NOT NULL DEFAULT 1,
                UsageLimit INTEGER DEFAULT NULL,
                UsageCount INTEGER NOT NULL DEFAULT 0,
                ExpiresAt DATETIME DEFAULT NULL,
                AllowedIpPattern VARCHAR(255) DEFAULT NULL,
                Metadata TEXT DEFAULT NULL,
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CreatedBy INTEGER DEFAULT NULL,
                LastUsedAt DATETIME DEFAULT NULL,
                FOREIGN KEY (ExamId) REFERENCES Exam(Id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_SecretKey_ExamId ON SecretKey(ExamId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_SecretKey_KeyHash ON SecretKey(KeyHash)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_SecretKey_IsActive ON SecretKey(IsActive)");
    }
    
    private static function createSecretKeyAccessTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS SecretKeyAccess (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                SecretKeyId INTEGER NOT NULL,
                IpAddress VARCHAR(45) DEFAULT NULL,
                IpAddressHash VARCHAR(64) DEFAULT NULL,
                UserAgent TEXT DEFAULT NULL,
                Referrer VARCHAR(500) DEFAULT NULL,
                CookieId VARCHAR(64) DEFAULT NULL,
                CountryCode VARCHAR(2) DEFAULT NULL,
                City VARCHAR(100) DEFAULT NULL,
                SessionDuration INTEGER DEFAULT NULL,
                AccessedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (SecretKeyId) REFERENCES SecretKey(Id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_SecretKeyAccess_SecretKeyId ON SecretKeyAccess(SecretKeyId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_SecretKeyAccess_IpAddressHash ON SecretKeyAccess(IpAddressHash)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_SecretKeyAccess_AccessedAt ON SecretKeyAccess(AccessedAt)");
    }
    
    private static function createWikiCategoryTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS WikiCategory (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                ParentId INTEGER DEFAULT NULL,
                Name VARCHAR(100) NOT NULL,
                Slug VARCHAR(100) NOT NULL UNIQUE,
                Description TEXT DEFAULT NULL,
                DisplayOrder INTEGER NOT NULL DEFAULT 0,
                Depth INTEGER NOT NULL DEFAULT 0,
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (ParentId) REFERENCES WikiCategory(Id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_WikiCategory_ParentId ON WikiCategory(ParentId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_WikiCategory_Slug ON WikiCategory(Slug)");
    }
    
    private static function createWikiTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS Wiki (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                Title VARCHAR(255) NOT NULL,
                Slug VARCHAR(100) NOT NULL UNIQUE,
                CategoryId INTEGER DEFAULT NULL,
                Content TEXT NOT NULL,
                Visibility VARCHAR(20) NOT NULL DEFAULT 'PRIVATE',
                VisibilityRoles TEXT DEFAULT NULL,
                AuthorId INTEGER NOT NULL,
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (CategoryId) REFERENCES WikiCategory(Id) ON DELETE SET NULL
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Wiki_Slug ON Wiki(Slug)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Wiki_CategoryId ON Wiki(CategoryId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Wiki_Visibility ON Wiki(Visibility)");
    }
    
    private static function createWikiRevisionTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS WikiRevision (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                WikiId INTEGER NOT NULL,
                Content TEXT NOT NULL,
                RevisionNumber INTEGER NOT NULL,
                ChangedBy INTEGER NOT NULL,
                ChangeNote VARCHAR(255) DEFAULT NULL,
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(WikiId, RevisionNumber),
                FOREIGN KEY (WikiId) REFERENCES Wiki(Id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_WikiRevision_WikiId ON WikiRevision(WikiId)");
    }
    
    private static function createEmailTemplateTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS EmailTemplate (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                TemplateKey VARCHAR(50) NOT NULL UNIQUE,
                Subject VARCHAR(255) NOT NULL,
                Body TEXT NOT NULL,
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_EmailTemplate_TemplateKey ON EmailTemplate(TemplateKey)");
    }
    
    private static function createEmailQueueTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS EmailQueue (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                RecipientEmail VARCHAR(255) NOT NULL,
                RecipientName VARCHAR(100) DEFAULT NULL,
                Subject VARCHAR(255) NOT NULL,
                Body TEXT NOT NULL,
                TemplateKey VARCHAR(50) DEFAULT NULL,
                Priority VARCHAR(10) NOT NULL DEFAULT 'NORMAL',
                Status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
                Attempts INTEGER NOT NULL DEFAULT 0,
                MaxAttempts INTEGER NOT NULL DEFAULT 3,
                LastAttemptAt DATETIME DEFAULT NULL,
                NextAttemptAt DATETIME DEFAULT NULL,
                SentAt DATETIME DEFAULT NULL,
                ErrorMessage TEXT DEFAULT NULL,
                Metadata TEXT DEFAULT NULL,
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (TemplateKey) REFERENCES EmailTemplate(TemplateKey) ON DELETE SET NULL
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_EmailQueue_Status ON EmailQueue(Status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_EmailQueue_Priority ON EmailQueue(Priority)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_EmailQueue_NextAttemptAt ON EmailQueue(NextAttemptAt)");
    }
    
    private static function createExamPrerequisiteTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ExamPrerequisite (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                ExamId INTEGER NOT NULL,
                Type VARCHAR(20) NOT NULL,
                Title VARCHAR(255) NOT NULL,
                Url VARCHAR(500) DEFAULT NULL,
                Description TEXT DEFAULT NULL,
                DisplayOrder INTEGER NOT NULL DEFAULT 0,
                IsRequired BOOLEAN NOT NULL DEFAULT 1,
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (ExamId) REFERENCES Exam(Id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ExamPrerequisite_ExamId ON ExamPrerequisite(ExamId)");
    }
    
    private static function createExamChecklistTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ExamChecklist (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                ExamId INTEGER NOT NULL,
                Phase VARCHAR(20) NOT NULL,
                ItemType VARCHAR(30) NOT NULL,
                Title VARCHAR(255) NOT NULL,
                Description TEXT DEFAULT NULL,
                IsRequired BOOLEAN NOT NULL DEFAULT 1,
                
                -- Submission Type Configuration
                SubmissionType VARCHAR(30) NOT NULL DEFAULT 'CHECKBOX',
                ValidationMode VARCHAR(30) NOT NULL DEFAULT 'FLAG_FOR_REVIEW',
                ValidationConfig TEXT DEFAULT NULL,
                
                -- Evidence (legacy, use SubmissionType instead)
                RequiresEvidence BOOLEAN NOT NULL DEFAULT 0,
                EvidenceType VARCHAR(20) DEFAULT NULL,
                
                TimeLimit INTEGER DEFAULT NULL,
                DisplayOrder INTEGER NOT NULL DEFAULT 0,
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (ExamId) REFERENCES Exam(Id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ExamChecklist_ExamId ON ExamChecklist(ExamId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ExamChecklist_Phase ON ExamChecklist(ExamId, Phase)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ExamChecklist_SubmissionType ON ExamChecklist(SubmissionType)");
    }
    
    private static function createExamRubricTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ExamRubric (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                ExamId INTEGER NOT NULL,
                CriterionTitle VARCHAR(255) NOT NULL,
                Description TEXT DEFAULT NULL,
                IsRequired BOOLEAN NOT NULL DEFAULT 1,
                DisplayOrder INTEGER NOT NULL DEFAULT 0,
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (ExamId) REFERENCES Exam(Id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ExamRubric_ExamId ON ExamRubric(ExamId)");
    }
    
    private static function createParticipantTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS Participant (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                ExamId INTEGER NOT NULL,
                
                -- User Identity (nullable for anonymous participants)
                UserId INTEGER DEFAULT NULL,
                Email VARCHAR(255) NOT NULL,
                PasswordHash VARCHAR(255) DEFAULT NULL,
                Name VARCHAR(100) DEFAULT NULL,
                Whatsapp VARCHAR(20) DEFAULT NULL,
                LinkedinUrl VARCHAR(255) DEFAULT NULL,
                
                -- Status & Progress
                Status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
                ProgressPercent INTEGER NOT NULL DEFAULT 0,
                
                -- Deadline Tracking (original + effective)
                OriginalSoftDeadline DATETIME NOT NULL,
                OriginalHardDeadline DATETIME NOT NULL,
                SoftDeadlineDate DATETIME NOT NULL,
                HardDeadlineDate DATETIME NOT NULL,
                ExtensionDeadlineDate DATETIME DEFAULT NULL,
                
                -- Deadline Override (admin manual override)
                DeadlineOverride DATETIME DEFAULT NULL,
                DeadlineOverrideReason TEXT DEFAULT NULL,
                DeadlineOverrideBy INTEGER DEFAULT NULL,
                
                -- Notification Tracking (prevents duplicate notifications)
                SoftDeadlineNotifiedAt DATETIME DEFAULT NULL,
                HardDeadlineNotifiedAt DATETIME DEFAULT NULL,
                ExtensionExpiringNotifiedAt DATETIME DEFAULT NULL,
                
                -- Anonymous Participant Tracking
                TrackingId VARCHAR(64) DEFAULT NULL,
                AccessMethod VARCHAR(30) NOT NULL DEFAULT 'SIGNUP',
                SecretKeyId INTEGER DEFAULT NULL,
                
                -- Migration Metadata
                MigratedAt DATETIME DEFAULT NULL,
                MigratedFromTrackingId VARCHAR(64) DEFAULT NULL,
                
                -- Timestamps
                SignupDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                LastActivityAt DATETIME DEFAULT NULL,
                DeletedAt DATETIME DEFAULT NULL,
                DeletedReason TEXT DEFAULT NULL,
                
                UNIQUE(ExamId, Email),
                UNIQUE(TrackingId),
                FOREIGN KEY (ExamId) REFERENCES Exam(Id) ON DELETE CASCADE,
                FOREIGN KEY (SecretKeyId) REFERENCES SecretKey(Id) ON DELETE SET NULL
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Participant_ExamId ON Participant(ExamId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Participant_Email ON Participant(Email)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Participant_Status ON Participant(Status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Participant_ProgressPercent ON Participant(ProgressPercent)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Participant_UserId ON Participant(UserId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Participant_TrackingId ON Participant(TrackingId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Participant_SoftDeadlineDate ON Participant(SoftDeadlineDate)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Participant_HardDeadlineDate ON Participant(HardDeadlineDate)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Participant_AccessMethod ON Participant(AccessMethod)");
    }
    
    private static function createProgressTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS Progress (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                ParticipantId INTEGER NOT NULL,
                SectionNumber INTEGER NOT NULL,
                SectionTitle VARCHAR(255) NOT NULL,
                IsMarkedDone BOOLEAN NOT NULL DEFAULT 0,
                CompletedAt DATETIME DEFAULT NULL,
                UNIQUE(ParticipantId, SectionNumber),
                FOREIGN KEY (ParticipantId) REFERENCES Participant(Id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Progress_ParticipantId ON Progress(ParticipantId)");
    }
    
    private static function createExtensionRequestTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ExtensionRequest (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                ParticipantId INTEGER NOT NULL,
                Reason TEXT NOT NULL,
                RequestedDays INTEGER NOT NULL,
                AttachedFilePath VARCHAR(255) DEFAULT NULL,
                Status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
                GrantedDays INTEGER DEFAULT NULL,
                AdminNote TEXT DEFAULT NULL,
                DenialReason TEXT DEFAULT NULL,
                RequestedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ReviewedAt DATETIME DEFAULT NULL,
                ReviewedBy INTEGER DEFAULT NULL,
                ExpiresAt DATETIME DEFAULT NULL,
                FOREIGN KEY (ParticipantId) REFERENCES Participant(Id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ExtensionRequest_ParticipantId ON ExtensionRequest(ParticipantId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ExtensionRequest_Status ON ExtensionRequest(Status)");
    }
    
    private static function createParticipantChecklistTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ParticipantChecklist (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                ParticipantId INTEGER NOT NULL,
                ChecklistId INTEGER NOT NULL,
                IsCompleted BOOLEAN NOT NULL DEFAULT 0,
                CompletedAt DATETIME DEFAULT NULL,
                
                -- Submission Data (for submission types other than CHECKBOX)
                SubmissionValue TEXT DEFAULT NULL,
                SubmissionFilePath VARCHAR(255) DEFAULT NULL,
                SubmittedAt DATETIME DEFAULT NULL,
                
                -- Review Status (for FLAG_FOR_REVIEW validation mode)
                ReviewStatus VARCHAR(30) DEFAULT NULL,
                ReviewedAt DATETIME DEFAULT NULL,
                ReviewedBy INTEGER DEFAULT NULL,
                ReviewNote TEXT DEFAULT NULL,
                
                UNIQUE(ParticipantId, ChecklistId),
                FOREIGN KEY (ParticipantId) REFERENCES Participant(Id) ON DELETE CASCADE,
                FOREIGN KEY (ChecklistId) REFERENCES ExamChecklist(Id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ParticipantChecklist_ParticipantId ON ParticipantChecklist(ParticipantId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ParticipantChecklist_ReviewStatus ON ParticipantChecklist(ReviewStatus)");
    }
    
    private static function createNotificationTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS Notification (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                UserId INTEGER NOT NULL,
                Type VARCHAR(50) NOT NULL,
                Title VARCHAR(255) NOT NULL,
                Message TEXT NOT NULL,
                Severity VARCHAR(20) NOT NULL DEFAULT 'INFO',
                EntityType VARCHAR(50) DEFAULT NULL,
                EntityId INTEGER DEFAULT NULL,
                ActionUrl VARCHAR(500) DEFAULT NULL,
                IsRead BOOLEAN NOT NULL DEFAULT 0,
                ReadAt DATETIME DEFAULT NULL,
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Notification_UserId ON Notification(UserId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Notification_IsRead ON Notification(IsRead)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Notification_Type ON Notification(Type)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Notification_CreatedAt ON Notification(CreatedAt)");
    }
    
    private static function createAuditLogTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS AuditLog (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                EventType VARCHAR(50) NOT NULL,
                EventCategory VARCHAR(30) NOT NULL,
                ActorId INTEGER DEFAULT NULL,
                ActorType VARCHAR(20) NOT NULL DEFAULT 'USER',
                Action VARCHAR(100) NOT NULL,
                TargetEntityType VARCHAR(50) DEFAULT NULL,
                TargetEntityId INTEGER DEFAULT NULL,
                IpAddressHash VARCHAR(64) DEFAULT NULL,
                UserAgent TEXT DEFAULT NULL,
                PreviousValue TEXT DEFAULT NULL,
                NewValue TEXT DEFAULT NULL,
                Context TEXT DEFAULT NULL,
                RequestId VARCHAR(36) DEFAULT NULL,
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_AuditLog_EventType ON AuditLog(EventType)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_AuditLog_EventCategory ON AuditLog(EventCategory)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_AuditLog_ActorId ON AuditLog(ActorId)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_AuditLog_TargetEntityType ON AuditLog(TargetEntityType)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_AuditLog_CreatedAt ON AuditLog(CreatedAt)");
    }
    
    /**
     * Rate Limiting Tables
     * Implements sliding window rate limiting per IP address
     */
    private static function createRateLimitTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS RateLimit (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                IpAddressHash VARCHAR(64) NOT NULL,
                Category VARCHAR(30) NOT NULL,
                Endpoint VARCHAR(100) DEFAULT NULL,
                RequestCount INTEGER NOT NULL DEFAULT 1,
                WindowStart DATETIME NOT NULL,
                WindowEnd DATETIME NOT NULL,
                LastRequestAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(IpAddressHash, Category, WindowStart)
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_RateLimit_IpAddressHash ON RateLimit(IpAddressHash)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_RateLimit_Category ON RateLimit(Category)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_RateLimit_WindowEnd ON RateLimit(WindowEnd)");
    }
    
    private static function createRateLockoutTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS RateLockout (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                IpAddressHash VARCHAR(64) NOT NULL,
                Category VARCHAR(30) NOT NULL,
                LockedUntil DATETIME NOT NULL,
                Reason VARCHAR(255) DEFAULT NULL,
                ViolationCount INTEGER NOT NULL DEFAULT 1,
                CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(IpAddressHash, Category)
            )
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_RateLockout_IpAddressHash ON RateLockout(IpAddressHash)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS IX_RateLockout_LockedUntil ON RateLockout(LockedUntil)");
    }
    
    /**
     * Drop all tables (for testing/reset)
     */
    public static function dropAll(): void {
        $pdo = Connection::getInstance();
        
        $tables = [
            'AuditLog',
            'Notification',
            'ParticipantChecklist',
            'ExtensionRequest',
            'Progress',
            'Participant',
            'ExamRubric',
            'ExamChecklist',
            'ExamPrerequisite',
            'EmailQueue',
            'EmailTemplate',
            'WikiRevision',
            'Wiki',
            'WikiCategory',
            'SecretKeyAccess',
            'SecretKey',
            'ExamInvite',
            'Exam',
            'ExamPreset',
            'UserRole',
            'RateLimit',
            'RateLockout',
            'Theme',
            'ThemeOverride',
            'CacheMeta',
            'FeatureFlag',
            'FeatureFlagOverride',
        ];
        
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
```

---

## 📊 Entity Relationship Summary

```
┌──────────────┐         ┌──────────────┐
│   UserRole   │         │     Exam     │──────────────┐
└──────────────┘         └──────┬───────┘              │
                                │ (self-ref: ParentExamId)
                         ┌──────┴───────┐              │
                         │              │              │
              ┌──────────▼────┐   ┌─────▼─────────┐    │
              │  SecretKey    │   │ExamPrerequisite│   │
              └───────┬───────┘   └───────────────┘    │
                      │                                 │
              ┌───────▼───────┐   ┌─────────────────┐  │
              │SecretKeyAccess│   │  ExamChecklist  │◄─┘
              └───────────────┘   └────────┬────────┘
                                           │
              ┌───────────────┐   ┌────────▼────────┐
              │   ExamRubric  │   │   Participant   │
              └───────────────┘   └───┬────────┬────┘
                                      │        │
                              ┌───────▼──┐  ┌──▼──────────────┐
                              │ Progress │  │ ExtensionRequest│
                              └──────────┘  └─────────────────┘
                                      │
                              ┌───────▼────────────┐
                              │ParticipantChecklist│
                              └────────────────────┘

┌──────────────┐    ┌──────────────┐         ┌──────────────┐
│ WikiCategory │◄───│     Wiki     │◄────────│ WikiRevision │
└──────────────┘    └──────────────┘         └──────────────┘
      ▲ (self-ref: ParentId)

┌──────────────┐         ┌──────────────┐
│EmailTemplate │◄────────│  EmailQueue  │
└──────────────┘         └──────────────┘

┌──────────────┐         ┌──────────────┐
│ Notification │         │   AuditLog   │
└──────────────┘         └──────────────┘
```

---

## 📋 Table Summary

| Table | Description | Key Fields |
|-------|-------------|------------|
| `UserRole` | Plugin role assignments | UserId, Role, AssignedBy |
| `ExamPreset` | Reusable exam configuration templates | Name, Category, Deadlines, IsSeeded |
| `Exam` | Exam definitions | ParentExamId, Slug, IsInviteOnly, PresetId |
| `ExamInvite` | Pre-approved invites for invite-only exams | ExamId, Email, Phone, Status |
| `SecretKey` | Secret access keys | KeyHash, ExpiresAt, UsageLimit |
| `SecretKeyAccess` | Access logs | IpAddressHash, UserAgent, Referrer |
| `WikiCategory` | Wiki category hierarchy | ParentId, Slug, Depth |
| `Wiki` | Wiki pages | CategoryId, Visibility, VisibilityRoles |
| `WikiRevision` | Wiki change history | RevisionNumber, Content |
| `EmailTemplate` | Email templates | TemplateKey, Subject, Body |
| `EmailQueue` | Queued emails | Status, Priority, Attempts |
| `ExamPrerequisite` | Exam prerequisites | Type, Url, DisplayOrder |
| `ExamChecklist` | Exam checklists | Phase, SubmissionType, ValidationMode |
| `ExamRubric` | Grading rubrics | CriterionTitle |
| `Participant` | Exam participants | Email, Status, Deadlines, TrackingId |
| `Progress` | **DEPRECATED** - Section completion | SectionNumber, IsMarkedDone |
| `ExtensionRequest` | Extension requests | Reason, GrantedDays, Status |
| `ParticipantChecklist` | Checklist submissions & completion | ChecklistId, SubmissionValue, ReviewStatus |
| `Notification` | In-app notifications | Type, Severity, IsRead |
| `AuditLog` | Audit trail | EventType, ActorId, Action |
| `RateLimit` | Rate limiting windows | IpAddressHash, Category, RequestCount |
| `RateLockout` | IP lockouts | IpAddressHash, LockedUntil |
| `Theme` | UI themes | Slug, Scope, IsActive |
| `ThemeOverride` | Theme overrides per exam | ThemeId, ExamId, OverrideKey |
| `CacheMeta` | Cache entry metadata | CacheKey, Tags, ExpiresAt |
| `FeatureFlag` | Feature flags | FlagKey, IsEnabled, RolloutPercentage |
| `FeatureFlagOverride` | Feature flag overrides | FlagKey, OverrideType, TargetId |

---

## 📊 Progress Tracking Clarification

> **IMPORTANT**: Use `ParticipantChecklist` for ALL progress tracking.

### Which Table to Use?

| Use Case | Table | Reason |
|----------|-------|--------|
| Section completion (H2 sections) | `ParticipantChecklist` | Sections are extracted as `IN_EXAM` phase checklist items |
| Prerequisite completion | `ParticipantChecklist` | Prerequisites are `PRE` phase checklist items |
| Post-exam tasks | `ParticipantChecklist` | Post tasks are `POST` phase checklist items |
| **Legacy/deprecated** | `Progress` | Only for backward compatibility |

### How Section Mapping Works

1. H2 sections in exam Markdown are extracted during exam creation
2. Each section becomes a checklist item with `Phase='IN_EXAM'` and `metadata.sectionNumber`
3. Frontend calls `POST /participants/{id}/sections/{sectionNumber}/complete`
4. Backend maps sectionNumber → ChecklistId and creates `ParticipantChecklist` record
5. Progress percentage is calculated from `ParticipantChecklist` completions

### The `Progress` Table (DEPRECATED)

The `Progress` table exists for legacy reasons but should NOT be used for new implementations:
- It duplicates data that should be in `ParticipantChecklist`
- It lacks the flexibility of the checklist system (phases, evidence, etc.)
- Future versions may remove this table

---

## ✅ Acceptance Criteria

### Database Connection
- [ ] Connection singleton returns PDO instance
- [ ] Foreign keys enabled via PRAGMA
- [ ] Connection uses exception error mode
- [ ] Database file created at correct path

### Table Creation
- [ ] All 27 tables created without errors
- [ ] Tables created in correct dependency order
- [ ] Foreign keys properly reference parent tables
- [ ] ON DELETE CASCADE works correctly

### Field Naming
- [ ] All fields use PascalCase (not snake_case or camelCase)
- [ ] Boolean fields use `Is` or `Has` prefix
- [ ] Timestamps use `CreatedAt`, `UpdatedAt` convention

### Indexes
- [ ] Primary key indexes created automatically
- [ ] Foreign key columns have indexes
- [ ] Frequently queried columns have indexes
- [ ] Index names follow `IX_{Table}_{Column}` convention

### Schema Operations
- [ ] `Schema::initialize()` can be called multiple times safely (IF NOT EXISTS)
- [ ] `Schema::dropAll()` removes all tables in correct order

---

## 📝 Notes

- SQLite uses `BOOLEAN` which maps to INTEGER (0/1)
- `VisibilityRoles` in Wiki table stores JSON array as TEXT
- `IpAddress` stored in plain text, `IpAddressHash` for unique counting
- All foreign keys cascade on delete for clean orphan removal
- `WikiCategory.ParentId` is self-referential for hierarchy (max 5 levels enforced in code)
- `EmailQueue.Metadata` stores JSON context for template variable substitution
- `AuditLog.Context` stores extensible JSON for additional event details
- `Notification.EntityType` + `EntityId` enable linking to related records

---

## 🎨 Theme Table

**Purpose:** Store customizable themes for admin and frontend UI.

```php
private static function createThemeTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Theme (
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            
            -- Identification
            Slug VARCHAR(50) NOT NULL UNIQUE,
            Name VARCHAR(100) NOT NULL,
            Scope VARCHAR(20) NOT NULL DEFAULT 'SHARED',  -- ADMIN, FRONTEND, SHARED
            
            -- State
            IsActive BOOLEAN NOT NULL DEFAULT 0,
            IsDefault BOOLEAN NOT NULL DEFAULT 0,  -- Seed themes, cannot be deleted
            
            -- Configuration (full JSON theme config)
            Config TEXT NOT NULL,  -- JSON: colors, typography, spacing, etc.
            
            -- Metadata
            CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CreatedBy INTEGER DEFAULT NULL,
            
            FOREIGN KEY (CreatedBy) REFERENCES UserRole(Id) ON DELETE SET NULL
        )
    ");
    
    // Index for scope queries
    $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Theme_Scope ON Theme(Scope)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS IX_Theme_IsActive ON Theme(IsActive)");
}
```

---

## 🎨 Theme Override Table

**Purpose:** Store exam-specific or user-specific theme overrides.

```php
private static function createThemeOverrideTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ThemeOverride (
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            
            -- Reference
            ThemeId INTEGER NOT NULL,
            ExamId INTEGER DEFAULT NULL,  -- NULL = global override
            
            -- Override data
            OverrideKey VARCHAR(100) NOT NULL,  -- Dot-notation path (e.g., 'colors.primary.base')
            OverrideValue TEXT NOT NULL,         -- JSON value
            
            -- Metadata
            CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (ThemeId) REFERENCES Theme(Id) ON DELETE CASCADE,
            FOREIGN KEY (ExamId) REFERENCES Exam(Id) ON DELETE CASCADE,
            UNIQUE(ThemeId, ExamId, OverrideKey)
        )
    ");
    
    $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ThemeOverride_ThemeId ON ThemeOverride(ThemeId)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS IX_ThemeOverride_ExamId ON ThemeOverride(ExamId)");
}
```

---

## 💾 Cache Metadata Table

**Purpose:** Track cache entries for file-based cache invalidation (when Memcached/Redis not available).

```php
private static function createCacheMetaTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS CacheMeta (
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            
            -- Cache key info
            CacheKey VARCHAR(255) NOT NULL UNIQUE,
            Tags TEXT DEFAULT NULL,  -- JSON array of tags
            
            -- Expiration
            ExpiresAt DATETIME DEFAULT NULL,
            
            -- Metadata
            CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            DataSize INTEGER DEFAULT 0  -- Size in bytes
        )
    ");
    
    $pdo->exec("CREATE INDEX IF NOT EXISTS IX_CacheMeta_ExpiresAt ON CacheMeta(ExpiresAt)");
}

/**
 * Feature Flag Table
 * Seeded feature flags with rollout control
 */
private static function createFeatureFlagTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS FeatureFlag (
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            
            -- Identification
            FlagKey VARCHAR(100) NOT NULL UNIQUE,
            DisplayName VARCHAR(255) NOT NULL,
            Description TEXT DEFAULT NULL,
            
            -- State
            DefaultValue BOOLEAN NOT NULL DEFAULT 0,
            IsEnabled BOOLEAN NOT NULL DEFAULT 1,
            
            -- Categorization
            Category VARCHAR(50) NOT NULL DEFAULT 'general',
            
            -- Rollout control
            RolloutPercentage INTEGER NOT NULL DEFAULT 100,
            
            -- Timestamps
            CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec("CREATE INDEX IF NOT EXISTS IX_FeatureFlag_FlagKey ON FeatureFlag(FlagKey)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS IX_FeatureFlag_Category ON FeatureFlag(Category)");
}

/**
 * Feature Flag Override Table
 * Per-user, per-exam, or per-role overrides
 */
private static function createFeatureFlagOverrideTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS FeatureFlagOverride (
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            
            -- Override target
            FlagKey VARCHAR(100) NOT NULL,
            OverrideType VARCHAR(20) NOT NULL,  -- 'user', 'exam', 'role'
            TargetId INTEGER NOT NULL,          -- UserId, ExamId, or RoleId
            
            -- Override value
            IsEnabled BOOLEAN NOT NULL,
            Reason VARCHAR(255) DEFAULT NULL,
            
            -- Audit
            CreatedBy INTEGER NOT NULL,
            CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ExpiresAt DATETIME DEFAULT NULL,
            
            UNIQUE(FlagKey, OverrideType, TargetId)
        )
    ");
    
    $pdo->exec("CREATE INDEX IF NOT EXISTS IX_FeatureFlagOverride_Lookup ON FeatureFlagOverride(FlagKey, OverrideType, TargetId)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS IX_FeatureFlagOverride_ExpiresAt ON FeatureFlagOverride(ExpiresAt)");
}
```

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **ER Diagram** | [diagrams/01-database-er-diagram](../diagrams/01-database-er-diagram.md) | Visual table relationships |
| **ORM Base Classes** | [05-orm-base-classes](05-orm-base-classes.md) | Repository and entity patterns |
| **Entity Models** | [08-entity-models](08-entity-models.md) | PHP class definitions |
| **Enums & Constants** | [06-enums-constants](06-enums-constants.md) | Enum values stored in columns |
| **Exam Service** | [12-exam-service](12-exam-service.md) | Uses `Exam` table |
| **Participant Service** | [27-participant-service](27-participant-service.md) | Uses `Participant` table |
| **Secret Key Service** | [24-secret-key-service](24-secret-key-service.md) | Uses `SecretKey`, `SecretKeyAccess` tables |
| **Wiki Service** | [20-wiki-service](20-wiki-service.md) | Uses `Wiki`, `WikiCategory`, `WikiRevision` tables |
| **Email Queue** | [31-email-queue](31-email-queue.md) | Uses `EmailQueue`, `EmailTemplate` tables |
| **Audit Logging** | [46-audit-logging](46-audit-logging.md) | Uses `AuditLog` table |
| **Theming System** | [56-theming-system](56-theming-system.md) | Uses `Theme`, `ThemeOverride` tables |
| **Caching System** | [57-caching-system](57-caching-system.md) | Uses `CacheMeta` table |
| **Feature Flags** | [58-feature-flags](58-feature-flags.md) | Uses `FeatureFlag`, `FeatureFlagOverride` tables |

### Table Groups
- **Core**: `Exam`, `UserRole`, `ExamPreset`
- **Content**: `Wiki`, `WikiCategory`, `WikiRevision`
- **Access**: `SecretKey`, `SecretKeyAccess`, `Participant`, `ExamInvite`
- **Progress**: `Progress`, `ParticipantChecklist`, `ExtensionRequest`
- **Communication**: `EmailTemplate`, `EmailQueue`, `Notification`
- **System**: `AuditLog`, `RateLimit`, `RateLockout`, `FeatureFlag`, `FeatureFlagOverride`
- **UI/Config**: `Theme`, `ThemeOverride`, `CacheMeta`

---

*Next: `05-orm-base-classes.md`*
