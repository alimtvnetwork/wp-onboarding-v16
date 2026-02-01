# 06 - Entity Models

> **Phase:** Foundation  
> **Dependencies:** `03-orm-base-classes.md`, `04-enums-constants.md`  
> **Estimated Time:** 4-6 hours

---

## 📋 Scope

Create all ORM entity model classes that extend the base Model class.

---

## 📁 File Structure

```
/src/ORM/Models/
├── UserRole.php
├── Exam.php
├── SecretKey.php
├── SecretKeyAccess.php
├── Wiki.php
├── WikiRevision.php
├── EmailTemplate.php
├── ExamPrerequisite.php
├── ExamChecklist.php
├── ExamRubric.php
├── Participant.php
├── Progress.php
├── ExtensionRequest.php
└── ParticipantChecklist.php
```

---

## 🔖 UserRole Model

**File:** `src/ORM/Models/UserRole.php`

```php
<?php
namespace ExamQuestionsManager\ORM\Models;

use ExamQuestionsManager\ORM\Model;
use ExamQuestionsManager\Enums\UserRoleType;

class UserRole extends Model {
    protected static string $table = 'userRole';
    
    protected static array $fillable = [
        'userId',
        'role',
        'assignedAt',
        'assignedBy',
    ];
    
    protected static array $casts = [
        'id' => 'int',
        'userId' => 'int',
        'assignedBy' => 'int',
    ];
    
    /**
     * Get role as enum
     */
    public function getRoleType(): UserRoleType {
        return UserRoleType::from($this->role);
    }
    
    /**
     * Find by WordPress user ID
     */
    public static function findByUserId(int $userId): ?self {
        return self::where('userId', $userId)->first();
    }
    
    /**
     * Get users by role
     */
    public static function findByRole(UserRoleType $role): array {
        return self::where('role', $role->value)->get();
    }
    
    /**
     * Check if user has role
     */
    public static function hasRole(int $userId, UserRoleType $role): bool {
        return self::where('userId', $userId)
            ->where('role', $role->value)
            ->exists();
    }
}
```

---

## 🔖 Exam Model

**File:** `src/ORM/Models/Exam.php`

```php
<?php
namespace ExamQuestionsManager\ORM\Models;

use ExamQuestionsManager\ORM\Model;

class Exam extends Model {
    protected static string $table = 'exam';
    
    protected static array $fillable = [
        'parentExamId',
        'title',
        'description',
        'slug',
        'markdownFilePath',
        'softDeadlineDays',
        'hardDeadlineDays',
        'isSecretKeyEnabled',
        'isEnabled',
        'createdAt',
        'updatedAt',
        'createdBy',
    ];
    
    protected static array $casts = [
        'id' => 'int',
        'parentExamId' => 'int',
        'softDeadlineDays' => 'int',
        'hardDeadlineDays' => 'int',
        'isSecretKeyEnabled' => 'bool',
        'isEnabled' => 'bool',
        'createdBy' => 'int',
    ];
    
    /**
     * Find by slug
     */
    public static function findBySlug(string $slug): ?self {
        return self::where('slug', $slug)->first();
    }
    
    /**
     * Get parent exams only (no children)
     */
    public static function parents(): array {
        return self::whereNull('parentExamId')
            ->orderBy('createdAt', 'DESC')
            ->get();
    }
    
    /**
     * Get enabled parent exams
     */
    public static function enabledParents(): array {
        return self::whereNull('parentExamId')
            ->where('isEnabled', true)
            ->orderBy('createdAt', 'DESC')
            ->get();
    }
    
    /**
     * Get child exams
     */
    public function children(): array {
        return self::where('parentExamId', $this->id)
            ->orderBy('createdAt', 'ASC')
            ->get();
    }
    
    /**
     * Get parent exam
     */
    public function parent(): ?self {
        if (!$this->parentExamId) {
            return null;
        }
        return self::find($this->parentExamId);
    }
    
    /**
     * Check if has children
     */
    public function hasChildren(): bool {
        return self::where('parentExamId', $this->id)->exists();
    }
    
    /**
     * Get participant count
     */
    public function participantCount(): int {
        return Participant::where('examId', $this->id)->count();
    }
    
    /**
     * Get pending extension count
     */
    public function pendingExtensionCount(): int {
        return ExtensionRequest::query()
            ->whereNull('isAdminApproved')
            ->get();
        // Note: This needs to join with participant - simplified here
    }
    
    /**
     * Get prerequisites
     */
    public function prerequisites(): array {
        return ExamPrerequisite::where('examId', $this->id)
            ->orderBy('displayOrder', 'ASC')
            ->get();
    }
    
    /**
     * Get checklists by type
     */
    public function checklists(string $type = null): array {
        $query = ExamChecklist::where('examId', $this->id);
        
        if ($type) {
            $query->where('checklistType', $type);
        }
        
        return $query->orderBy('displayOrder', 'ASC')->get();
    }
    
    /**
     * Get rubrics
     */
    public function rubrics(): array {
        return ExamRubric::where('examId', $this->id)
            ->orderBy('displayOrder', 'ASC')
            ->get();
    }
    
    /**
     * Get secret keys
     */
    public function secretKeys(): array {
        return SecretKey::where('examId', $this->id)->get();
    }
    
    /**
     * Read markdown content
     */
    public function readMarkdown(): ?string {
        $filePath = EQM_UPLOADS_DIR . $this->markdownFilePath;
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        return file_get_contents($filePath);
    }
    
    /**
     * Write markdown content
     */
    public function writeMarkdown(string $content): bool {
        $filePath = EQM_UPLOADS_DIR . $this->markdownFilePath;
        $dir = dirname($filePath);
        
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        
        return file_put_contents($filePath, $content) !== false;
    }
}
```

