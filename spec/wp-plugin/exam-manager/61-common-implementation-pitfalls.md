# Common Implementation Pitfalls

> **Version:** 1.0.0  
> **Last Updated:** 2026-01-26  
> **Status:** Active  
> **Purpose:** Comprehensive guide to implementation mistakes, edge cases, and correct patterns for AI implementers

---

## 1. Deadline Calculation Pitfalls

### 1.1 Extension from Wrong Base Date

**❌ WRONG:**
```php
// Extending from current hard deadline
$extensionDeadline = $participant->hardDeadlineDate + ($approvedDays * 86400);
```

**✅ CORRECT:**
```php
// First extension: use ORIGINAL hard deadline
$baseDeadline = $participant->originalHardDeadline ?? $participant->hardDeadlineDate;
$extensionDeadline = $baseDeadline + ($approvedDays * 86400);

// Subsequent extensions: use CURRENT extension deadline
if ($participant->extensionDeadlineDate !== null) {
    $baseDeadline = $participant->extensionDeadlineDate;
    $extensionDeadline = $baseDeadline + ($additionalDays * 86400);
}
```

**Why It Matters**: Extending from current hard deadline instead of original causes deadline drift when multiple extensions are granted.

---

### 1.2 Soft vs Hard Deadline Confusion

**❌ WRONG:**
```php
// Extending from soft deadline
$extensionDeadline = $participant->softDeadlineDate + ($approvedDays * 86400);
```

**✅ CORRECT:**
```php
// Extensions ALWAYS extend from hard deadline
$extensionDeadline = $participant->originalHardDeadline + ($approvedDays * 86400);
```

**Why It Matters**: Soft deadlines are warnings only; extensions conceptually extend the access cutoff (hard deadline).

---

### 1.3 Not Preserving Original Deadlines

**❌ WRONG:**
```php
// No backup before override
$participant->hardDeadlineDate = $newDeadline;
```

**✅ CORRECT:**
```php
// Preserve original BEFORE any modification
if ($participant->originalHardDeadline === null) {
    $participant->originalHardDeadline = $participant->hardDeadlineDate;
}
$participant->deadlineOverride = $newDeadline;
```

**Why It Matters**: Without originals, you cannot calculate extensions correctly or audit what changed.

---

### 1.4 Soft Deadline After Hard Deadline

**❌ WRONG:**
```php
// No validation
$participant->softDeadlineDate = $softDate;
$participant->hardDeadlineDate = $hardDate;
```

**✅ CORRECT:**
```php
if ($softDate !== null && $hardDate !== null && $softDate >= $hardDate) {
    throw new ValidationException("Soft deadline must be before hard deadline");
}
$participant->softDeadlineDate = $softDate;
$participant->hardDeadlineDate = $hardDate;
```

**Why It Matters**: A soft deadline after hard deadline creates undefined behavior - which one locks first?

---

### 1.5 Timezone Handling

**❌ WRONG:**
```php
// Using server timezone
$deadline = new DateTime($dateString);
```

**✅ CORRECT:**
```php
// Always store and compare in UTC
$deadline = new DateTime($dateString, new DateTimeZone('UTC'));

// Convert for display only
$userTimezone = new DateTimeZone($user->timezone ?? 'UTC');
$displayDeadline = clone $deadline;
$displayDeadline->setTimezone($userTimezone);
```

**Why It Matters**: Server timezone changes or multi-server deployments cause deadline drift.

---

## 2. Progress Calculation Pitfalls

### 2.1 Using round() Instead of floor()

**❌ WRONG:**
```php
$progress = round($completedItems / $totalItems * 100);
// 99.5% → 100% ← SHOWS COMPLETE WHEN NOT!
```

**✅ CORRECT:**
```php
$progress = floor($completedItems / $totalItems * 100);
// 99.5% → 99% ← Never shows 100% unless truly complete
```

**Why It Matters**: Users see 100% and think they're done, but one item remains.

---

### 2.2 Not Excluding SKIPPED Items

**❌ WRONG:**
```php
$totalItems = count($checklistItems);
$completedItems = count($completedChecklistItems);
// SKIPPED items inflate denominator
```

