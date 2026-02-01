# 04 - Enums & Constants

> **Phase:** Foundation  
> **Dependencies:** `01-plugin-structure.md`  
> **Estimated Time:** 2-3 hours

---

## 📋 Scope

Define all PHP 8.0+ enums used throughout the plugin. **No magic strings allowed** - all multi-option values must use enums.

---

## 📁 File Structure

```
/src/Enums/
├── ParticipantStatus.php
├── UserRoleType.php
├── WikiVisibility.php
├── ChecklistPhase.php
├── ChecklistItemType.php
├── EvidenceType.php
├── PrerequisiteType.php
├── ExtensionStatus.php
├── InviteStatus.php         ← NEW
├── AccessMethod.php         ← NEW
├── SubmissionType.php       ← NEW
├── SubmissionValidationMode.php  ← NEW
├── SubmissionReviewStatus.php    ← NEW
├── PresetCategory.php       ← NEW
├── EmailTemplateKey.php
├── EmailStatus.php
├── EmailPriority.php
├── LogLevel.php
├── DeadlineType.php
├── NotificationType.php
├── NotificationSeverity.php
└── AuditAction.php
```

---

## 🔖 ParticipantStatus

**File:** `src/Enums/ParticipantStatus.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum ParticipantStatus: string {
    case INVITED = 'INVITED';
    case ACTIVE = 'ACTIVE';
    case PAUSED = 'PAUSED';
    case SOFT_DEADLINE_REACHED = 'SOFT_DEADLINE_REACHED';
    case HARD_DEADLINE_REACHED = 'HARD_DEADLINE_REACHED';
    case EXTENDED = 'EXTENDED';
    case COMPLETED = 'COMPLETED';
    case LOCKED = 'LOCKED';
    case WITHDRAWN = 'WITHDRAWN';
    
    /**
     * Get allowed transitions from this status
     */
    public function allowedTransitions(): array {
        return match($this) {
            self::INVITED => [self::ACTIVE, self::WITHDRAWN],
            self::ACTIVE => [self::PAUSED, self::SOFT_DEADLINE_REACHED, self::COMPLETED, self::LOCKED, self::WITHDRAWN],
            self::PAUSED => [self::ACTIVE, self::WITHDRAWN],
            self::SOFT_DEADLINE_REACHED => [self::ACTIVE, self::HARD_DEADLINE_REACHED, self::EXTENDED, self::COMPLETED, self::LOCKED, self::WITHDRAWN],
            self::HARD_DEADLINE_REACHED => [self::EXTENDED, self::LOCKED],
            self::EXTENDED => [self::ACTIVE, self::SOFT_DEADLINE_REACHED, self::COMPLETED, self::LOCKED, self::WITHDRAWN],
            self::COMPLETED => [], // terminal
            self::LOCKED => [], // terminal
            self::WITHDRAWN => [], // terminal
        };
    }
    
    /**
     * Check if status is terminal (no further transitions)
     */
    public function isTerminal(): bool {
        return empty($this->allowedTransitions());
    }
    
    /**
     * Check if participant can mark sections
     */
    public function canMarkSections(): bool {
        return match($this) {
            self::ACTIVE,
            self::SOFT_DEADLINE_REACHED,
            self::EXTENDED => true,
            
            self::INVITED,
            self::PAUSED,
            self::HARD_DEADLINE_REACHED,
            self::LOCKED,
            self::COMPLETED,
            self::WITHDRAWN => false,
        };
    }
    
    /**
     * Check if participant can request extension
     */
    public function canRequestExtension(): bool {
        return match($this) {
            self::SOFT_DEADLINE_REACHED,
            self::HARD_DEADLINE_REACHED,
            self::LOCKED => true,
            
            default => false,
        };
    }
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::INVITED => 'Invited',
            self::ACTIVE => 'Active',
            self::PAUSED => 'Paused',
            self::SOFT_DEADLINE_REACHED => 'Soft Deadline Reached',
            self::HARD_DEADLINE_REACHED => 'Hard Deadline Reached',
            self::EXTENDED => 'Extended',
            self::COMPLETED => 'Completed',
            self::LOCKED => 'Locked',
            self::WITHDRAWN => 'Withdrawn',
        };
    }
    
    /**
     * Get badge color class
     */
    public function badgeClass(): string {
        return match($this) {
            self::INVITED => 'badge-secondary',
            self::ACTIVE => 'badge-success',
            self::PAUSED => 'badge-secondary',
            self::SOFT_DEADLINE_REACHED => 'badge-warning',
            self::HARD_DEADLINE_REACHED => 'badge-danger',
            self::EXTENDED => 'badge-info',
            self::COMPLETED => 'badge-primary',
            self::LOCKED => 'badge-danger',
            self::WITHDRAWN => 'badge-muted',
        };
    }
}
```

---

## 🔖 UserRoleType

**File:** `src/Enums/UserRoleType.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum UserRoleType: string {
    case ADMIN = 'ADMIN';
    case EXAM_EDITOR = 'EXAM_EDITOR';
    case EXAMINEE = 'EXAMINEE';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::ADMIN => 'Admin',
            self::EXAM_EDITOR => 'Exam Editor',
            self::EXAMINEE => 'Examinee',
        };
    }
    
    /**
     * Get icon
     */
    public function icon(): string {
        return match($this) {
            self::ADMIN => '👑',
            self::EXAM_EDITOR => '✏️',
            self::EXAMINEE => '📝',
        };
    }
    
    /**
     * Check if role can manage exams
     */
    public function canManageExams(): bool {
        return match($this) {
            self::ADMIN, self::EXAM_EDITOR => true,
            self::EXAMINEE => false,
        };
    }
    
    /**
     * Check if role can manage roles
     */
    public function canManageRoles(): bool {
        return $this === self::ADMIN;
    }
    
    /**
     * Check if role can manage settings
     */
    public function canManageSettings(): bool {
        return $this === self::ADMIN;
    }
    
    /**
     * Check if role can view logs
     */
    public function canViewLogs(): bool {
        return $this === self::ADMIN;
    }
    
    /**
     * Get priority (lower = more powerful)
     */
    public function priority(): int {
        return match($this) {
            self::ADMIN => 1,
            self::EXAM_EDITOR => 2,
            self::EXAMINEE => 3,
        };
    }
}
```

---

## 🔖 WikiVisibility