---

## 🔖 Participant Model

**File:** `src/ORM/Models/Participant.php`

```php
<?php
namespace ExamQuestionsManager\ORM\Models;

use ExamQuestionsManager\ORM\Model;
use ExamQuestionsManager\Enums\ParticipantStatus;

class Participant extends Model {
    protected static string $table = 'participant';
    
    protected static array $fillable = [
        'examId',
        'email',
        'passwordHash',
        'whatsapp',
        'linkedinUrl',
        'displayName',
        'status',
        'signupDate',
        'softDeadlineDate',
        'hardDeadlineDate',
        'extensionDeadlineDate',
        'lastActivityAt',
    ];
    
    protected static array $casts = [
        'id' => 'int',
        'examId' => 'int',
    ];
    
    /**
     * Get status as enum
     */
    public function getStatus(): ParticipantStatus {
        return ParticipantStatus::from($this->status);
    }
    
    /**
     * Set status from enum
     */
    public function setStatus(ParticipantStatus $status): void {
        $this->status = $status->value;
    }
    
    /**
     * Find by email and exam
     */
    public static function findByEmailAndExam(string $email, int $examId): ?self {
        return self::where('email', $email)
            ->where('examId', $examId)
            ->first();
    }
    
    /**
     * Check if can mark sections
     */
    public function canMarkSections(): bool {
        $status = $this->getStatus();
        
        if (!$status->canMarkSections()) {
            return false;
        }
        
        // Check deadline
        $now = new \DateTime();
        $applicableDeadline = $this->getApplicableDeadline();
        
        return $now < $applicableDeadline;
    }
    
    /**
     * Get applicable deadline (extension or hard)
     */
    public function getApplicableDeadline(): \DateTime {
        if ($this->extensionDeadlineDate) {
            return new \DateTime($this->extensionDeadlineDate);
        }
        return new \DateTime($this->hardDeadlineDate);
    }
    
    /**
     * Get exam
     */
    public function exam(): ?Exam {
        return Exam::find($this->examId);
    }
    
    /**
     * Get progress records
     */
    public function progress(): array {
        return Progress::where('participantId', $this->id)
            ->orderBy('sectionNumber', 'ASC')
            ->get();
    }
    
    /**
     * Get completion percentage
     */
    public function completionPercent(): float {
        $progress = $this->progress();
        
        if (empty($progress)) {
            return 0.0;
        }
        
        $completed = count(array_filter($progress, fn($p) => $p->isMarkedDone));
        return round(($completed / count($progress)) * 100, 1);
    }
    
    /**
     * Get extension requests
     */
    public function extensionRequests(): array {
        return ExtensionRequest::where('participantId', $this->id)
            ->orderBy('requestedAt', 'DESC')
            ->get();
    }
    
    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool {
        return password_verify($password, $this->passwordHash);
    }
}
```

---

## 🔖 Progress Model

**File:** `src/ORM/Models/Progress.php`

```php
<?php
namespace ExamQuestionsManager\ORM\Models;

use ExamQuestionsManager\ORM\Model;

class Progress extends Model {
    protected static string $table = 'progress';
    
    protected static array $fillable = [
        'participantId',
        'sectionNumber',
        'sectionTitle',
        'isMarkedDone',
        'completedAt',
    ];
    
    protected static array $casts = [
        'id' => 'int',
        'participantId' => 'int',
        'sectionNumber' => 'int',
        'isMarkedDone' => 'bool',
    ];
    
    /**
     * Mark as done
     */
    public function markDone(): bool {
        $this->isMarkedDone = true;
        $this->completedAt = date('Y-m-d H:i:s');
        return $this->save();
    }
    
    /**
     * Get participant
     */
    public function participant(): ?Participant {
        return Participant::find($this->participantId);
    }
}
```

---

## 🔖 Other Models (Summary)

Create similar model files for:

### SecretKey.php
```php
protected static string $table = 'secretKey';
protected static array $fillable = ['examId', 'keyValue', 'label', 'description', 'expiresAt', 'usageLimit', 'isEnabled', 'viewCount', 'uniqueVisitorCount', 'createdAt', 'createdBy'];
protected static array $casts = ['id' => 'int', 'examId' => 'int', 'usageLimit' => 'int', 'isEnabled' => 'bool', 'viewCount' => 'int', 'uniqueVisitorCount' => 'int', 'createdBy' => 'int'];
// Methods: findByKeyValue(), isValid(), incrementViewCount(), exam(), accessLogs()
```