**✅ CORRECT:**
```php
$requiredItems = array_filter($checklistItems, fn($item) => 
    $item->status !== ChecklistStatus::SKIPPED
);
$completedItems = array_filter($requiredItems, fn($item) => 
    $item->status === ChecklistStatus::COMPLETED
);
$progress = floor(count($completedItems) / count($requiredItems) * 100);
```

**Why It Matters**: SKIPPED items are intentionally excluded; counting them distorts progress.

---

### 2.3 Phase Weights Not Summing to 1.0

**❌ WRONG:**
```php
$weights = ['PRE' => 0.20, 'IN_EXAM' => 0.70, 'POST' => 0.20];
// Sum = 1.10 → Progress can exceed 100%!
```

**✅ CORRECT:**
```php
$weights = ['PRE' => 0.20, 'IN_EXAM' => 0.60, 'POST' => 0.20];
// Sum = 1.00

// Or normalize dynamically
$totalWeight = array_sum($weights);
$normalizedWeights = array_map(fn($w) => $w / $totalWeight, $weights);
```

**Why It Matters**: Weights > 1.0 produce impossible percentages; < 1.0 prevents reaching 100%.

---

### 2.4 Division by Zero

**❌ WRONG:**
```php
$progress = floor($completedItems / $totalItems * 100);
// Crashes when $totalItems = 0
```

**✅ CORRECT:**
```php
if ($totalItems === 0) {
    $progress = 0; // Or 100 if no items means "complete by default"
} else {
    $progress = floor($completedItems / $totalItems * 100);
}
```

**Why It Matters**: Exams with no checklist items crash progress calculation.

---

## 3. Anonymous Migration Pitfalls

### 3.1 Not Checking for Existing Participant

**❌ WRONG:**
```php
// Directly assign user ID to anonymous record
$anonParticipant->userId = $userId;
$anonParticipant->save();
```

**✅ CORRECT:**
```php
// Check if user already has a record for this exam
$existingParticipant = $db->findOne('participants', [
    'userId' => $userId,
    'examId' => $anonParticipant->examId
]);

if ($existingParticipant !== null) {
    // Merge required - show merge strategy dialog
    return ['type' => 'MERGE_REQUIRED', 'existing' => $existingParticipant];
}

// Safe to claim
$anonParticipant->userId = $userId;
$anonParticipant->save();
```

**Why It Matters**: Creates duplicate participant records, data inconsistency, and unique constraint violations.

---

### 3.2 Forgetting to Delete Anonymous Cookie

**❌ WRONG:**
```php
$anonParticipant->userId = $userId;
$anonParticipant->save();
// Cookie still exists → next visit tries to migrate again
```

**✅ CORRECT:**
```php
$anonParticipant->userId = $userId;
$anonParticipant->save();

// Delete anonymous cookie
setcookie('eqm_anon_' . $examSlug, '', time() - 3600, '/');

// Set authenticated session cookie
setSessionCookie($userId, $examSlug);
```

**Why It Matters**: Stale cookie triggers migration logic repeatedly, causing errors.

---

### 3.3 Losing Extension Requests During Migration

**❌ WRONG:**
```php
// Only migrating participant record
$anonParticipant->userId = $userId;
$anonParticipant->save();
// Extension requests still reference anonymous ID
```

**✅ CORRECT:**
```php
$db->beginTransaction();

// Migrate participant
$anonParticipant->userId = $userId;
$anonParticipant->save();

// Migrate related records
$db->update('extension_requests', 
    ['participantId' => $anonParticipant->id],
    ['participantId' => $anonParticipant->id] // Already correct, but verify
);

// Migrate checklist completions (if stored separately)
// Migrate submissions
// etc.

$db->commit();
```

**Why It Matters**: Orphaned extension requests or submissions cause data loss.

---

### 3.4 Not Preserving Migration Audit Trail

**❌ WRONG:**
```php
$anonParticipant->userId = $userId;
$anonParticipant->trackingId = null; // Deleted!
$anonParticipant->save();
```