**File:** `src/Enums/WikiVisibility.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum WikiVisibility: string {
    case PUBLIC = 'PUBLIC';
    case AUTHENTICATED = 'AUTHENTICATED';
    case ROLE = 'ROLE';
    case PRIVATE = 'PRIVATE';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::PUBLIC => 'Public',
            self::AUTHENTICATED => 'Authenticated Users',
            self::ROLE => 'Specific Roles',
            self::PRIVATE => 'Private',
        };
    }
    
    /**
     * Get description
     */
    public function description(): string {
        return match($this) {
            self::PUBLIC => 'Visible to everyone, no login required',
            self::AUTHENTICATED => 'Visible to any logged-in user',
            self::ROLE => 'Visible only to users with specific roles',
            self::PRIVATE => 'Only visible to admins and the author',
        };
    }
    
    /**
     * Check if requires authentication
     */
    public function requiresAuth(): bool {
        return match($this) {
            self::PUBLIC => false,
            default => true,
        };
    }
}
```

---

## 🔖 ChecklistPhase

**File:** `src/Enums/ChecklistPhase.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum ChecklistPhase: string {
    case PRE = 'PRE';
    case IN_EXAM = 'IN_EXAM';
    case POST = 'POST';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::PRE => 'Pre-Exam Checklist',
            self::IN_EXAM => 'In-Exam Checklist',
            self::POST => 'Post-Exam Checklist',
        };
    }
    
    /**
     * Get short label
     */
    public function shortLabel(): string {
        return match($this) {
            self::PRE => 'Pre',
            self::IN_EXAM => 'During',
            self::POST => 'Post',
        };
    }
    
    /**
     * Get description
     */
    public function description(): string {
        return match($this) {
            self::PRE => 'Must complete before starting exam',
            self::IN_EXAM => 'Tasks during exam (can be timed)',
            self::POST => 'Actions after exam completion',
        };
    }
    
    /**
     * Check if phase supports time limits
     */
    public function supportsTimeLimit(): bool {
        return $this === self::IN_EXAM;
    }
    
    /**
     * Get execution order (for sorting)
     */
    public function order(): int {
        return match($this) {
            self::PRE => 1,
            self::IN_EXAM => 2,
            self::POST => 3,
        };
    }
}
```

---

## 🔖 ChecklistItemType

**File:** `src/Enums/ChecklistItemType.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum ChecklistItemType: string {
    case VIDEO = 'VIDEO';
    case LINK = 'LINK';
    case TEXT = 'TEXT';
    case SECTION_CHECKPOINT = 'SECTION_CHECKPOINT';
    case RUBRIC_ITEM = 'RUBRIC_ITEM';
    case CUSTOM = 'CUSTOM';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::VIDEO => 'Video',
            self::LINK => 'Link',
            self::TEXT => 'Text Item',
            self::SECTION_CHECKPOINT => 'Section Checkpoint',
            self::RUBRIC_ITEM => 'Rubric Item',
            self::CUSTOM => 'Custom Item',
        };
    }
    
    /**
     * Get icon
     */
    public function icon(): string {
        return match($this) {
            self::VIDEO => '🎬',
            self::LINK => '🔗',
            self::TEXT => '📝',
            self::SECTION_CHECKPOINT => '✅',
            self::RUBRIC_ITEM => '📋',
            self::CUSTOM => '⚙️',
        };
    }
    
    /**
     * Check if has URL field
     */
    public function hasUrl(): bool {
        return match($this) {
            self::VIDEO, self::LINK => true,
            default => false,
        };
    }
}
```

---

## 🔖 SubmissionType

**File:** `src/Enums/SubmissionType.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

/**
 * Types of user submissions for checklist items
 * Each type has specific validation and UI rendering requirements
 */
enum SubmissionType: string {
    case CHECKBOX = 'CHECKBOX';           // Simple completion toggle
    case TEXT_SHORT = 'TEXT_SHORT';       // Single line text (max 255 chars)
    case TEXT_LONG = 'TEXT_LONG';         // Multi-paragraph (max 10,000 chars)
    case URL = 'URL';                     // Any URL with optional regex
    case VIDEO_LINK = 'VIDEO_LINK';       // YouTube, Vimeo, Loom, etc.
    case FILE_UPLOAD = 'FILE_UPLOAD';     // Document/file attachment
    case SELECT = 'SELECT';               // Dropdown single selection
    case RADIO = 'RADIO';                 // Radio button single selection
    case MULTISELECT = 'MULTISELECT';     // Checkbox multiple selection
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::CHECKBOX => 'Checkbox (Simple)',
            self::TEXT_SHORT => 'Short Text',
            self::TEXT_LONG => 'Long Text',
            self::URL => 'URL Link',
            self::VIDEO_LINK => 'Video Link',
            self::FILE_UPLOAD => 'File Upload',
            self::SELECT => 'Dropdown',
            self::RADIO => 'Radio Buttons',
            self::MULTISELECT => 'Checkboxes (Multiple)',
        };
    }
    
    /**
     * Get icon for UI
     */
    public function icon(): string {
        return match($this) {
            self::CHECKBOX => '☑️',
            self::TEXT_SHORT => '📝',
            self::TEXT_LONG => '📄',
            self::URL => '🔗',
            self::VIDEO_LINK => '🎬',
            self::FILE_UPLOAD => '📎',
            self::SELECT => '📋',
            self::RADIO => '🔘',
            self::MULTISELECT => '☐',
        };
    }
    
    /**
     * Check if type requires validation config
     */
    public function requiresValidationConfig(): bool {
        return match($this) {
            self::CHECKBOX => false,
            default => true,
        };
    }
    
    /**
     * Check if type supports regex validation
     */
    public function supportsRegex(): bool {
        return match($this) {
            self::TEXT_SHORT,
            self::TEXT_LONG,
            self::URL,
            self::VIDEO_LINK => true,
            default => false,
        };
    }
    
    /**
     * Check if type has options array
     */
    public function hasOptions(): bool {
        return match($this) {
            self::SELECT,
            self::RADIO,
            self::MULTISELECT => true,
            default => false,
        };
    }
    
    /**
     * Check if type allows multiple correct answers
     */
    public function allowsMultipleCorrect(): bool {
        return $this === self::MULTISELECT;
    }
    
    /**
     * Get default validation config
     */
    public function defaultValidationConfig(): array {
        return match($this) {
            self::CHECKBOX => [],
            self::TEXT_SHORT => ['maxLength' => 255],
            self::TEXT_LONG => ['minLength' => 50, 'maxLength' => 10000],
            self::URL => ['regexPattern' => '^https?://.*$'],
            self::VIDEO_LINK => [
                'allowedDomains' => ['youtube.com', 'youtu.be', 'vimeo.com', 'loom.com']
            ],
            self::FILE_UPLOAD => [
                'allowedExtensions' => ['pdf', 'doc', 'docx'],
                'maxFileSize' => 5242880, // 5MB
                'maxFiles' => 3
            ],
            self::SELECT, self::RADIO => [
                'options' => [],
                'shuffleOptions' => false
            ],
            self::MULTISELECT => [
                'options' => [],
                'shuffleOptions' => false,
                'minSelections' => 1,
                'maxSelections' => null
            ],
        };
    }
}
```