### SecretKeyAccess.php
```php
protected static string $table = 'secretKeyAccess';
protected static array $fillable = ['secretKeyId', 'ipAddress', 'ipAddressHash', 'userAgent', 'referrer', 'cookieId', 'countryCode', 'city', 'sessionDuration', 'accessedAt'];
protected static array $casts = ['id' => 'int', 'secretKeyId' => 'int', 'sessionDuration' => 'int'];
// Methods: secretKey()
```

### Wiki.php
```php
protected static string $table = 'wiki';
protected static array $fillable = ['title', 'slug', 'category', 'content', 'visibility', 'visibilityRoles', 'authorId', 'createdAt', 'updatedAt'];
protected static array $casts = ['id' => 'int', 'authorId' => 'int', 'visibilityRoles' => 'array'];
// Methods: findBySlug(), revisions(), latestRevision(), createRevision()
```

### WikiRevision.php
```php
protected static string $table = 'wikiRevision';
protected static array $fillable = ['wikiId', 'content', 'revisionNumber', 'changedBy', 'changeNote', 'createdAt'];
protected static array $casts = ['id' => 'int', 'wikiId' => 'int', 'revisionNumber' => 'int', 'changedBy' => 'int'];
// Methods: wiki()
```

### EmailTemplate.php
```php
protected static string $table = 'emailTemplate';
protected static array $fillable = ['templateKey', 'subject', 'body', 'createdAt', 'updatedAt'];
protected static array $casts = ['id' => 'int'];
// Methods: findByKey(), getTemplateKey()
```

### ExamPrerequisite.php
```php
protected static string $table = 'examPrerequisite';
protected static array $fillable = ['examId', 'type', 'title', 'url', 'description', 'displayOrder', 'isRequired', 'createdAt', 'updatedAt'];
protected static array $casts = ['id' => 'int', 'examId' => 'int', 'displayOrder' => 'int', 'isRequired' => 'bool'];
// Methods: exam(), getType()
```

### ExamChecklist.php
```php
protected static string $table = 'examChecklist';
protected static array $fillable = ['examId', 'checklistType', 'itemType', 'title', 'description', 'isRequired', 'displayOrder', 'createdAt', 'updatedAt'];
protected static array $casts = ['id' => 'int', 'examId' => 'int', 'displayOrder' => 'int', 'isRequired' => 'bool'];
// Methods: exam(), getChecklistType(), getItemType()
```

### ExamRubric.php
```php
protected static string $table = 'examRubric';
protected static array $fillable = ['examId', 'criterionTitle', 'description', 'isRequired', 'displayOrder', 'createdAt', 'updatedAt'];
protected static array $casts = ['id' => 'int', 'examId' => 'int', 'displayOrder' => 'int', 'isRequired' => 'bool'];
// Methods: exam()
```

### ExtensionRequest.php
```php
protected static string $table = 'extensionRequest';
protected static array $fillable = ['participantId', 'reason', 'requestedDays', 'attachedFilePath', 'isAdminApproved', 'adminApprovedDays', 'adminNote', 'requestedAt', 'processedAt', 'processedBy'];
protected static array $casts = ['id' => 'int', 'participantId' => 'int', 'requestedDays' => 'int', 'isAdminApproved' => 'bool', 'adminApprovedDays' => 'int', 'processedBy' => 'int'];
// Methods: participant(), isPending(), approve(), reject()
```

### ParticipantChecklist.php
```php
protected static string $table = 'participantChecklist';
protected static array $fillable = ['participantId', 'checklistId', 'isCompleted', 'completedAt'];
protected static array $casts = ['id' => 'int', 'participantId' => 'int', 'checklistId' => 'int', 'isCompleted' => 'bool'];
// Methods: participant(), checklist(), markComplete()
```

---

## ✅ Acceptance Criteria

### All Models
- [ ] All 14 model files created
- [ ] All extend base Model class
- [ ] Proper `$table`, `$fillable`, `$casts` defined
- [ ] Foreign key relationships have helper methods

### Exam Model
- [ ] `findBySlug()` works
- [ ] `parents()` returns only root exams
- [ ] `children()` returns child exams
- [ ] `hasChildren()` works
- [ ] `prerequisites()`, `checklists()`, `rubrics()` work

### Participant Model
- [ ] `findByEmailAndExam()` works
- [ ] `getStatus()` returns enum
- [ ] `canMarkSections()` checks status and deadline
- [ ] `completionPercent()` calculates correctly
- [ ] `verifyPassword()` works with bcrypt

### Data Types
- [ ] Integer fields properly cast
- [ ] Boolean fields properly cast (true/false)
- [ ] JSON array fields decode correctly (visibilityRoles)

---

*Next: `07-validation-utilities.md`*