**✅ CORRECT:**
```php
$anonParticipant->userId = $userId;
$anonParticipant->migratedAt = new DateTime('now', new DateTimeZone('UTC'));
$anonParticipant->migratedFromTrackingId = $anonParticipant->trackingId;
// Keep trackingId for audit, or move to metadata
$anonParticipant->save();

$auditLog->record(AuditAction::ANONYMOUS_MIGRATED, $anonParticipant->id, [
    'trackingId' => $anonParticipant->migratedFromTrackingId,
    'userId' => $userId
]);
```

**Why It Matters**: Without audit trail, you cannot investigate migration issues or verify data integrity.

---

## 4. H2 Section Extraction Pitfalls

### 4.1 Matching H2 Inside Code Blocks

**❌ WRONG:**
```php
preg_match_all('/^## (.+)$/m', $markdown, $matches);
// Matches ```## This is not a header``` inside code blocks
```

**✅ CORRECT:**
```php
// Step 1: Remove code blocks first
$noCodeBlocks = preg_replace('/```[\\s\\S]*?```/m', '', $markdown);
$noInlineCode = preg_replace('/`[^`]+`/', '', $noCodeBlocks);

// Step 2: Match H2 headers
preg_match_all('/^## (.+)$/m', $noInlineCode, $matches);
```

**Why It Matters**: False positives create phantom checklist items.

---

### 4.2 Using 0-Indexed Section Numbers

**❌ WRONG:**
```php
foreach ($matches[1] as $index => $title) {
    $sections[] = ['sectionNumber' => $index, 'title' => $title];
    // sectionNumber: 0, 1, 2, 3...
}
```

**✅ CORRECT:**
```php
foreach ($matches[1] as $index => $title) {
    $sections[] = ['sectionNumber' => $index + 1, 'title' => $title];
    // sectionNumber: 1, 2, 3, 4...
}
```

**Why It Matters**: Frontend expects 1-indexed; API endpoint is `/sections/{sectionNumber}/complete`.

---

### 4.3 Not Normalizing Titles

**❌ WRONG:**
```php
$title = $matches[1][0];
// Title might be: "## **Important** Section [[wiki-link]]"
```

**✅ CORRECT:**
```php
$title = $matches[1][0];

// Remove bold/italic markers
$title = preg_replace('/\\*\\*([^*]+)\\*\\*/', '$1', $title);
$title = preg_replace('/\\*([^*]+)\\*/', '$1', $title);

// Remove wiki links, keep display text
$title = preg_replace('/\\[\\[([^\\]|]+)\\|([^\\]]+)\\]\\]/', '$2', $title);
$title = preg_replace('/\\[\\[([^\\]]+)\\]\\]/', '$1', $title);

// Trim
$title = trim($title);
```

**Why It Matters**: Raw titles with markdown artifacts look broken in UI.

---

### 4.4 Forgetting to Update exam.sectionCount

**❌ WRONG:**
```php
$sections = extractH2Sections($markdown);
$exam->content = $markdown;
$exam->save();
// sectionCount not updated!
```

**✅ CORRECT:**
```php
$sections = extractH2Sections($markdown);
$exam->content = $markdown;
$exam->sectionCount = count($sections);
$exam->save();