---

## 🔖 SubmissionValidationMode

**File:** `src/Enums/SubmissionValidationMode.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

/**
 * Defines behavior when submission fails validation
 * Default is FLAG_FOR_REVIEW - participant proceeds but admin reviews
 */
enum SubmissionValidationMode: string {
    case FLAG_FOR_REVIEW = 'FLAG_FOR_REVIEW';   // Accept but flag for admin
    case ALLOW_RESUBMIT = 'ALLOW_RESUBMIT';     // Show error, allow correction
    case AUTO_ACCEPT = 'AUTO_ACCEPT';           // No validation, always accept
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::FLAG_FOR_REVIEW => 'Flag for Review',
            self::ALLOW_RESUBMIT => 'Allow Resubmit',
            self::AUTO_ACCEPT => 'Auto Accept',
        };
    }
    
    /**
     * Get description
     */
    public function description(): string {
        return match($this) {
            self::FLAG_FOR_REVIEW => 'Accept submission but flag for admin review if validation fails',
            self::ALLOW_RESUBMIT => 'Show validation error and allow participant to correct',
            self::AUTO_ACCEPT => 'No validation - accept any input without checking',
        };
    }
    
    /**
     * Check if mode blocks progression
     */
    public function blocksProgression(): bool {
        return $this === self::ALLOW_RESUBMIT;
    }
    
    /**
     * Check if mode requires admin review
     */
    public function requiresReview(): bool {
        return $this === self::FLAG_FOR_REVIEW;
    }
}
```

---

## 🔖 SubmissionReviewStatus

**File:** `src/Enums/SubmissionReviewStatus.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

/**
 * Review status for flagged submissions
 */
enum SubmissionReviewStatus: string {
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case NEEDS_RESUBMIT = 'NEEDS_RESUBMIT';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::PENDING => 'Pending Review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::NEEDS_RESUBMIT => 'Needs Resubmit',
        };
    }
    
    /**
     * Get badge class
     */
    public function badgeClass(): string {
        return match($this) {
            self::PENDING => 'badge-warning',
            self::APPROVED => 'badge-success',
            self::REJECTED => 'badge-danger',
            self::NEEDS_RESUBMIT => 'badge-info',
        };
    }
    
    /**
     * Check if final status
     */
    public function isFinal(): bool {
        return match($this) {
            self::APPROVED,
            self::REJECTED => true,
            default => false,
        };
    }
}
```

---

## 🔖 PresetCategory

**File:** `src/Enums/PresetCategory.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

/**
 * Categories for exam preset templates
 */
enum PresetCategory: string {
    case GENERAL = 'GENERAL';
    case CERTIFICATION = 'CERTIFICATION';
    case TRAINING = 'TRAINING';
    case ASSESSMENT = 'ASSESSMENT';
    case CUSTOM = 'CUSTOM';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::GENERAL => 'General',
            self::CERTIFICATION => 'Certification',
            self::TRAINING => 'Training',
            self::ASSESSMENT => 'Assessment',
            self::CUSTOM => 'Custom',
        };
    }
    
    /**
     * Get description
     */
    public function description(): string {
        return match($this) {
            self::GENERAL => 'Standard exam configurations',
            self::CERTIFICATION => 'Strict settings for certification exams',
            self::TRAINING => 'Flexible settings for self-paced training',
            self::ASSESSMENT => 'Time-limited skill assessments',
            self::CUSTOM => 'User-created custom presets',
        };
    }
    
    /**
     * Get icon
     */
    public function icon(): string {
        return match($this) {
            self::GENERAL => '📚',
            self::CERTIFICATION => '🏆',
            self::TRAINING => '🎓',
            self::ASSESSMENT => '⏱️',
            self::CUSTOM => '⚙️',
        };
    }
    
    /**
     * Check if can be deleted (custom only)
     */
    public function canDelete(): bool {
        return $this === self::CUSTOM;
    }
}
```

---

## 🔖 EvidenceType

**File:** `src/Enums/EvidenceType.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum EvidenceType: string {
    case FILE = 'FILE';
    case IMAGE = 'IMAGE';
    case URL = 'URL';
    case TEXT = 'TEXT';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::FILE => 'File Upload',
            self::IMAGE => 'Image Upload',
            self::URL => 'URL Link',
            self::TEXT => 'Text Response',
        };
    }
    
    /**
     * Get icon
     */
    public function icon(): string {
        return match($this) {
            self::FILE => '📎',
            self::IMAGE => '🖼️',
            self::URL => '🔗',
            self::TEXT => '📝',
        };
    }
    
    /**
     * Get accepted file extensions (for FILE and IMAGE types)
     * Note: Extension requests have stricter limits (PDF/DOC/DOCX only, 5MB)
     * See SHARED-CONSTANTS.md for context-specific limits
     */
    public function acceptedExtensions(): array {
        return match($this) {
            self::FILE => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip'],
            self::IMAGE => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            self::URL => [],
            self::TEXT => [],
        };
    }
    
    /**
     * Get max file size in bytes
     * Note: Extension requests have stricter limits (5MB)
     * See SHARED-CONSTANTS.md for context-specific limits
     */
    public function maxFileSize(): int {
        return match($this) {
            self::FILE => 10 * 1024 * 1024, // 10MB for general evidence
            self::IMAGE => 5 * 1024 * 1024, // 5MB
            self::URL => 0,
            self::TEXT => 0,
        };
    }
    
    /**
     * Get max files allowed per upload
     */
    public function maxFilesPerUpload(): int {
        return match($this) {
            self::FILE => 5,
            self::IMAGE => 10,
            self::URL => 0,
            self::TEXT => 0,
        };
    }
    
    /**
     * Get minimum text length (for TEXT type)
     */
    public function minTextLength(): int {
        return match($this) {
            self::TEXT => 50,
            default => 0,
        };
    }
    
    /**
     * Check if requires file upload
     */
    public function requiresUpload(): bool {
        return match($this) {
            self::FILE, self::IMAGE => true,
            self::URL, self::TEXT => false,
        };
    }
}
```

---

## 🔖 PrerequisiteType

**File:** `src/Enums/PrerequisiteType.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum PrerequisiteType: string {
    // Content-based prerequisites (display items)
    case VIDEO = 'VIDEO';
    case LINK = 'LINK';
    case DOCUMENT = 'DOCUMENT';
    
    // Logic-based prerequisites (access control)
    case EXAM_COMPLETION = 'EXAM_COMPLETION';
    case CHECKLIST_ITEM = 'CHECKLIST_ITEM';
    case ROLE_ASSIGNMENT = 'ROLE_ASSIGNMENT';
    case DATE_RANGE = 'DATE_RANGE';
    case MANUAL_APPROVAL = 'MANUAL_APPROVAL';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::VIDEO => 'Video',
            self::LINK => 'Link',
            self::DOCUMENT => 'Document',
            self::EXAM_COMPLETION => 'Exam Completion',
            self::CHECKLIST_ITEM => 'Checklist Item',
            self::ROLE_ASSIGNMENT => 'Role Assignment',
            self::DATE_RANGE => 'Date Range',
            self::MANUAL_APPROVAL => 'Manual Approval',
        };
    }
    
    /**
     * Get icon
     */
    public function icon(): string {
        return match($this) {
            self::VIDEO => '🎬',
            self::LINK => '🔗',
            self::DOCUMENT => '📄',
            self::EXAM_COMPLETION => '✅',
            self::CHECKLIST_ITEM => '☑️',
            self::ROLE_ASSIGNMENT => '👤',
            self::DATE_RANGE => '📅',
            self::MANUAL_APPROVAL => '🔐',
        };
    }
    
    /**
     * Check if this is a content-based prerequisite (display only)
     */
    public function isContentType(): bool {
        return match($this) {
            self::VIDEO, self::LINK, self::DOCUMENT => true,
            default => false,
        };
    }
    
    /**
     * Check if this is a logic-based prerequisite (access control)
     */
    public function isAccessControlType(): bool {
        return !$this->isContentType();
    }
    
    /**
     * Check if requires URL field
     */
    public function requiresUrl(): bool {
        return match($this) {
            self::VIDEO, self::LINK, self::DOCUMENT => true,
            default => false,
        };
    }
    
    /**
     * Check if requires target entity ID
     */
    public function requiresTargetId(): bool {
        return match($this) {
            self::EXAM_COMPLETION, self::CHECKLIST_ITEM, self::ROLE_ASSIGNMENT => true,
            default => false,
        };
    }
    
    /**
     * Check if requires date configuration
     */
    public function requiresDateRange(): bool {
        return $this === self::DATE_RANGE;
    }
    
    /**
     * Get content-based types only
     */
    public static function contentTypes(): array {
        return [self::VIDEO, self::LINK, self::DOCUMENT];
    }
    
    /**
     * Get access control types only
     */
    public static function accessControlTypes(): array {
        return [
            self::EXAM_COMPLETION,
            self::CHECKLIST_ITEM,
            self::ROLE_ASSIGNMENT,
            self::DATE_RANGE,
            self::MANUAL_APPROVAL,
        ];
    }
}
```

---

## 🔖 ExtensionStatus

**File:** `src/Enums/ExtensionStatus.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum ExtensionStatus: string {
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case EXPIRED = 'EXPIRED';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::PENDING => 'Pending Review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::EXPIRED => 'Expired',
        };
    }
    
    /**
     * Get badge class
     */
    public function badgeClass(): string {
        return match($this) {
            self::PENDING => 'badge-warning',
            self::APPROVED => 'badge-success',
            self::REJECTED => 'badge-danger',
            self::EXPIRED => 'badge-muted',
        };
    }
    
    /**
     * Check if request is terminal (no further action possible)
     */
    public function isTerminal(): bool {
        return match($this) {
            self::PENDING => false,
            self::APPROVED, self::REJECTED, self::EXPIRED => true,
        };
    }
    
    /**
     * Check if request can be reviewed
     */
    public function canBeReviewed(): bool {
        return $this === self::PENDING;
    }
}
```

---

## 🔖 InviteStatus

**File:** `src/Enums/InviteStatus.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

/**
 * Status of an exam invitation (for invite-only exams)
 */
enum InviteStatus: string {
    case PENDING = 'PENDING';
    case ACCEPTED = 'ACCEPTED';
    case EXPIRED = 'EXPIRED';
    case REVOKED = 'REVOKED';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::PENDING => 'Pending',
            self::ACCEPTED => 'Accepted',
            self::EXPIRED => 'Expired',
            self::REVOKED => 'Revoked',
        };
    }
    
    /**
     * Get badge class
     */
    public function badgeClass(): string {
        return match($this) {
            self::PENDING => 'badge-warning',
            self::ACCEPTED => 'badge-success',
            self::EXPIRED => 'badge-muted',
            self::REVOKED => 'badge-danger',
        };
    }
    
    /**
     * Check if invite can be used for signup
     */
    public function canSignup(): bool {
        return $this === self::PENDING;
    }
    
    /**
     * Check if invite is terminal (no further action)
     */
    public function isTerminal(): bool {
        return match($this) {
            self::PENDING => false,
            self::ACCEPTED, self::EXPIRED, self::REVOKED => true,
        };
    }
}
```

---

## 🔖 AccessMethod

**File:** `src/Enums/AccessMethod.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

/**
 * How a participant gained access to an exam
 */
enum AccessMethod: string {
    case SIGNUP = 'SIGNUP';
    case SECRET_KEY = 'SECRET_KEY';
    case ADMIN_ADDED = 'ADMIN_ADDED';
    case MIGRATED_FROM_ANONYMOUS = 'MIGRATED_FROM_ANONYMOUS';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::SIGNUP => 'Self Signup',
            self::SECRET_KEY => 'Secret Key',
            self::ADMIN_ADDED => 'Admin Added',
            self::MIGRATED_FROM_ANONYMOUS => 'Migrated',
        };
    }
    
    /**
     * Get icon
     */
    public function icon(): string {
        return match($this) {
            self::SIGNUP => '📝',
            self::SECRET_KEY => '🔑',
            self::ADMIN_ADDED => '👤',
            self::MIGRATED_FROM_ANONYMOUS => '🔄',
        };
    }
    
    /**
     * Check if method requires password
     */
    public function requiresPassword(): bool {
        return match($this) {
            self::SIGNUP, self::MIGRATED_FROM_ANONYMOUS => true,
            self::SECRET_KEY, self::ADMIN_ADDED => false,
        };
    }
    
    /**
     * Check if method is self-initiated
     */
    public function isSelfInitiated(): bool {
        return match($this) {
            self::SIGNUP, self::SECRET_KEY => true,
            self::ADMIN_ADDED, self::MIGRATED_FROM_ANONYMOUS => false,
        };
    }
}
```

---

## 🔖 EmailTemplateKey