// Sync checklist items
$checklistService->syncSectionsToChecklist($exam->id, $sections);
```

**Why It Matters**: sectionCount drives progress bar and API validation.

---

## 5. Cookie Naming Pitfalls

### 5.1 Missing Exam Slug Scope

**❌ WRONG:**
```php
setcookie('eqm_session', $value, ...);
// Shared across ALL exams!
```

**✅ CORRECT:**
```php
setcookie('eqm_session_' . $examSlug, $value, ...);
// Isolated per exam
```

**Why It Matters**: Cross-exam session leakage, wrong progress shown, security issues.

---

### 5.2 Inconsistent Naming Pattern

**❌ WRONG:**
```php
// Mixed patterns
setcookie('eqm-session', ...);
setcookie('eqmAnon', ...);
setcookie('exam_tracking', ...);
```

**✅ CORRECT:**
```php
// Consistent pattern: eqm_{purpose}_{examSlug}
setcookie('eqm_session_' . $examSlug, ...);
setcookie('eqm_anon_' . $examSlug, ...);
setcookie('eqm_track_' . $examSlug, ...);
```

**Why It Matters**: Inconsistent names break detection logic and cleanup routines.

---

### 5.3 Not Slugifying Exam Slug

**❌ WRONG:**
```php
$cookieName = 'eqm_session_' . $exam->title;
// "eqm_session_Advanced Exam 2026!" ← Invalid cookie name
```

**✅ CORRECT:**
```php
$cookieName = 'eqm_session_' . $exam->slug;
// "eqm_session_advanced-exam-2026" ← Valid
```

**Why It Matters**: Spaces and special characters in cookie names cause parsing failures.

---

## 6. Rate Limiting Pitfalls

### 6.1 Fixed Window Instead of Sliding Window

**❌ WRONG (Fixed Window):**
```php
// Resets at exactly :00 each minute
$windowStart = floor(time() / 60) * 60;
$key = "rate:{$action}:{$ip}:{$windowStart}";
```

**✅ CORRECT (Sliding Window):**
```php
// Counts requests in rolling window
$now = time();
$windowSeconds = 60;

// Clean old entries
$redis->zRemRangeByScore($key, 0, $now - $windowSeconds);

// Count current window
$count = $redis->zCard($key);

if ($count >= $limit) {
    return ['blocked' => true, 'retryAfter' => $windowSeconds - ($now - $redis->zRange($key, 0, 0)[0])];
}

// Add current request
$redis->zAdd($key, $now, uniqid());
$redis->expire($key, $windowSeconds);
```

**Why It Matters**: Fixed window allows burst at window boundary (2x limit possible).

---

### 6.2 Missing Retry-After Header

**❌ WRONG:**
```php
return new Response(429, [], 'Too Many Requests');
```

**✅ CORRECT:**
```php
return new Response(429, [
    'Retry-After' => $secondsUntilReset,
    'X-RateLimit-Limit' => $limit,
    'X-RateLimit-Remaining' => 0,
    'X-RateLimit-Reset' => time() + $secondsUntilReset
], 'Too Many Requests');
```

**Why It Matters**: Clients need Retry-After to implement proper backoff.

---

### 6.3 Rate Limiting Session Instead of IP for Anonymous

**❌ WRONG:**
```php
// Anonymous users have no session
$key = "rate:{$action}:{$sessionId}";
// $sessionId is null → all anonymous share one limit!
```

**✅ CORRECT:**
```php
$identifier = $userId ?? hash('sha256', $ipAddress . $salt);
$key = "rate:{$action}:{$identifier}";
```

**Why It Matters**: Anonymous users bypass rate limits entirely, or share a single limit.

---

## 7. Secret Key Validation Pitfalls

### 7.1 Storing Plain Key Instead of Hash

**❌ WRONG:**
```php
$secretKey->key = $rawKey;
$secretKey->save();
```

**✅ CORRECT:**
```php
$secretKey->keyHash = hash('sha256', $rawKey);
$secretKey->keyPrefix = substr($rawKey, 0, 4); // For admin display only
$secretKey->save();

// Return raw key ONCE to admin (they save it)
return ['key' => $rawKey, 'id' => $secretKey->id];
```

**Why It Matters**: Database breach exposes all secret keys; hashing prevents this.

---

### 7.2 Revealing Key Existence in Error Messages

**❌ WRONG:**
```php
if ($key->isExpired) {
    return new Response(403, 'Key expired'); // Reveals key exists!
}
if ($key->usageCount >= $key->usageLimit) {
    return new Response(403, 'Key usage limit reached'); // Reveals key exists!
}
```

**✅ CORRECT:**
```php
// Same error for all invalid cases - no information leakage
$key = $db->findOne('secret_keys', ['keyHash' => hash('sha256', $rawKey)]);

$isValid = $key !== null
    && $key->isActive
    && ($key->expiresAt === null || $key->expiresAt > now())
    && ($key->usageLimit === null || $key->usageCount < $key->usageLimit);

if (!$isValid) {
    return new Response(404, 'Invalid key'); // Same response for all
}
```

**Why It Matters**: Attackers can enumerate valid keys by observing different error messages.

---

### 7.3 Not Updating usageCount and lastUsedAt

**❌ WRONG:**
```php
// Validated key, proceeding...
return ['success' => true, 'examId' => $key->examId];
```

**✅ CORRECT:**
```php
// Update usage tracking
$key->usageCount++;
$key->lastUsedAt = new DateTime('now', new DateTimeZone('UTC'));
$key->save();

return ['success' => true, 'examId' => $key->examId];
```

**Why It Matters**: Usage limits don't work; analytics are missing; abuse is undetectable.

---

## 8. Status Transition Pitfalls

### 8.1 Direct Status Assignment

**❌ WRONG:**
```php
$participant->status = ParticipantStatus::COMPLETED;
$participant->save();
```

**✅ CORRECT:**
```php
$allowedTransitions = ParticipantStatus::allowedTransitions($participant->status);

if (!in_array(ParticipantStatus::COMPLETED, $allowedTransitions)) {
    throw new InvalidTransitionException(
        "Cannot transition from {$participant->status->value} to COMPLETED"
    );
}

$participant->status = ParticipantStatus::COMPLETED;
$participant->save();

$auditLog->record(AuditAction::STATUS_CHANGED, $participant->id, [
    'from' => $oldStatus->value,
    'to' => 'COMPLETED'
]);
```

**Why It Matters**: Invalid transitions (e.g., LOCKED → ACTIVE) corrupt data and bypass business rules.

---

### 8.2 Modifying Terminal States

**❌ WRONG:**
```php
// No check for terminal state
$participant->status = $newStatus;
$participant->save();
```

**✅ CORRECT:**
```php
if (ParticipantStatus::isTerminal($participant->status)) {
    throw new InvalidTransitionException(
        "Cannot modify participant in terminal state: {$participant->status->value}"
    );
}

$participant->status = $newStatus;
$participant->save();
```

**Terminal States:**
- `COMPLETED`
- `LOCKED`
- `WITHDRAWN`

**Why It Matters**: Terminal states are business-final; changing them violates audit integrity.

---

## 9. File Upload Pitfalls

### 9.1 Storing Files in Database

**❌ WRONG:**
```php
$submission->fileContent = file_get_contents($_FILES['file']['tmp_name']);
$submission->save();
```

**✅ CORRECT:**
```php
// Store in blob storage
$path = $blobStorage->upload(
    $_FILES['file']['tmp_name'],
    "submissions/{$participantId}/{$filename}"
);

$submission->filePath = $path;
$submission->fileSize = $_FILES['file']['size'];
$submission->mimeType = $_FILES['file']['type'];
$submission->save();
```

**Why It Matters**: Database bloat, backup issues, performance degradation.

---

### 9.2 Not Validating File Type Server-Side

**❌ WRONG:**
```php
// Trust client-provided MIME type
$mimeType = $_FILES['file']['type'];
```

**✅ CORRECT:**
```php
// Detect actual MIME type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($_FILES['file']['tmp_name']);

$allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
if (!in_array($mimeType, $allowedTypes)) {
    throw new ValidationException("Invalid file type: {$mimeType}");
}
```

**Why It Matters**: Attackers can upload executables with fake extensions.

---

## 10. Cron Job Pitfalls

### 10.1 Non-Idempotent Processing

**❌ WRONG:**
```php
// Sends email every time cron runs
foreach ($participants as $participant) {
    sendEmail($participant, 'DEADLINE_REMINDER');
}
```

**✅ CORRECT:**
```php
// Check if already notified
foreach ($participants as $participant) {
    if ($participant->softDeadlineNotifiedAt === null) {
        sendEmail($participant, 'DEADLINE_REMINDER');
        $participant->softDeadlineNotifiedAt = now();
        $participant->save();
    }
}
```

**Why It Matters**: Users receive duplicate emails; cron re-runs cause spam.

---

### 10.2 Stopping Batch on Single Error

**❌ WRONG:**
```php
foreach ($participants as $participant) {
    processDeadline($participant); // Throws on error → stops all
}
```

**✅ CORRECT:**
```php
$errors = [];
foreach ($participants as $participant) {
    try {
        processDeadline($participant);
    } catch (Exception $e) {
        $errors[] = ['participantId' => $participant->id, 'error' => $e->getMessage()];
        logError($e, ['participantId' => $participant->id]);
        // Continue processing others
    }
}