**File:** `src/Enums/EmailTemplateKey.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum EmailTemplateKey: string {
    case SIGNUP_CONFIRMATION = 'SIGNUP_CONFIRMATION';
    case DAILY_DIGEST = 'DAILY_DIGEST';
    case SOFT_DEADLINE_APPROACHING = 'SOFT_DEADLINE_APPROACHING';
    case SOFT_DEADLINE_PASSED = 'SOFT_DEADLINE_PASSED';
    case HARD_DEADLINE_APPROACHING = 'HARD_DEADLINE_APPROACHING';
    case EXAM_LOCKED = 'EXAM_LOCKED';
    case EXTENSION_REQUESTED = 'EXTENSION_REQUESTED';
    case EXTENSION_APPROVED = 'EXTENSION_APPROVED';
    case EXTENSION_REJECTED = 'EXTENSION_REJECTED';
    case EXTENSION_EXPIRED = 'EXTENSION_EXPIRED';
    case EXAM_COMPLETED = 'EXAM_COMPLETED';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::SIGNUP_CONFIRMATION => 'Signup Confirmation',
            self::DAILY_DIGEST => 'Daily Digest',
            self::SOFT_DEADLINE_APPROACHING => 'Soft Deadline Approaching',
            self::SOFT_DEADLINE_PASSED => 'Soft Deadline Passed',
            self::HARD_DEADLINE_APPROACHING => 'Hard Deadline Approaching',
            self::EXAM_LOCKED => 'Exam Locked',
            self::EXTENSION_REQUESTED => 'Extension Requested',
            self::EXTENSION_APPROVED => 'Extension Approved',
            self::EXTENSION_REJECTED => 'Extension Rejected',
            self::EXTENSION_EXPIRED => 'Extension Expired',
            self::EXAM_COMPLETED => 'Exam Completed',
        };
    }
    
    /**
     * Get filename for seeding
     */
    public function filename(): string {
        return strtolower(str_replace('_', '-', $this->value)) . '.html';
    }
    
    /**
     * Get default subject
     */
    public function defaultSubject(): string {
        return match($this) {
            self::SIGNUP_CONFIRMATION => 'Welcome to {{examTitle}}',
            self::DAILY_DIGEST => 'Your Daily Progress - {{examTitle}}',
            self::SOFT_DEADLINE_APPROACHING => 'Soft Deadline Approaching - {{examTitle}}',
            self::SOFT_DEADLINE_PASSED => 'Soft Deadline Passed - {{examTitle}}',
            self::HARD_DEADLINE_APPROACHING => 'Urgent: Hard Deadline in 24 Hours - {{examTitle}}',
            self::EXAM_LOCKED => 'Exam Locked - {{examTitle}}',
            self::EXTENSION_REQUESTED => 'Extension Request Received - {{examTitle}}',
            self::EXTENSION_APPROVED => 'Extension Approved - {{examTitle}}',
            self::EXTENSION_REJECTED => 'Extension Request Update - {{examTitle}}',
            self::EXTENSION_EXPIRED => 'Extension Deadline Passed - {{examTitle}}',
            self::EXAM_COMPLETED => 'Congratulations! Exam Completed - {{examTitle}}',
        };
    }
    
    /**
     * Get all template keys
     */
    public static function all(): array {
        return self::cases();
    }
}
```

---

## 🔖 LogLevel

**File:** `src/Enums/LogLevel.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum LogLevel: string {
    case DEBUG = 'DEBUG';
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case ERROR = 'ERROR';
    case CRITICAL = 'CRITICAL';
    
    /**
     * Get numeric priority (higher = more severe)
     */
    public function priority(): int {
        return match($this) {
            self::DEBUG => 1,
            self::INFO => 2,
            self::WARNING => 3,
            self::ERROR => 4,
            self::CRITICAL => 5,
        };
    }
    
    /**
     * Check if should log to error file
     */
    public function isError(): bool {
        return match($this) {
            self::ERROR, self::CRITICAL => true,
            default => false,
        };
    }
}
```

---

## 🔖 DeadlineType

**File:** `src/Enums/DeadlineType.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum DeadlineType: string {
    case SOFT = 'SOFT';
    case HARD = 'HARD';
    case EXTENSION = 'EXTENSION';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::SOFT => 'Soft Deadline',
            self::HARD => 'Hard Deadline',
            self::EXTENSION => 'Extension Deadline',
        };
    }
    
    /**
     * Check if blocks section marking
     */
    public function isBlocking(): bool {
        return match($this) {
            self::SOFT => false,
            self::HARD, self::EXTENSION => true,
        };
    }
}
```

---

## 🔖 NotificationType

**File:** `src/Enums/NotificationType.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum NotificationType: string {
    // Admin notifications
    case PARTICIPANT_REGISTERED = 'PARTICIPANT_REGISTERED';
    case EXTENSION_REQUEST_SUBMITTED = 'EXTENSION_REQUEST_SUBMITTED';
    case PARTICIPANT_COMPLETED = 'PARTICIPANT_COMPLETED';
    case HARD_DEADLINE_APPROACHING = 'HARD_DEADLINE_APPROACHING';
    case SYSTEM_ALERT = 'SYSTEM_ALERT';
    
    // Participant notifications
    case DEADLINE_REMINDER = 'DEADLINE_REMINDER';
    case EXTENSION_DECISION = 'EXTENSION_DECISION';
    case NEW_CONTENT_ADDED = 'NEW_CONTENT_ADDED';
    case CERTIFICATE_READY = 'CERTIFICATE_READY';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::PARTICIPANT_REGISTERED => 'New Participant Registered',
            self::EXTENSION_REQUEST_SUBMITTED => 'Extension Request Submitted',
            self::PARTICIPANT_COMPLETED => 'Participant Completed Exam',
            self::HARD_DEADLINE_APPROACHING => 'Hard Deadline Approaching',
            self::SYSTEM_ALERT => 'System Alert',
            self::DEADLINE_REMINDER => 'Deadline Reminder',
            self::EXTENSION_DECISION => 'Extension Request Decision',
            self::NEW_CONTENT_ADDED => 'New Content Added',
            self::CERTIFICATE_READY => 'Certificate Ready',
        };
    }
    
    /**
     * Get icon
     */
    public function icon(): string {
        return match($this) {
            self::PARTICIPANT_REGISTERED => '👤',
            self::EXTENSION_REQUEST_SUBMITTED => '📝',
            self::PARTICIPANT_COMPLETED => '🎉',
            self::HARD_DEADLINE_APPROACHING => '⏰',
            self::SYSTEM_ALERT => '⚠️',
            self::DEADLINE_REMINDER => '📅',
            self::EXTENSION_DECISION => '📩',
            self::NEW_CONTENT_ADDED => '📚',
            self::CERTIFICATE_READY => '🏆',
        };
    }
    
    /**
     * Check if this is an admin notification
     */
    public function isAdminNotification(): bool {
        return match($this) {
            self::PARTICIPANT_REGISTERED,
            self::EXTENSION_REQUEST_SUBMITTED,
            self::PARTICIPANT_COMPLETED,
            self::HARD_DEADLINE_APPROACHING,
            self::SYSTEM_ALERT => true,
            
            default => false,
        };
    }
    
    /**
     * Check if this is a participant notification
     */
    public function isParticipantNotification(): bool {
        return match($this) {
            self::DEADLINE_REMINDER,
            self::EXTENSION_DECISION,
            self::NEW_CONTENT_ADDED,
            self::CERTIFICATE_READY => true,
            
            default => false,
        };
    }
    
    /**
     * Get default severity for this notification type
     */
    public function defaultSeverity(): NotificationSeverity {
        return match($this) {
            self::HARD_DEADLINE_APPROACHING,
            self::SYSTEM_ALERT => NotificationSeverity::URGENT,
            
            self::EXTENSION_REQUEST_SUBMITTED,
            self::DEADLINE_REMINDER => NotificationSeverity::WARNING,
            
            default => NotificationSeverity::INFO,
        };
    }
    
    /**
     * Get action URL template (with placeholders)
     */
    public function actionUrlTemplate(): ?string {
        return match($this) {
            self::PARTICIPANT_REGISTERED => '/admin/participants/{{participantId}}',
            self::EXTENSION_REQUEST_SUBMITTED => '/admin/extensions/{{requestId}}',
            self::PARTICIPANT_COMPLETED => '/admin/participants/{{participantId}}',
            self::HARD_DEADLINE_APPROACHING => '/admin/participants?filter=deadline',
            self::CERTIFICATE_READY => '/participant/certificate/{{examSlug}}',
            default => null,
        };
    }
}
```