if (count($errors) > 0) {
    logWarning("Deadline check completed with errors", ['errors' => $errors]);
}
```

**Why It Matters**: One bad record shouldn't block processing of hundreds of valid records.

---

### 10.3 No Batch Size Limit

**❌ WRONG:**
```php
$participants = $db->query("SELECT * FROM participants WHERE status = 'ACTIVE'");
// Could be 100,000 records!
```

**✅ CORRECT:**
```php
$batchSize = Settings::get('cron_batch_size', 100);
$offset = 0;

do {
    $participants = $db->query(
        "SELECT * FROM participants WHERE status = 'ACTIVE' LIMIT ? OFFSET ?",
        [$batchSize, $offset]
    );
    
    foreach ($participants as $participant) {
        processDeadline($participant);
    }
    
    $offset += $batchSize;
} while (count($participants) === $batchSize);
```

**Why It Matters**: Memory exhaustion, timeout, server crash.

---

## 11. Theme System Pitfalls

### 11.1 Hardcoded Colors in Components

**❌ WRONG:**
```css
.button { background-color: #1e3a5f; color: white; }
.card { border: 1px solid #e5e7eb; }
```

**✅ CORRECT:**
```css
.button { 
  background-color: hsl(var(--primary)); 
  color: hsl(var(--primary-foreground)); 
}
.card { border: 1px solid hsl(var(--border)); }
```

**Why It Matters**: Hardcoded colors break theming, dark mode, and accessibility customization.

---

### 11.2 Ignoring Theme Scope

**❌ WRONG:**
```php
// Gets random theme regardless of context
$theme = $this->themeService->getActiveTheme();
```

**✅ CORRECT:**
```php
// Frontend context
$theme = $this->themeService->getActiveTheme(ThemeScope::FRONTEND);

// Admin context
$theme = $this->themeService->getActiveTheme(ThemeScope::ADMIN);
```

**Why It Matters**: Admin and frontend may have different active themes.

---

### 11.3 Overwriting User Customizations on Upgrade

**❌ WRONG:**
```php
// On plugin upgrade
$this->seedThemes(); // Replaces all themes with defaults
```

**✅ CORRECT:**
```php
// On plugin upgrade - merge only NEW keys
public function upgradeThemes(): void {
    $seedConfig = $this->loadSeedConfig();
    
    foreach ($this->getExistingThemes() as $theme) {
        // Deep merge: existing values preserved, new keys added
        $merged = $this->deepMerge($theme->config, $seedConfig[$theme->slug] ?? []);
        $theme->config = $merged;
        $theme->save();
    }
}
```

**Why It Matters**: User customizations are lost on every upgrade.

---

### 11.4 Direct Database Access for Theme Values

**❌ WRONG:**
```php
$theme = $wpdb->get_row("SELECT * FROM theme WHERE slug = 'default'");
$color = json_decode($theme->config)->colors->primary->base;
```

**✅ CORRECT:**
```php
$color = $this->themeService->getValue('colors.primary.base', ThemeScope::FRONTEND);
```

**Why It Matters**: Bypasses override resolution, caching, and fallback logic.

---

## 12. Caching System Pitfalls

### 12.1 Cache Without Invalidation Tags

**❌ WRONG:**
```php
// No way to invalidate when user data changes
$this->cache->set('user:123:profile', $userData, 3600);
```

**✅ CORRECT:**
```php
$this->cache->setWithTags(
    'user:123:profile', 
    $userData, 
    3600, 
    tags: ['user:123']
);

// Later, on user update:
$this->cache->invalidateByTag('user:123');
```

**Why It Matters**: Stale data served indefinitely or requires full cache flush.

---

### 12.2 Caching Sensitive Data

**❌ WRONG:**
```php
// Caching passwords, tokens, secret keys
$this->cache->set('user:123', [
    'email' => $user->email,
    'password' => $user->passwordHash,
    'apiToken' => $user->apiToken
]);
```

**✅ CORRECT:**
```php
$safeData = [
    'email' => $user->email,
    'name' => $user->name,
    'roles' => $user->roles
];
$this->cache->set('user:123:profile', $safeData);
```

**Why It Matters**: Security breach if cache is compromised.

---

### 12.3 No Cache Key Versioning

**❌ WRONG:**
```php
// No version - old schema data may be retrieved after code change
$key = "exam:{$slug}";
```

**✅ CORRECT:**
```php
// Include version for schema/format migrations
$key = "exam:{$slug}:v{$this->cacheSchemaVersion}";
```

**Why It Matters**: Deserialization errors or data corruption after schema changes.

---

### 12.4 Caching Dynamic or User-Specific Pages Without User Key

**❌ WRONG:**
```php
// Same cached page for all users
$key = "page:dashboard";
$html = $this->pageCache->get($key) ?? $this->render();
```

**✅ CORRECT:**
```php
// User-specific cache key
$key = sprintf('page:dashboard:user_%d:theme_%s', 
    $userId, 
    substr($themeHash, 0, 8)
);
$html = $this->pageCache->get($key) ?? $this->render();
```

**Why It Matters**: User A sees User B's data.

---

### 12.5 No Cache Bypass for Real-Time Data

**❌ WRONG:**
```php
// Always use cached deadline status
return $this->cache->get("participant:{$id}:deadline");
```

**✅ CORRECT:**
```php
// Bypass cache for deadline-critical requests
if ($this->isDeadlineCritical($request)) {
    return $this->participantService->getDeadlineStatus($id);
}
return $this->cache->get("participant:{$id}:deadline");
```

**Why It Matters**: User locked out prematurely or allowed access after deadline.

---

### 12.6 Session Cache Not Refreshed on Login

**❌ WRONG:**
```php
// Stale session data after role change
$roles = $_SESSION['eqm']['user']['roles'];
```

**✅ CORRECT:**
```php
public function onLogin(int $userId): void {
    // Always refresh session on login
    $userData = $this->userService->getById($userId);
    $_SESSION['eqm']['user'] = [
        'id' => $userData->id,
        'roles' => $userData->roles,
        'cachedAt' => time()
    ];
}
```

**Why It Matters**: Role/permission changes not reflected until session expires.

---

### 12.7 Page Cache Without Theme Hash

**❌ WRONG:**
```php
$key = "page:exam:{$slug}:user_{$userId}";
```

**✅ CORRECT:**
```php
$key = "page:exam:{$slug}:user_{$userId}:theme_{$themeHash}";
```

**Why It Matters**: Old theme rendered after theme switch.

---

## Quick Reference Card

| Area | Common Mistake | Correct Pattern |
|------|----------------|-----------------|
| **Deadlines** | Extend from current date | Extend from original hard deadline |
| **Progress** | `round()` | `floor()` (never show 100% unless complete) |
| **Migration** | Claim without checking existing | Check for merge scenario first |
| **H2 Extract** | Include code blocks | Strip code blocks before matching |
| **Cookies** | Global scope | Exam-scoped: `eqm_{purpose}_{examSlug}` |
| **Rate Limit** | Fixed window | Sliding window with Retry-After |
| **Secret Keys** | Store plain | Store hash, show once |
| **Status** | Direct assignment | Validate transition first |
| **Files** | Store in DB | Store in blob storage |
| **Cron** | Stop on error | Log and continue |
| **Theme** | Hardcoded colors | CSS variables: `hsl(var(--primary))` |
| **Cache** | No invalidation tags | Always use cache tags |
| **Cache** | No key versioning | Include version in cache key |
| **Page Cache** | No user/theme key | Include user_id + theme_hash |