---

## 🔖 NotificationSeverity

**File:** `src/Enums/NotificationSeverity.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum NotificationSeverity: string {
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case URGENT = 'URGENT';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::INFO => 'Info',
            self::WARNING => 'Warning',
            self::URGENT => 'Urgent',
        };
    }
    
    /**
     * Get badge color class
     */
    public function badgeClass(): string {
        return match($this) {
            self::INFO => 'badge-info',
            self::WARNING => 'badge-warning',
            self::URGENT => 'badge-danger',
        };
    }
    
    /**
     * Get icon
     */
    public function icon(): string {
        return match($this) {
            self::INFO => 'ℹ️',
            self::WARNING => '⚠️',
            self::URGENT => '🚨',
        };
    }
    
    /**
     * Get priority (higher = more important)
     */
    public function priority(): int {
        return match($this) {
            self::INFO => 1,
            self::WARNING => 2,
            self::URGENT => 3,
        };
    }
    
    /**
     * Check if should trigger browser notification
     */
    public function triggersBrowserNotification(): bool {
        return match($this) {
            self::URGENT => true,
            default => false,
        };
    }
    
    /**
     * Check if should play sound
     */
    public function triggersSound(): bool {
        return match($this) {
            self::URGENT, self::WARNING => true,
            self::INFO => false,
        };
    }
    
    /**
     * Check if should show toast
     */
    public function triggersToast(): bool {
        return match($this) {
            self::URGENT => true,
            default => false,
        };
    }
}
```

---

## 🔖 AuditAction

**File:** `src/Enums/AuditAction.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum AuditAction: string {
    // User Actions
    case USER_LOGIN = 'USER_LOGIN';
    case USER_LOGOUT = 'USER_LOGOUT';
    case ROLE_CHANGED = 'ROLE_CHANGED';
    case PERMISSION_MODIFIED = 'PERMISSION_MODIFIED';
    case PASSWORD_RESET = 'PASSWORD_RESET';
    
    // Exam Operations
    case EXAM_CREATED = 'EXAM_CREATED';
    case EXAM_UPDATED = 'EXAM_UPDATED';
    case EXAM_DELETED = 'EXAM_DELETED';
    case EXAM_STATUS_CHANGED = 'EXAM_STATUS_CHANGED';
    case EXAM_CONTENT_MODIFIED = 'EXAM_CONTENT_MODIFIED';
    case EXAM_SETTINGS_CHANGED = 'EXAM_SETTINGS_CHANGED';
    
    // Participant Operations
    case PARTICIPANT_ADDED = 'PARTICIPANT_ADDED';
    case PARTICIPANT_REMOVED = 'PARTICIPANT_REMOVED';
    case PARTICIPANT_STATUS_CHANGED = 'PARTICIPANT_STATUS_CHANGED';
    case EXTENSION_REQUESTED = 'EXTENSION_REQUESTED';
    case EXTENSION_APPROVED = 'EXTENSION_APPROVED';
    case EXTENSION_REJECTED = 'EXTENSION_REJECTED';
    case CHECKLIST_COMPLETED = 'CHECKLIST_COMPLETED';
    
    // Secret Key Operations
    case SECRET_KEY_CREATED = 'SECRET_KEY_CREATED';
    case SECRET_KEY_DEACTIVATED = 'SECRET_KEY_DEACTIVATED';
    case SECRET_KEY_ACCESSED = 'SECRET_KEY_ACCESSED';
    
    // System Events
    case PLUGIN_ACTIVATED = 'PLUGIN_ACTIVATED';
    case PLUGIN_DEACTIVATED = 'PLUGIN_DEACTIVATED';
    case SETTINGS_CHANGED = 'SETTINGS_CHANGED';
    case DATABASE_MIGRATED = 'DATABASE_MIGRATED';
    case CRON_EXECUTED = 'CRON_EXECUTED';
    
    // Audit Events
    case AUDIT_LOG_VIEWED = 'AUDIT_LOG_VIEWED';
    case AUDIT_LOG_EXPORTED = 'AUDIT_LOG_EXPORTED';
    case AUDIT_LOG_ARCHIVED = 'AUDIT_LOG_ARCHIVED';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::USER_LOGIN => 'User Login',
            self::USER_LOGOUT => 'User Logout',
            self::ROLE_CHANGED => 'Role Changed',
            self::PERMISSION_MODIFIED => 'Permission Modified',
            self::PASSWORD_RESET => 'Password Reset',
            self::EXAM_CREATED => 'Exam Created',
            self::EXAM_UPDATED => 'Exam Updated',
            self::EXAM_DELETED => 'Exam Deleted',
            self::EXAM_STATUS_CHANGED => 'Exam Status Changed',
            self::EXAM_CONTENT_MODIFIED => 'Exam Content Modified',
            self::EXAM_SETTINGS_CHANGED => 'Exam Settings Changed',
            self::PARTICIPANT_ADDED => 'Participant Added',
            self::PARTICIPANT_REMOVED => 'Participant Removed',
            self::PARTICIPANT_STATUS_CHANGED => 'Participant Status Changed',
            self::EXTENSION_REQUESTED => 'Extension Requested',
            self::EXTENSION_APPROVED => 'Extension Approved',
            self::EXTENSION_REJECTED => 'Extension Rejected',
            self::CHECKLIST_COMPLETED => 'Checklist Completed',
            self::SECRET_KEY_CREATED => 'Secret Key Created',
            self::SECRET_KEY_DEACTIVATED => 'Secret Key Deactivated',
            self::SECRET_KEY_ACCESSED => 'Secret Key Accessed',
            self::PLUGIN_ACTIVATED => 'Plugin Activated',
            self::PLUGIN_DEACTIVATED => 'Plugin Deactivated',
            self::SETTINGS_CHANGED => 'Settings Changed',
            self::DATABASE_MIGRATED => 'Database Migrated',
            self::CRON_EXECUTED => 'Cron Executed',
            self::AUDIT_LOG_VIEWED => 'Audit Log Viewed',
            self::AUDIT_LOG_EXPORTED => 'Audit Log Exported',
            self::AUDIT_LOG_ARCHIVED => 'Audit Log Archived',
        };
    }
    
    /**
     * Get category for grouping
     */
    public function category(): string {
        return match($this) {
            self::USER_LOGIN,
            self::USER_LOGOUT,
            self::ROLE_CHANGED,
            self::PERMISSION_MODIFIED,
            self::PASSWORD_RESET => 'USER',
            
            self::EXAM_CREATED,
            self::EXAM_UPDATED,
            self::EXAM_DELETED,
            self::EXAM_STATUS_CHANGED,
            self::EXAM_CONTENT_MODIFIED,
            self::EXAM_SETTINGS_CHANGED => 'EXAM',
            
            self::PARTICIPANT_ADDED,
            self::PARTICIPANT_REMOVED,
            self::PARTICIPANT_STATUS_CHANGED,
            self::EXTENSION_REQUESTED,
            self::EXTENSION_APPROVED,
            self::EXTENSION_REJECTED,
            self::CHECKLIST_COMPLETED => 'PARTICIPANT',
            
            self::SECRET_KEY_CREATED,
            self::SECRET_KEY_DEACTIVATED,
            self::SECRET_KEY_ACCESSED => 'SECRET_KEY',
            
            self::PLUGIN_ACTIVATED,
            self::PLUGIN_DEACTIVATED,
            self::SETTINGS_CHANGED,
            self::DATABASE_MIGRATED,
            self::CRON_EXECUTED => 'SYSTEM',
            
            self::AUDIT_LOG_VIEWED,
            self::AUDIT_LOG_EXPORTED,
            self::AUDIT_LOG_ARCHIVED => 'AUDIT',
        };
    }
    
    /**
     * Get icon
     */
    public function icon(): string {
        return match($this->category()) {
            'USER' => '👤',
            'EXAM' => '📝',
            'PARTICIPANT' => '🎓',
            'SECRET_KEY' => '🔑',
            'SYSTEM' => '⚙️',
            'AUDIT' => '📋',
            default => '📌',
        };
    }
    
    /**
     * Check if this is a security-sensitive action (longer retention)
     */
    public function isSecurityEvent(): bool {
        return match($this) {
            self::USER_LOGIN,
            self::USER_LOGOUT,
            self::ROLE_CHANGED,
            self::PERMISSION_MODIFIED,
            self::PASSWORD_RESET,
            self::SECRET_KEY_CREATED,
            self::SECRET_KEY_DEACTIVATED,
            self::SECRET_KEY_ACCESSED,
            self::AUDIT_LOG_VIEWED,
            self::AUDIT_LOG_EXPORTED => true,
            
            default => false,
        };
    }
    
    /**
     * Get retention period in days (security events kept longer)
     */
    public function retentionDays(): int {
        return $this->isSecurityEvent() ? 365 : 90;
    }
    
    /**
     * Check if action should trigger webhook notification
     */
    public function triggersWebhook(): bool {
        return match($this) {
            self::ROLE_CHANGED,
            self::PERMISSION_MODIFIED,
            self::EXAM_DELETED,
            self::PARTICIPANT_REMOVED,
            self::SECRET_KEY_CREATED,
            self::SECRET_KEY_DEACTIVATED,
            self::SETTINGS_CHANGED => true,
            
            default => false,
        };
    }
    
    /**
     * Get all actions for a category
     */
    public static function forCategory(string $category): array {
        return array_filter(
            self::cases(),
            fn(self $action) => $action->category() === $category
        );
    }
}
```

---

## 🔖 EmailStatus

**File:** `src/Enums/EmailStatus.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum EmailStatus: string {
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case SENT = 'SENT';
    case FAILED = 'FAILED';
    case BOUNCED = 'BOUNCED';
    case CANCELLED = 'CANCELLED';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::SENT => 'Sent',
            self::FAILED => 'Failed',
            self::BOUNCED => 'Bounced',
            self::CANCELLED => 'Cancelled',
        };
    }
    
    /**
     * Get badge color class
     */
    public function badgeClass(): string {
        return match($this) {
            self::PENDING => 'badge-secondary',
            self::PROCESSING => 'badge-info',
            self::SENT => 'badge-success',
            self::FAILED => 'badge-danger',
            self::BOUNCED => 'badge-warning',
            self::CANCELLED => 'badge-muted',
        };
    }
    
    /**
     * Get icon
     */
    public function icon(): string {
        return match($this) {
            self::PENDING => '⏳',
            self::PROCESSING => '🔄',
            self::SENT => '✅',
            self::FAILED => '❌',
            self::BOUNCED => '↩️',
            self::CANCELLED => '🚫',
        };
    }
    
    /**
     * Check if email is in a terminal state (no more processing)
     */
    public function isTerminal(): bool {
        return match($this) {
            self::SENT,
            self::FAILED,
            self::BOUNCED,
            self::CANCELLED => true,
            
            self::PENDING,
            self::PROCESSING => false,
        };
    }
    
    /**
     * Check if email can be retried
     */
    public function canRetry(): bool {
        return match($this) {
            self::FAILED,
            self::BOUNCED => true,
            
            default => false,
        };
    }
    
    /**
     * Check if email can be cancelled
     */
    public function canCancel(): bool {
        return match($this) {
            self::PENDING => true,
            
            default => false,
        };
    }
    
    /**
     * Check if this status indicates a delivery problem
     */
    public function isDeliveryFailure(): bool {
        return match($this) {
            self::FAILED,
            self::BOUNCED => true,
            
            default => false,
        };
    }
    
    /**
     * Get allowed transitions from this status
     */
    public function allowedTransitions(): array {
        return match($this) {
            self::PENDING => [self::PROCESSING, self::CANCELLED],
            self::PROCESSING => [self::SENT, self::FAILED, self::PENDING], // PENDING for retry
            self::SENT => [self::BOUNCED], // bounce can come later
            self::FAILED => [self::PENDING], // manual retry requeues
            self::BOUNCED => [], // terminal
            self::CANCELLED => [], // terminal
        };
    }
}
```

---

## 🔖 EmailPriority

**File:** `src/Enums/EmailPriority.php`

```php
<?php
namespace ExamQuestionsManager\Enums;

enum EmailPriority: string {
    case LOW = 'LOW';
    case NORMAL = 'NORMAL';
    case HIGH = 'HIGH';
    case URGENT = 'URGENT';
    
    /**
     * Get display label
     */
    public function label(): string {
        return match($this) {
            self::LOW => 'Low',
            self::NORMAL => 'Normal',
            self::HIGH => 'High',
            self::URGENT => 'Urgent',
        };
    }
    
    /**
     * Get badge color class
     */
    public function badgeClass(): string {
        return match($this) {
            self::LOW => 'badge-muted',
            self::NORMAL => 'badge-secondary',
            self::HIGH => 'badge-warning',
            self::URGENT => 'badge-danger',
        };
    }
    
    /**
     * Get icon
     */
    public function icon(): string {
        return match($this) {
            self::LOW => '🔽',
            self::NORMAL => '➖',
            self::HIGH => '🔼',
            self::URGENT => '🚨',
        };
    }
    
    /**
     * Get numeric weight for sorting (higher = processed first)
     */
    public function weight(): int {
        return match($this) {
            self::LOW => 1,
            self::NORMAL => 5,
            self::HIGH => 10,
            self::URGENT => 100,
        };
    }
    
    /**
     * Get max delay in minutes before escalation
     */
    public function maxDelayMinutes(): int {
        return match($this) {
            self::LOW => 60,      // 1 hour
            self::NORMAL => 30,   // 30 minutes
            self::HIGH => 10,     // 10 minutes
            self::URGENT => 2,    // 2 minutes
        };
    }
    
    /**
     * Check if should bypass rate limiting
     */
    public function bypassRateLimit(): bool {
        return $this === self::URGENT;
    }
    
    /**
     * Check if should trigger immediate processing
     */
    public function isImmediate(): bool {
        return match($this) {
            self::HIGH, self::URGENT => true,
            default => false,
        };
    }
}
```

---

## 📝 Usage Examples

```php
// ✅ Correct - Use enums
use ExamQuestionsManager\Enums\ParticipantStatus;

$participant->status = ParticipantStatus::ACTIVE->value;

if ($participant->status === ParticipantStatus::LOCKED->value) {
    // Handle locked state
}

$status = ParticipantStatus::from($participant->status);
if ($status->canMarkSections()) {
    // Allow marking
}

// ❌ Wrong - Magic strings
$participant->status = 'ACTIVE';  // Don't do this!
if ($participant->status === 'LOCKED') { ... }  // Don't do this!
```

---

## ✅ Acceptance Criteria

### Enum Files
- [ ] All 16 enum files created
- [ ] Enums use PHP 8.0+ backed enum syntax
- [ ] All enums have string backing values

### Enum Methods
- [ ] All enums have `label()` method for display
- [ ] Relevant enums have helper methods (canMarkSections, etc.)
- [ ] EmailTemplateKey has `filename()` and `defaultSubject()` methods

### Code Standards
- [ ] No magic strings in codebase - all use enum values
- [ ] Enums are properly namespaced
- [ ] Enums are type-hinted in method signatures

### Testing
- [ ] Enum values can be stored in database
- [ ] Enum values can be retrieved and parsed with `::from()`
- [ ] `::tryFrom()` returns null for invalid values

---

## 📝 Notes

- Enums store their string value in the database
- Use `->value` to get the string for database storage
- Use `::from($value)` to convert string back to enum
- Use `::tryFrom($value)` if value might be invalid (returns null)

---

## Related Specifications

| Enum | Primary Usage Specs |
|------|---------------------|
| **ParticipantStatus** | [27-participant-service](27-participant-service.md), [29-deadline-engine](29-deadline-engine.md), [diagrams/02-participant-status-states](../diagrams/02-participant-status-states.md) |
| **UserRoleType** | [10-rbac-system](10-rbac-system.md), [11-rbac-admin-ui](11-rbac-admin-ui.md) |
| **WikiVisibility** | [20-wiki-service](20-wiki-service.md), [21-wiki-categories](21-wiki-categories.md) |
| **ChecklistPhase** | [19-exam-checklists-tab](19-exam-checklists-tab.md), [28-participant-progress](28-participant-progress.md) |
| **ChecklistItemType** | [19-exam-checklists-tab](19-exam-checklists-tab.md), [12-exam-service](12-exam-service.md) |
| **EvidenceType** | [19-exam-checklists-tab](19-exam-checklists-tab.md), [30-extension-system](30-extension-system.md) |
| **PrerequisiteType** | [18-exam-prerequisites-tab](18-exam-prerequisites-tab.md) |
| **ExtensionStatus** | [30-extension-system](30-extension-system.md), [29-deadline-engine](29-deadline-engine.md) |
| **EmailTemplateKey** | [33-email-templates](33-email-templates.md), [31-email-queue](31-email-queue.md) |
| **EmailStatus** | [31-email-queue](31-email-queue.md), [34-cron-system](34-cron-system.md) |
| **EmailPriority** | [31-email-queue](31-email-queue.md) |
| **LogLevel** | [07-logging-system](07-logging-system.md), [02-error-management](02-error-management.md) |
| **DeadlineType** | [29-deadline-engine](29-deadline-engine.md) |
| **NotificationType** | [32-notification-service](32-notification-service.md), [45-notifications-panel](45-notifications-panel.md) |
| **NotificationSeverity** | [32-notification-service](32-notification-service.md) |
| **AuditAction** | [46-audit-logging](46-audit-logging.md) |

### Cross-References
- **Database Schema**: [04-database-schema](04-database-schema.md) - Enum values stored as VARCHAR
- **Shared Constants**: [66-shared-constants](../../66-shared-constants.md) - Frontend enum mirrors
- **Regex Patterns**: Defined in `Consts.php` section, used by [12-exam-service](12-exam-service.md) §10.6

---

*Next: `07-logging-system.md`*
