# 08 - Entity Models

> **Status:** Complete  
> **Priority:** High  
> **Updated:** 2026-01-31  
> **Dependencies:** `04-database-schema.md`, `66-shared-constants.md`

---

## Purpose

PHP entity classes representing all 25 database tables with proper type hints, validation, and serialization methods. Follows PascalCase for database columns and camelCase for ORM properties.

---

## Naming Convention

| Layer | Convention | Example |
|-------|------------|---------|
| Database Columns | PascalCase | `WpPostId`, `CreatedAt` |
| PHP Properties | camelCase | `$wpPostId`, `$createdAt` |
| PHP Classes | PascalCase | `Post`, `LinkHealthCheck` |

---

## Base Entity

**File:** `src/Entity/BaseEntity.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use DateTimeImmutable;
use JsonSerializable;

abstract class BaseEntity implements JsonSerializable
{
    protected ?int $id = null;
    protected DateTimeImmutable $createdAt;
    protected DateTimeImmutable $updatedAt;
    
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
    
    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
    
    public function touch(): self
    {
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }
    
    /**
     * Hydrate entity from database row
     */
    abstract public static function fromRow(array $row): static;
    
    /**
     * Convert to database-ready array
     */
    abstract public function toRow(): array;
    
    /**
     * JSON serialization
     */
    public function jsonSerialize(): array
    {
        return $this->toRow();
    }
    
    protected static function parseDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        return new DateTimeImmutable($value);
    }
    
    protected static function formatDateTime(?DateTimeImmutable $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return $value->format('Y-m-d H:i:s');
    }
}
```

---

## Core Content Entities

### Post Entity

**File:** `src/Entity/Post.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use DateTimeImmutable;

class Post extends BaseEntity
{
    private int $wpPostId;
    private string $title;
    private string $slug;
    private ?string $metaDescription = null;
    private string $postType = 'post';
    private string $postStatus = 'publish';
    private int $totalLinks = 0;
    private int $brokenLinks = 0;
    private int $workingLinks = 0;
    private int $unknownLinks = 0;
    private ?DateTimeImmutable $lastScannedAt = null;
    private bool $hasBrokenHtml = false;
    private bool $hasHistory = false;
    private ?string $historyDbPath = null;
    
    public function getWpPostId(): int
    {
        return $this->wpPostId;
    }
    
    public function setWpPostId(int $wpPostId): self
    {
        $this->wpPostId = $wpPostId;
        return $this;
    }
    
    public function getTitle(): string
    {
        return $this->title;
    }
    
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }
    
    public function getSlug(): string
    {
        return $this->slug;
    }
    
    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }
    
    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }
    
    public function setMetaDescription(?string $metaDescription): self
    {
        $this->metaDescription = $metaDescription;
        return $this;
    }
    
    public function getTotalLinks(): int
    {
        return $this->totalLinks;
    }
    
    public function getBrokenLinks(): int
    {
        return $this->brokenLinks;
    }
    
    public function hasBrokenLinks(): bool
    {
        return $this->brokenLinks > 0;
    }
    
    public function updateLinkCounts(int $total, int $broken, int $working, int $unknown): self
    {
        $this->totalLinks = $total;
        $this->brokenLinks = $broken;
        $this->workingLinks = $working;
        $this->unknownLinks = $unknown;
        return $this->touch();
    }
    
    public function markScanned(): self
    {
        $this->lastScannedAt = new DateTimeImmutable();
        return $this->touch();
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->wpPostId = (int) $row['WpPostId'];
        $entity->title = $row['Title'];
        $entity->slug = $row['Slug'];
        $entity->metaDescription = $row['MetaDescription'] ?? null;
        $entity->postType = $row['PostType'] ?? 'post';
        $entity->postStatus = $row['PostStatus'] ?? 'publish';
        $entity->totalLinks = (int) ($row['TotalLinks'] ?? 0);
        $entity->brokenLinks = (int) ($row['BrokenLinks'] ?? 0);
        $entity->workingLinks = (int) ($row['WorkingLinks'] ?? 0);
        $entity->unknownLinks = (int) ($row['UnknownLinks'] ?? 0);
        $entity->lastScannedAt = self::parseDateTime($row['LastScannedAt'] ?? null);
        $entity->hasBrokenHtml = (bool) ($row['HasBrokenHtml'] ?? false);
        $entity->hasHistory = (bool) ($row['HasHistory'] ?? false);
        $entity->historyDbPath = $row['HistoryDbPath'] ?? null;
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        $entity->updatedAt = self::parseDateTime($row['UpdatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'WpPostId' => $this->wpPostId,
            'Title' => $this->title,
            'Slug' => $this->slug,
            'MetaDescription' => $this->metaDescription,
            'PostType' => $this->postType,
            'PostStatus' => $this->postStatus,
            'TotalLinks' => $this->totalLinks,
            'BrokenLinks' => $this->brokenLinks,
            'WorkingLinks' => $this->workingLinks,
            'UnknownLinks' => $this->unknownLinks,
            'LastScannedAt' => self::formatDateTime($this->lastScannedAt),
            'HasBrokenHtml' => $this->hasBrokenHtml ? 1 : 0,
            'HasHistory' => $this->hasHistory ? 1 : 0,
            'HistoryDbPath' => $this->historyDbPath,
            'CreatedAt' => self::formatDateTime($this->createdAt),
            'UpdatedAt' => self::formatDateTime($this->updatedAt),
        ];
    }
}
```

---

### Page Entity

**File:** `src/Entity/Page.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use DateTimeImmutable;

class Page extends BaseEntity
{
    private int $wpPageId;
    private string $title;
    private string $slug;
    private ?string $metaDescription = null;
    private string $pageStatus = 'publish';
    private int $totalLinks = 0;
    private int $brokenLinks = 0;
    private int $workingLinks = 0;
    private int $unknownLinks = 0;
    private ?DateTimeImmutable $lastScannedAt = null;
    private bool $hasBrokenHtml = false;
    private bool $hasHistory = false;
    private ?string $historyDbPath = null;
    
    // Getters/setters follow same pattern as Post...
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->wpPageId = (int) $row['WpPageId'];
        $entity->title = $row['Title'];
        $entity->slug = $row['Slug'];
        $entity->metaDescription = $row['MetaDescription'] ?? null;
        $entity->pageStatus = $row['PageStatus'] ?? 'publish';
        $entity->totalLinks = (int) ($row['TotalLinks'] ?? 0);
        $entity->brokenLinks = (int) ($row['BrokenLinks'] ?? 0);
        $entity->workingLinks = (int) ($row['WorkingLinks'] ?? 0);
        $entity->unknownLinks = (int) ($row['UnknownLinks'] ?? 0);
        $entity->lastScannedAt = self::parseDateTime($row['LastScannedAt'] ?? null);
        $entity->hasBrokenHtml = (bool) ($row['HasBrokenHtml'] ?? false);
        $entity->hasHistory = (bool) ($row['HasHistory'] ?? false);
        $entity->historyDbPath = $row['HistoryDbPath'] ?? null;
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        $entity->updatedAt = self::parseDateTime($row['UpdatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'WpPageId' => $this->wpPageId,
            'Title' => $this->title,
            'Slug' => $this->slug,
            'MetaDescription' => $this->metaDescription,
            'PageStatus' => $this->pageStatus,
            'TotalLinks' => $this->totalLinks,
            'BrokenLinks' => $this->brokenLinks,
            'WorkingLinks' => $this->workingLinks,
            'UnknownLinks' => $this->unknownLinks,
            'LastScannedAt' => self::formatDateTime($this->lastScannedAt),
            'HasBrokenHtml' => $this->hasBrokenHtml ? 1 : 0,
            'HasHistory' => $this->hasHistory ? 1 : 0,
            'HistoryDbPath' => $this->historyDbPath,
            'CreatedAt' => self::formatDateTime($this->createdAt),
            'UpdatedAt' => self::formatDateTime($this->updatedAt),
        ];
    }
}
```

---

### Category Entity

**File:** `src/Entity/Category.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

class Category extends BaseEntity
{
    private int $wpCategoryId;
    private string $name;
    private string $slug;
    private ?string $description = null;
    private int $totalLinks = 0;
    private int $brokenLinks = 0;
    private int $workingLinks = 0;
    private ?DateTimeImmutable $lastScannedAt = null;
    private bool $hasHistory = false;
    private ?string $historyDbPath = null;
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->wpCategoryId = (int) $row['WpCategoryId'];
        $entity->name = $row['Name'];
        $entity->slug = $row['Slug'];
        $entity->description = $row['Description'] ?? null;
        $entity->totalLinks = (int) ($row['TotalLinks'] ?? 0);
        $entity->brokenLinks = (int) ($row['BrokenLinks'] ?? 0);
        $entity->workingLinks = (int) ($row['WorkingLinks'] ?? 0);
        $entity->lastScannedAt = self::parseDateTime($row['LastScannedAt'] ?? null);
        $entity->hasHistory = (bool) ($row['HasHistory'] ?? false);
        $entity->historyDbPath = $row['HistoryDbPath'] ?? null;
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        $entity->updatedAt = self::parseDateTime($row['UpdatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'WpCategoryId' => $this->wpCategoryId,
            'Name' => $this->name,
            'Slug' => $this->slug,
            'Description' => $this->description,
            'TotalLinks' => $this->totalLinks,
            'BrokenLinks' => $this->brokenLinks,
            'WorkingLinks' => $this->workingLinks,
            'LastScannedAt' => self::formatDateTime($this->lastScannedAt),
            'HasHistory' => $this->hasHistory ? 1 : 0,
            'HistoryDbPath' => $this->historyDbPath,
            'CreatedAt' => self::formatDateTime($this->createdAt),
            'UpdatedAt' => self::formatDateTime($this->updatedAt),
        ];
    }
}
```

---

### Link Entity

**File:** `src/Entity/Link.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use LinkManager\Enums\ContentType;
use LinkManager\Enums\LinkStatus;
use LinkManager\Enums\LinkWordCount;

class Link extends BaseEntity
{
    private ContentType $contentType;
    private int $contentId;
    private int $wpContentId;
    private string $url;
    private ?string $anchorText = null;
    private ?string $titleAttribute = null;
    private LinkStatus $status;
    private ?int $httpStatusCode = null;
    private ?DateTimeImmutable $lastCheckedAt = null;
    private string $linkSource = 'POST_CONTENT';
    private LinkWordCount $wordCount;
    private ?array $wrapperTags = null;
    private bool $hasHeadingWrapper = false;
    private bool $hasEmphasisWrapper = false;
    private int $positionIndex = 0;
    private ?string $elementorWidgetId = null;
    private ?int $scanHistoryId = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->status = LinkStatus::UNKNOWN;
        $this->wordCount = LinkWordCount::THREE_PLUS;
    }
    
    public function getUrl(): string
    {
        return $this->url;
    }
    
    public function setUrl(string $url): self
    {
        if (strlen($url) > 2048) {
            throw new \InvalidArgumentException('URL exceeds maximum length of 2048 characters');
        }
        $this->url = $url;
        return $this;
    }
    
    public function isBroken(): bool
    {
        return $this->status === LinkStatus::BROKEN;
    }
    
    public function markAsChecked(LinkStatus $status, ?int $httpCode = null): self
    {
        $this->status = $status;
        $this->httpStatusCode = $httpCode;
        $this->lastCheckedAt = new DateTimeImmutable();
        return $this->touch();
    }
    
    public function getWrapperTags(): array
    {
        return $this->wrapperTags ?? [];
    }
    
    public function setWrapperTags(array $tags): self
    {
        $this->wrapperTags = $tags;
        $this->hasHeadingWrapper = !empty(array_intersect($tags, ['H1', 'H2', 'H3', 'H4', 'H5', 'H6']));
        $this->hasEmphasisWrapper = !empty(array_intersect($tags, ['STRONG', 'EM', 'B', 'I']));
        return $this;
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->contentType = ContentType::from($row['ContentType']);
        $entity->contentId = (int) $row['ContentId'];
        $entity->wpContentId = (int) $row['WpContentId'];
        $entity->url = $row['Url'];
        $entity->anchorText = $row['AnchorText'] ?? null;
        $entity->titleAttribute = $row['TitleAttribute'] ?? null;
        $entity->status = LinkStatus::from($row['Status'] ?? 'UNKNOWN');
        $entity->httpStatusCode = isset($row['HttpStatusCode']) ? (int) $row['HttpStatusCode'] : null;
        $entity->lastCheckedAt = self::parseDateTime($row['LastCheckedAt'] ?? null);
        $entity->linkSource = $row['LinkSource'] ?? 'POST_CONTENT';
        $entity->wordCount = LinkWordCount::from($row['WordCount'] ?? 'THREE_PLUS');
        $entity->wrapperTags = isset($row['WrapperTags']) ? json_decode($row['WrapperTags'], true) : null;
        $entity->hasHeadingWrapper = (bool) ($row['HasHeadingWrapper'] ?? false);
        $entity->hasEmphasisWrapper = (bool) ($row['HasEmphasisWrapper'] ?? false);
        $entity->positionIndex = (int) ($row['PositionIndex'] ?? 0);
        $entity->elementorWidgetId = $row['ElementorWidgetId'] ?? null;
        $entity->scanHistoryId = isset($row['ScanHistoryId']) ? (int) $row['ScanHistoryId'] : null;
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        $entity->updatedAt = self::parseDateTime($row['UpdatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'ContentType' => $this->contentType->value,
            'ContentId' => $this->contentId,
            'WpContentId' => $this->wpContentId,
            'Url' => $this->url,
            'AnchorText' => $this->anchorText,
            'TitleAttribute' => $this->titleAttribute,
            'Status' => $this->status->value,
            'HttpStatusCode' => $this->httpStatusCode,
            'LastCheckedAt' => self::formatDateTime($this->lastCheckedAt),
            'LinkSource' => $this->linkSource,
            'WordCount' => $this->wordCount->value,
            'WrapperTags' => $this->wrapperTags ? json_encode($this->wrapperTags) : null,
            'HasHeadingWrapper' => $this->hasHeadingWrapper ? 1 : 0,
            'HasEmphasisWrapper' => $this->hasEmphasisWrapper ? 1 : 0,
            'PositionIndex' => $this->positionIndex,
            'ElementorWidgetId' => $this->elementorWidgetId,
            'ScanHistoryId' => $this->scanHistoryId,
            'CreatedAt' => self::formatDateTime($this->createdAt),
            'UpdatedAt' => self::formatDateTime($this->updatedAt),
        ];
    }
}
```

---

## Scan & History Entities

### ScanHistory Entity

**File:** `src/Entity/ScanHistory.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use LinkManager\Enums\ScanMode;
use LinkManager\Enums\ScanStatus;

class ScanHistory extends BaseEntity
{
    private ScanMode $scanMode;
    private array $contentTypes = ['POST', 'PAGE'];
    private ScanStatus $status;
    private int $totalItems = 0;
    private int $processedItems = 0;
    private int $totalLinksFound = 0;
    private int $brokenLinksFound = 0;
    private ?DateTimeImmutable $startedAt = null;
    private ?DateTimeImmutable $completedAt = null;
    private ?int $durationSeconds = null;
    private ?string $errorMessage = null;
    private ?string $errorDetails = null;
    private ?int $initiatedBy = null;
    private bool $isCronJob = false;
    
    public function __construct()
    {
        parent::__construct();
        $this->scanMode = ScanMode::ALL_LINKS;
        $this->status = ScanStatus::PENDING;
    }
    
    public function start(): self
    {
        $this->status = ScanStatus::RUNNING;
        $this->startedAt = new DateTimeImmutable();
        return $this;
    }
    
    public function complete(): self
    {
        $this->status = ScanStatus::COMPLETED;
        $this->completedAt = new DateTimeImmutable();
        if ($this->startedAt) {
            $this->durationSeconds = $this->completedAt->getTimestamp() - $this->startedAt->getTimestamp();
        }
        return $this;
    }
    
    public function fail(string $message, ?string $details = null): self
    {
        $this->status = ScanStatus::FAILED;
        $this->completedAt = new DateTimeImmutable();
        $this->errorMessage = $message;
        $this->errorDetails = $details;
        return $this;
    }
    
    public function getProgressPercentage(): float
    {
        if ($this->totalItems === 0) {
            return 0.0;
        }
        return round(($this->processedItems / $this->totalItems) * 100, 1);
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->scanMode = ScanMode::from($row['ScanMode'] ?? 'ALL_LINKS');
        $entity->contentTypes = json_decode($row['ContentTypes'] ?? '["POST","PAGE"]', true);
        $entity->status = ScanStatus::from($row['Status'] ?? 'PENDING');
        $entity->totalItems = (int) ($row['TotalItems'] ?? 0);
        $entity->processedItems = (int) ($row['ProcessedItems'] ?? 0);
        $entity->totalLinksFound = (int) ($row['TotalLinksFound'] ?? 0);
        $entity->brokenLinksFound = (int) ($row['BrokenLinksFound'] ?? 0);
        $entity->startedAt = self::parseDateTime($row['StartedAt'] ?? null);
        $entity->completedAt = self::parseDateTime($row['CompletedAt'] ?? null);
        $entity->durationSeconds = isset($row['DurationSeconds']) ? (int) $row['DurationSeconds'] : null;
        $entity->errorMessage = $row['ErrorMessage'] ?? null;
        $entity->errorDetails = $row['ErrorDetails'] ?? null;
        $entity->initiatedBy = isset($row['InitiatedBy']) ? (int) $row['InitiatedBy'] : null;
        $entity->isCronJob = (bool) ($row['IsCronJob'] ?? false);
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'ScanMode' => $this->scanMode->value,
            'ContentTypes' => json_encode($this->contentTypes),
            'Status' => $this->status->value,
            'TotalItems' => $this->totalItems,
            'ProcessedItems' => $this->processedItems,
            'TotalLinksFound' => $this->totalLinksFound,
            'BrokenLinksFound' => $this->brokenLinksFound,
            'StartedAt' => self::formatDateTime($this->startedAt),
            'CompletedAt' => self::formatDateTime($this->completedAt),
            'DurationSeconds' => $this->durationSeconds,
            'ErrorMessage' => $this->errorMessage,
            'ErrorDetails' => $this->errorDetails,
            'InitiatedBy' => $this->initiatedBy,
            'IsCronJob' => $this->isCronJob ? 1 : 0,
            'CreatedAt' => self::formatDateTime($this->createdAt),
        ];
    }
}
```

---

### Snapshot Entity

**File:** `src/Entity/Snapshot.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use LinkManager\Enums\SnapshotType;

class Snapshot extends BaseEntity
{
    private int $snapshotNumber;
    private string $name;
    private string $fileName;
    private string $filePath;
    private SnapshotType $type;
    private int $postCount = 0;
    private int $pageCount = 0;
    private int $categoryCount = 0;
    private int $linkCount = 0;
    private int $sizeBytes = 0;
    private bool $includesHistory = false;
    private ?int $createdBy = null;
    private ?DateTimeImmutable $restoredAt = null;
    private ?int $restoredBy = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->type = SnapshotType::MANUAL;
    }
    
    public function getSizeFormatted(): string
    {
        $bytes = $this->sizeBytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    public function markRestored(int $userId): self
    {
        $this->restoredAt = new DateTimeImmutable();
        $this->restoredBy = $userId;
        return $this;
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->snapshotNumber = (int) $row['SnapshotNumber'];
        $entity->name = $row['Name'];
        $entity->fileName = $row['FileName'];
        $entity->filePath = $row['FilePath'];
        $entity->type = SnapshotType::from($row['Type'] ?? 'MANUAL');
        $entity->postCount = (int) ($row['PostCount'] ?? 0);
        $entity->pageCount = (int) ($row['PageCount'] ?? 0);
        $entity->categoryCount = (int) ($row['CategoryCount'] ?? 0);
        $entity->linkCount = (int) ($row['LinkCount'] ?? 0);
        $entity->sizeBytes = (int) ($row['SizeBytes'] ?? 0);
        $entity->includesHistory = (bool) ($row['IncludesHistory'] ?? false);
        $entity->createdBy = isset($row['CreatedBy']) ? (int) $row['CreatedBy'] : null;
        $entity->restoredAt = self::parseDateTime($row['RestoredAt'] ?? null);
        $entity->restoredBy = isset($row['RestoredBy']) ? (int) $row['RestoredBy'] : null;
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'SnapshotNumber' => $this->snapshotNumber,
            'Name' => $this->name,
            'FileName' => $this->fileName,
            'FilePath' => $this->filePath,
            'Type' => $this->type->value,
            'PostCount' => $this->postCount,
            'PageCount' => $this->pageCount,
            'CategoryCount' => $this->categoryCount,
            'LinkCount' => $this->linkCount,
            'SizeBytes' => $this->sizeBytes,
            'IncludesHistory' => $this->includesHistory ? 1 : 0,
            'CreatedBy' => $this->createdBy,
            'RestoredAt' => self::formatDateTime($this->restoredAt),
            'RestoredBy' => $this->restoredBy,
            'CreatedAt' => self::formatDateTime($this->createdAt),
        ];
    }
}
```

---

### Settings Entity

**File:** `src/Entity/Settings.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

class Settings extends BaseEntity
{
    private string $settingKey;
    private string $settingValue;
    private string $settingType = 'string';
    private ?string $description = null;
    private bool $isUserModified = false;
    
    public function getValue(): mixed
    {
        return match ($this->settingType) {
            'int', 'integer' => (int) $this->settingValue,
            'bool', 'boolean' => filter_var($this->settingValue, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($this->settingValue, true),
            'float' => (float) $this->settingValue,
            default => $this->settingValue,
        };
    }
    
    public function setValue(mixed $value): self
    {
        $this->settingValue = is_array($value) ? json_encode($value) : (string) $value;
        $this->isUserModified = true;
        return $this->touch();
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->settingKey = $row['SettingKey'];
        $entity->settingValue = $row['SettingValue'];
        $entity->settingType = $row['SettingType'] ?? 'string';
        $entity->description = $row['Description'] ?? null;
        $entity->isUserModified = (bool) ($row['IsUserModified'] ?? false);
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        $entity->updatedAt = self::parseDateTime($row['UpdatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'SettingKey' => $this->settingKey,
            'SettingValue' => $this->settingValue,
            'SettingType' => $this->settingType,
            'Description' => $this->description,
            'IsUserModified' => $this->isUserModified ? 1 : 0,
            'CreatedAt' => self::formatDateTime($this->createdAt),
            'UpdatedAt' => self::formatDateTime($this->updatedAt),
        ];
    }
}
```

---

## Internal Linking Entities

### LinkTarget Entity

**File:** `src/Entity/LinkTarget.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

class LinkTarget extends BaseEntity
{
    private string $url;
    private string $title;
    private ?string $description = null;
    private ?string $keywords = null;
    private bool $isActive = true;
    private int $usageCount = 0;
    private ?int $maxUsageLimit = null;
    
    public function canBeUsed(): bool
    {
        if (!$this->isActive) {
            return false;
        }
        if ($this->maxUsageLimit !== null && $this->usageCount >= $this->maxUsageLimit) {
            return false;
        }
        return true;
    }
    
    public function incrementUsage(): self
    {
        $this->usageCount++;
        return $this->touch();
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->url = $row['Url'];
        $entity->title = $row['Title'];
        $entity->description = $row['Description'] ?? null;
        $entity->keywords = $row['Keywords'] ?? null;
        $entity->isActive = (bool) ($row['IsActive'] ?? true);
        $entity->usageCount = (int) ($row['UsageCount'] ?? 0);
        $entity->maxUsageLimit = isset($row['MaxUsageLimit']) ? (int) $row['MaxUsageLimit'] : null;
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        $entity->updatedAt = self::parseDateTime($row['UpdatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'Url' => $this->url,
            'Title' => $this->title,
            'Description' => $this->description,
            'Keywords' => $this->keywords,
            'IsActive' => $this->isActive ? 1 : 0,
            'UsageCount' => $this->usageCount,
            'MaxUsageLimit' => $this->maxUsageLimit,
            'CreatedAt' => self::formatDateTime($this->createdAt),
            'UpdatedAt' => self::formatDateTime($this->updatedAt),
        ];
    }
}
```

---

### LinkTemplate Entity

**File:** `src/Entity/LinkTemplate.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

class LinkTemplate extends BaseEntity
{
    private string $name;
    private string $template;
    private ?string $description = null;
    private bool $isDefault = false;
    private bool $isActive = true;
    
    public function render(array $variables): string
    {
        $result = $this->template;
        foreach ($variables as $key => $value) {
            $result = str_replace("{{$key}}", $value, $result);
        }
        return $result;
    }
    
    public function getVariableNames(): array
    {
        preg_match_all('/\{(\w+)\}/', $this->template, $matches);
        return array_unique($matches[1] ?? []);
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->name = $row['Name'];
        $entity->template = $row['Template'];
        $entity->description = $row['Description'] ?? null;
        $entity->isDefault = (bool) ($row['IsDefault'] ?? false);
        $entity->isActive = (bool) ($row['IsActive'] ?? true);
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        $entity->updatedAt = self::parseDateTime($row['UpdatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'Name' => $this->name,
            'Template' => $this->template,
            'Description' => $this->description,
            'IsDefault' => $this->isDefault ? 1 : 0,
            'IsActive' => $this->isActive ? 1 : 0,
            'CreatedAt' => self::formatDateTime($this->createdAt),
            'UpdatedAt' => self::formatDateTime($this->updatedAt),
        ];
    }
}
```

---

### LinkVariable Entity

**File:** `src/Entity/LinkVariable.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use LinkManager\Enums\VariableSelectionMode;

class LinkVariable extends BaseEntity
{
    private string $name;
    private array $values = [];
    private VariableSelectionMode $selectionMode;
    private int $currentIndex = 0;
    
    public function __construct()
    {
        parent::__construct();
        $this->selectionMode = VariableSelectionMode::SEQUENTIAL;
    }
    
    public function getNextValue(): ?string
    {
        if (empty($this->values)) {
            return null;
        }
        
        return match ($this->selectionMode) {
            VariableSelectionMode::SEQUENTIAL => $this->getSequentialValue(),
            VariableSelectionMode::RANDOM => $this->getRandomValue(),
            VariableSelectionMode::WEIGHTED => $this->getWeightedValue(),
        };
    }
    
    private function getSequentialValue(): string
    {
        $value = $this->values[$this->currentIndex]['value'] ?? $this->values[$this->currentIndex];
        $this->currentIndex = ($this->currentIndex + 1) % count($this->values);
        return $value;
    }
    
    private function getRandomValue(): string
    {
        $index = array_rand($this->values);
        return $this->values[$index]['value'] ?? $this->values[$index];
    }
    
    private function getWeightedValue(): string
    {
        $totalWeight = array_sum(array_column($this->values, 'weight'));
        $random = mt_rand(1, $totalWeight);
        $current = 0;
        
        foreach ($this->values as $item) {
            $current += $item['weight'] ?? 1;
            if ($random <= $current) {
                return $item['value'];
            }
        }
        
        return $this->values[0]['value'] ?? $this->values[0];
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->name = $row['Name'];
        $entity->values = json_decode($row['Values'] ?? '[]', true);
        $entity->selectionMode = VariableSelectionMode::from($row['SelectionMode'] ?? 'SEQUENTIAL');
        $entity->currentIndex = (int) ($row['CurrentIndex'] ?? 0);
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        $entity->updatedAt = self::parseDateTime($row['UpdatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'Name' => $this->name,
            'Values' => json_encode($this->values),
            'SelectionMode' => $this->selectionMode->value,
            'CurrentIndex' => $this->currentIndex,
            'CreatedAt' => self::formatDateTime($this->createdAt),
            'UpdatedAt' => self::formatDateTime($this->updatedAt),
        ];
    }
}
```

---

### InternalLink Entity

**File:** `src/Entity/InternalLink.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use LinkManager\Enums\ContentType;
use LinkManager\Enums\InternalLinkSource;

class InternalLink extends BaseEntity
{
    private ContentType $contentType;
    private int $contentId;
    private int $wpContentId;
    private string $targetUrl;
    private string $anchorText;
    private ?int $templateId = null;
    private InternalLinkSource $source;
    private bool $isActive = true;
    private int $positionIndex = 0;
    
    public function __construct()
    {
        parent::__construct();
        $this->source = InternalLinkSource::MANUAL_CREATE;
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->contentType = ContentType::from($row['ContentType']);
        $entity->contentId = (int) $row['ContentId'];
        $entity->wpContentId = (int) $row['WpContentId'];
        $entity->targetUrl = $row['TargetUrl'];
        $entity->anchorText = $row['AnchorText'];
        $entity->templateId = isset($row['TemplateId']) ? (int) $row['TemplateId'] : null;
        $entity->source = InternalLinkSource::from($row['Source'] ?? 'MANUAL_CREATE');
        $entity->isActive = (bool) ($row['IsActive'] ?? true);
        $entity->positionIndex = (int) ($row['PositionIndex'] ?? 0);
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        $entity->updatedAt = self::parseDateTime($row['UpdatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'ContentType' => $this->contentType->value,
            'ContentId' => $this->contentId,
            'WpContentId' => $this->wpContentId,
            'TargetUrl' => $this->targetUrl,
            'AnchorText' => $this->anchorText,
            'TemplateId' => $this->templateId,
            'Source' => $this->source->value,
            'IsActive' => $this->isActive ? 1 : 0,
            'PositionIndex' => $this->positionIndex,
            'CreatedAt' => self::formatDateTime($this->createdAt),
            'UpdatedAt' => self::formatDateTime($this->updatedAt),
        ];
    }
}
```

---

## Cron Job Entities

### ScanJob Entity

**File:** `src/Entity/ScanJob.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use LinkManager\Enums\ScanStatus;

class ScanJob extends BaseEntity
{
    private ScanStatus $status;
    private string $jobType = 'SCAN';
    private ?DateTimeImmutable $scheduledAt = null;
    private ?DateTimeImmutable $startedAt = null;
    private ?DateTimeImmutable $completedAt = null;
    private int $itemsProcessed = 0;
    private int $itemsTotal = 0;
    private ?string $errorMessage = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->status = ScanStatus::PENDING;
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->status = ScanStatus::from($row['Status'] ?? 'PENDING');
        $entity->jobType = $row['JobType'] ?? 'SCAN';
        $entity->scheduledAt = self::parseDateTime($row['ScheduledAt'] ?? null);
        $entity->startedAt = self::parseDateTime($row['StartedAt'] ?? null);
        $entity->completedAt = self::parseDateTime($row['CompletedAt'] ?? null);
        $entity->itemsProcessed = (int) ($row['ItemsProcessed'] ?? 0);
        $entity->itemsTotal = (int) ($row['ItemsTotal'] ?? 0);
        $entity->errorMessage = $row['ErrorMessage'] ?? null;
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'Status' => $this->status->value,
            'JobType' => $this->jobType,
            'ScheduledAt' => self::formatDateTime($this->scheduledAt),
            'StartedAt' => self::formatDateTime($this->startedAt),
            'CompletedAt' => self::formatDateTime($this->completedAt),
            'ItemsProcessed' => $this->itemsProcessed,
            'ItemsTotal' => $this->itemsTotal,
            'ErrorMessage' => $this->errorMessage,
            'CreatedAt' => self::formatDateTime($this->createdAt),
        ];
    }
}
```

---

### AutoLinkJob Entity

**File:** `src/Entity/AutoLinkJob.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use LinkManager\Enums\ScanStatus;
use LinkManager\Enums\AutoLinkJobType;

class AutoLinkJob extends BaseEntity
{
    private AutoLinkJobType $jobType;
    private ScanStatus $status;
    private int $totalItems = 0;
    private int $processedItems = 0;
    private int $linksCreated = 0;
    private int $linksSkipped = 0;
    private ?DateTimeImmutable $startedAt = null;
    private ?DateTimeImmutable $completedAt = null;
    private ?string $errorMessage = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->jobType = AutoLinkJobType::ORPHAN_CONTENT;
        $this->status = ScanStatus::PENDING;
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->jobType = AutoLinkJobType::from($row['JobType'] ?? 'ORPHAN_CONTENT');
        $entity->status = ScanStatus::from($row['Status'] ?? 'PENDING');
        $entity->totalItems = (int) ($row['TotalItems'] ?? 0);
        $entity->processedItems = (int) ($row['ProcessedItems'] ?? 0);
        $entity->linksCreated = (int) ($row['LinksCreated'] ?? 0);
        $entity->linksSkipped = (int) ($row['LinksSkipped'] ?? 0);
        $entity->startedAt = self::parseDateTime($row['StartedAt'] ?? null);
        $entity->completedAt = self::parseDateTime($row['CompletedAt'] ?? null);
        $entity->errorMessage = $row['ErrorMessage'] ?? null;
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'JobType' => $this->jobType->value,
            'Status' => $this->status->value,
            'TotalItems' => $this->totalItems,
            'ProcessedItems' => $this->processedItems,
            'LinksCreated' => $this->linksCreated,
            'LinksSkipped' => $this->linksSkipped,
            'StartedAt' => self::formatDateTime($this->startedAt),
            'CompletedAt' => self::formatDateTime($this->completedAt),
            'ErrorMessage' => $this->errorMessage,
            'CreatedAt' => self::formatDateTime($this->createdAt),
        ];
    }
}
```

---

## Health Monitor Entities

### LinkHealthCheck Entity

**File:** `src/Entity/LinkHealthCheck.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use LinkManager\Enums\LinkHealthStatus;
use LinkManager\Enums\HealthCheckPriority;

class LinkHealthCheck extends BaseEntity
{
    private int $linkId;
    private string $url;
    private LinkHealthStatus $status;
    private ?int $httpCode = null;
    private ?int $responseTimeMs = null;
    private int $redirectCount = 0;
    private ?string $finalUrl = null;
    private ?string $errorMessage = null;
    private bool $sslValid = true;
    private ?DateTimeImmutable $sslExpiry = null;
    private HealthCheckPriority $priority;
    private ?DateTimeImmutable $lastCheckedAt = null;
    private ?DateTimeImmutable $nextCheckAt = null;
    private int $checkCount = 0;
    private int $consecutiveFailures = 0;
    
    public function __construct()
    {
        parent::__construct();
        $this->status = LinkHealthStatus::UNKNOWN;
        $this->priority = HealthCheckPriority::NORMAL;
    }
    
    public function isHealthy(): bool
    {
        return $this->status === LinkHealthStatus::HEALTHY;
    }
    
    public function isBroken(): bool
    {
        return $this->status === LinkHealthStatus::BROKEN;
    }
    
    public function isSlow(): bool
    {
        return $this->status === LinkHealthStatus::SLOW;
    }
    
    public function recordCheck(
        LinkHealthStatus $status,
        ?int $httpCode = null,
        ?int $responseTimeMs = null
    ): self {
        $this->status = $status;
        $this->httpCode = $httpCode;
        $this->responseTimeMs = $responseTimeMs;
        $this->lastCheckedAt = new DateTimeImmutable();
        $this->checkCount++;
        
        if ($status === LinkHealthStatus::BROKEN) {
            $this->consecutiveFailures++;
        } else {
            $this->consecutiveFailures = 0;
        }
        
        return $this->touch();
    }
    
    public function scheduleNextCheck(DateTimeImmutable $nextCheck): self
    {
        $this->nextCheckAt = $nextCheck;
        return $this;
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->linkId = (int) $row['LinkId'];
        $entity->url = $row['Url'];
        $entity->status = LinkHealthStatus::from($row['Status'] ?? 'UNKNOWN');
        $entity->httpCode = isset($row['HttpCode']) ? (int) $row['HttpCode'] : null;
        $entity->responseTimeMs = isset($row['ResponseTimeMs']) ? (int) $row['ResponseTimeMs'] : null;
        $entity->redirectCount = (int) ($row['RedirectCount'] ?? 0);
        $entity->finalUrl = $row['FinalUrl'] ?? null;
        $entity->errorMessage = $row['ErrorMessage'] ?? null;
        $entity->sslValid = (bool) ($row['SslValid'] ?? true);
        $entity->sslExpiry = self::parseDateTime($row['SslExpiry'] ?? null);
        $entity->priority = HealthCheckPriority::from($row['Priority'] ?? 'NORMAL');
        $entity->lastCheckedAt = self::parseDateTime($row['LastCheckedAt'] ?? null);
        $entity->nextCheckAt = self::parseDateTime($row['NextCheckAt'] ?? null);
        $entity->checkCount = (int) ($row['CheckCount'] ?? 0);
        $entity->consecutiveFailures = (int) ($row['ConsecutiveFailures'] ?? 0);
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        $entity->updatedAt = self::parseDateTime($row['UpdatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'LinkId' => $this->linkId,
            'Url' => $this->url,
            'Status' => $this->status->value,
            'HttpCode' => $this->httpCode,
            'ResponseTimeMs' => $this->responseTimeMs,
            'RedirectCount' => $this->redirectCount,
            'FinalUrl' => $this->finalUrl,
            'ErrorMessage' => $this->errorMessage,
            'SslValid' => $this->sslValid ? 1 : 0,
            'SslExpiry' => self::formatDateTime($this->sslExpiry),
            'Priority' => $this->priority->value,
            'LastCheckedAt' => self::formatDateTime($this->lastCheckedAt),
            'NextCheckAt' => self::formatDateTime($this->nextCheckAt),
            'CheckCount' => $this->checkCount,
            'ConsecutiveFailures' => $this->consecutiveFailures,
            'CreatedAt' => self::formatDateTime($this->createdAt),
            'UpdatedAt' => self::formatDateTime($this->updatedAt),
        ];
    }
}
```

---

### HealthAlert Entity

**File:** `src/Entity/HealthAlert.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use LinkManager\Enums\HealthAlertType;
use LinkManager\Enums\HealthAlertSeverity;

class HealthAlert extends BaseEntity
{
    private int $healthCheckId;
    private HealthAlertType $alertType;
    private HealthAlertSeverity $severity;
    private string $message;
    private ?array $details = null;
    private ?int $contentId = null;
    private ?string $contentType = null;
    private bool $acknowledged = false;
    private ?string $acknowledgedBy = null;
    private ?DateTimeImmutable $acknowledgedAt = null;
    private ?DateTimeImmutable $resolvedAt = null;
    
    public function acknowledge(string $userId): self
    {
        $this->acknowledged = true;
        $this->acknowledgedBy = $userId;
        $this->acknowledgedAt = new DateTimeImmutable();
        return $this;
    }
    
    public function resolve(): self
    {
        $this->resolvedAt = new DateTimeImmutable();
        return $this;
    }
    
    public function isResolved(): bool
    {
        return $this->resolvedAt !== null;
    }
    
    public function isCritical(): bool
    {
        return $this->severity === HealthAlertSeverity::CRITICAL;
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->healthCheckId = (int) $row['HealthCheckId'];
        $entity->alertType = HealthAlertType::from($row['AlertType']);
        $entity->severity = HealthAlertSeverity::from($row['Severity']);
        $entity->message = $row['Message'];
        $entity->details = isset($row['Details']) ? json_decode($row['Details'], true) : null;
        $entity->contentId = isset($row['ContentId']) ? (int) $row['ContentId'] : null;
        $entity->contentType = $row['ContentType'] ?? null;
        $entity->acknowledged = (bool) ($row['Acknowledged'] ?? false);
        $entity->acknowledgedBy = $row['AcknowledgedBy'] ?? null;
        $entity->acknowledgedAt = self::parseDateTime($row['AcknowledgedAt'] ?? null);
        $entity->resolvedAt = self::parseDateTime($row['ResolvedAt'] ?? null);
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'HealthCheckId' => $this->healthCheckId,
            'AlertType' => $this->alertType->value,
            'Severity' => $this->severity->value,
            'Message' => $this->message,
            'Details' => $this->details ? json_encode($this->details) : null,
            'ContentId' => $this->contentId,
            'ContentType' => $this->contentType,
            'Acknowledged' => $this->acknowledged ? 1 : 0,
            'AcknowledgedBy' => $this->acknowledgedBy,
            'AcknowledgedAt' => self::formatDateTime($this->acknowledgedAt),
            'ResolvedAt' => self::formatDateTime($this->resolvedAt),
            'CreatedAt' => self::formatDateTime($this->createdAt),
        ];
    }
}
```

---

### HealthCheckJob Entity

**File:** `src/Entity/HealthCheckJob.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use LinkManager\Enums\ScanStatus;

class HealthCheckJob extends BaseEntity
{
    private ScanStatus $status;
    private int $totalLinks = 0;
    private int $processedLinks = 0;
    private int $healthyCount = 0;
    private int $brokenCount = 0;
    private int $slowCount = 0;
    private int $redirectCount = 0;
    private ?DateTimeImmutable $startedAt = null;
    private ?DateTimeImmutable $completedAt = null;
    private ?string $errorMessage = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->status = ScanStatus::PENDING;
    }
    
    public function getProgressPercentage(): float
    {
        if ($this->totalLinks === 0) {
            return 0.0;
        }
        return round(($this->processedLinks / $this->totalLinks) * 100, 1);
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->status = ScanStatus::from($row['Status'] ?? 'PENDING');
        $entity->totalLinks = (int) ($row['TotalLinks'] ?? 0);
        $entity->processedLinks = (int) ($row['ProcessedLinks'] ?? 0);
        $entity->healthyCount = (int) ($row['HealthyCount'] ?? 0);
        $entity->brokenCount = (int) ($row['BrokenCount'] ?? 0);
        $entity->slowCount = (int) ($row['SlowCount'] ?? 0);
        $entity->redirectCount = (int) ($row['RedirectCount'] ?? 0);
        $entity->startedAt = self::parseDateTime($row['StartedAt'] ?? null);
        $entity->completedAt = self::parseDateTime($row['CompletedAt'] ?? null);
        $entity->errorMessage = $row['ErrorMessage'] ?? null;
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'Status' => $this->status->value,
            'TotalLinks' => $this->totalLinks,
            'ProcessedLinks' => $this->processedLinks,
            'HealthyCount' => $this->healthyCount,
            'BrokenCount' => $this->brokenCount,
            'SlowCount' => $this->slowCount,
            'RedirectCount' => $this->redirectCount,
            'StartedAt' => self::formatDateTime($this->startedAt),
            'CompletedAt' => self::formatDateTime($this->completedAt),
            'ErrorMessage' => $this->errorMessage,
            'CreatedAt' => self::formatDateTime($this->createdAt),
        ];
    }
}
```

---

### HealthExclusion Entity

**File:** `src/Entity/HealthExclusion.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

class HealthExclusion extends BaseEntity
{
    private string $pattern;
    private string $patternType; // 'domain', 'url', 'regex'
    private ?string $reason = null;
    private ?string $createdBy = null;
    
    public function matches(string $url): bool
    {
        return match ($this->patternType) {
            'domain' => $this->matchesDomain($url),
            'url' => $this->matchesUrl($url),
            'regex' => $this->matchesRegex($url),
            default => false,
        };
    }
    
    private function matchesDomain(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        return $host && (str_contains($host, $this->pattern) || $host === $this->pattern);
    }
    
    private function matchesUrl(string $url): bool
    {
        return str_starts_with($url, $this->pattern) || $url === $this->pattern;
    }
    
    private function matchesRegex(string $url): bool
    {
        return (bool) preg_match($this->pattern, $url);
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->pattern = $row['Pattern'];
        $entity->patternType = $row['PatternType'];
        $entity->reason = $row['Reason'] ?? null;
        $entity->createdBy = $row['CreatedBy'] ?? null;
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'Pattern' => $this->pattern,
            'PatternType' => $this->patternType,
            'Reason' => $this->reason,
            'CreatedBy' => $this->createdBy,
            'CreatedAt' => self::formatDateTime($this->createdAt),
        ];
    }
}
```

---

## Notification Entities

### NotificationQueue Entity

**File:** `src/Entity/NotificationQueue.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use LinkManager\Enums\NotificationType;
use LinkManager\Enums\NotificationChannel;
use LinkManager\Enums\NotificationPriority;
use LinkManager\Enums\NotificationStatus;

class NotificationQueue extends BaseEntity
{
    private NotificationType $type;
    private NotificationChannel $channel;
    private NotificationPriority $priority;
    private NotificationStatus $status;
    private string $recipient;
    private ?string $subject = null;
    private array $payload = [];
    private int $attempts = 0;
    private ?DateTimeImmutable $lastAttemptAt = null;
    private ?string $lastError = null;
    private ?DateTimeImmutable $scheduledFor = null;
    private ?DateTimeImmutable $sentAt = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->priority = NotificationPriority::NORMAL;
        $this->status = NotificationStatus::PENDING;
    }
    
    public function isPending(): bool
    {
        return $this->status === NotificationStatus::PENDING;
    }
    
    public function canRetry(): bool
    {
        return $this->attempts < 3 && $this->status === NotificationStatus::FAILED;
    }
    
    public function markSent(): self
    {
        $this->status = NotificationStatus::SENT;
        $this->sentAt = new DateTimeImmutable();
        return $this;
    }
    
    public function markFailed(string $error): self
    {
        $this->status = NotificationStatus::FAILED;
        $this->lastError = $error;
        $this->lastAttemptAt = new DateTimeImmutable();
        $this->attempts++;
        return $this;
    }
    
    public function markRetrying(): self
    {
        $this->status = NotificationStatus::RETRYING;
        return $this;
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->type = NotificationType::from($row['Type']);
        $entity->channel = NotificationChannel::from($row['Channel']);
        $entity->priority = NotificationPriority::from($row['Priority'] ?? 'NORMAL');
        $entity->status = NotificationStatus::from($row['Status'] ?? 'PENDING');
        $entity->recipient = $row['Recipient'];
        $entity->subject = $row['Subject'] ?? null;
        $entity->payload = json_decode($row['Payload'] ?? '{}', true);
        $entity->attempts = (int) ($row['Attempts'] ?? 0);
        $entity->lastAttemptAt = self::parseDateTime($row['LastAttemptAt'] ?? null);
        $entity->lastError = $row['LastError'] ?? null;
        $entity->scheduledFor = self::parseDateTime($row['ScheduledFor'] ?? null);
        $entity->sentAt = self::parseDateTime($row['SentAt'] ?? null);
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'Type' => $this->type->value,
            'Channel' => $this->channel->value,
            'Priority' => $this->priority->value,
            'Status' => $this->status->value,
            'Recipient' => $this->recipient,
            'Subject' => $this->subject,
            'Payload' => json_encode($this->payload),
            'Attempts' => $this->attempts,
            'LastAttemptAt' => self::formatDateTime($this->lastAttemptAt),
            'LastError' => $this->lastError,
            'ScheduledFor' => self::formatDateTime($this->scheduledFor),
            'SentAt' => self::formatDateTime($this->sentAt),
            'CreatedAt' => self::formatDateTime($this->createdAt),
        ];
    }
}
```

---

### NotificationRecipient Entity

**File:** `src/Entity/NotificationRecipient.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

class NotificationRecipient extends BaseEntity
{
    private string $email;
    private ?string $name = null;
    private bool $isActive = true;
    private array $notificationTypes = [];
    private array $channels = [];
    private string $digestPreference = 'DAILY';
    
    public function isSubscribedTo(string $type): bool
    {
        return empty($this->notificationTypes) || in_array($type, $this->notificationTypes, true);
    }
    
    public function prefersChannel(string $channel): bool
    {
        return empty($this->channels) || in_array($channel, $this->channels, true);
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->email = $row['Email'];
        $entity->name = $row['Name'] ?? null;
        $entity->isActive = (bool) ($row['IsActive'] ?? true);
        $entity->notificationTypes = json_decode($row['NotificationTypes'] ?? '[]', true) ?? [];
        $entity->channels = json_decode($row['Channels'] ?? '[]', true) ?? [];
        $entity->digestPreference = $row['DigestPreference'] ?? 'DAILY';
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        $entity->updatedAt = self::parseDateTime($row['UpdatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'Email' => $this->email,
            'Name' => $this->name,
            'IsActive' => $this->isActive ? 1 : 0,
            'NotificationTypes' => json_encode($this->notificationTypes),
            'Channels' => json_encode($this->channels),
            'DigestPreference' => $this->digestPreference,
            'CreatedAt' => self::formatDateTime($this->createdAt),
            'UpdatedAt' => self::formatDateTime($this->updatedAt),
        ];
    }
}
```

---

### WebhookEndpoint Entity

**File:** `src/Entity/WebhookEndpoint.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use LinkManager\Enums\WebhookAuthType;

class WebhookEndpoint extends BaseEntity
{
    private string $name;
    private string $url;
    private WebhookAuthType $authType;
    private ?string $authSecret = null;
    private bool $isActive = true;
    private array $notificationTypes = [];
    private array $headers = [];
    private bool $retryEnabled = true;
    private ?DateTimeImmutable $lastSuccessAt = null;
    private ?DateTimeImmutable $lastFailureAt = null;
    private int $consecutiveFailures = 0;
    
    public function __construct()
    {
        parent::__construct();
        $this->authType = WebhookAuthType::NONE;
    }
    
    public function isSubscribedTo(string $type): bool
    {
        return empty($this->notificationTypes) || in_array($type, $this->notificationTypes, true);
    }
    
    public function recordSuccess(): self
    {
        $this->lastSuccessAt = new DateTimeImmutable();
        $this->consecutiveFailures = 0;
        return $this->touch();
    }
    
    public function recordFailure(): self
    {
        $this->lastFailureAt = new DateTimeImmutable();
        $this->consecutiveFailures++;
        return $this->touch();
    }
    
    public function shouldDisable(): bool
    {
        return $this->consecutiveFailures >= 10;
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->name = $row['Name'];
        $entity->url = $row['Url'];
        $entity->authType = WebhookAuthType::from($row['AuthType'] ?? 'NONE');
        $entity->authSecret = $row['AuthSecret'] ?? null;
        $entity->isActive = (bool) ($row['IsActive'] ?? true);
        $entity->notificationTypes = json_decode($row['NotificationTypes'] ?? '[]', true) ?? [];
        $entity->headers = json_decode($row['Headers'] ?? '{}', true) ?? [];
        $entity->retryEnabled = (bool) ($row['RetryEnabled'] ?? true);
        $entity->lastSuccessAt = self::parseDateTime($row['LastSuccessAt'] ?? null);
        $entity->lastFailureAt = self::parseDateTime($row['LastFailureAt'] ?? null);
        $entity->consecutiveFailures = (int) ($row['ConsecutiveFailures'] ?? 0);
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        $entity->updatedAt = self::parseDateTime($row['UpdatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'Name' => $this->name,
            'Url' => $this->url,
            'AuthType' => $this->authType->value,
            'AuthSecret' => $this->authSecret,
            'IsActive' => $this->isActive ? 1 : 0,
            'NotificationTypes' => json_encode($this->notificationTypes),
            'Headers' => json_encode($this->headers),
            'RetryEnabled' => $this->retryEnabled ? 1 : 0,
            'LastSuccessAt' => self::formatDateTime($this->lastSuccessAt),
            'LastFailureAt' => self::formatDateTime($this->lastFailureAt),
            'ConsecutiveFailures' => $this->consecutiveFailures,
            'CreatedAt' => self::formatDateTime($this->createdAt),
            'UpdatedAt' => self::formatDateTime($this->updatedAt),
        ];
    }
}
```

---

### NotificationLog Entity

**File:** `src/Entity/NotificationLog.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

class NotificationLog extends BaseEntity
{
    private ?int $notificationId = null;
    private string $channel;
    private string $recipient;
    private string $type;
    private string $status; // SENT, FAILED
    private ?int $responseCode = null;
    private ?string $responseBody = null;
    private ?int $durationMs = null;
    private ?string $errorMessage = null;
    
    public function isSuccess(): bool
    {
        return $this->status === 'SENT';
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->notificationId = isset($row['NotificationId']) ? (int) $row['NotificationId'] : null;
        $entity->channel = $row['Channel'];
        $entity->recipient = $row['Recipient'];
        $entity->type = $row['Type'];
        $entity->status = $row['Status'];
        $entity->responseCode = isset($row['ResponseCode']) ? (int) $row['ResponseCode'] : null;
        $entity->responseBody = $row['ResponseBody'] ?? null;
        $entity->durationMs = isset($row['DurationMs']) ? (int) $row['DurationMs'] : null;
        $entity->errorMessage = $row['ErrorMessage'] ?? null;
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'NotificationId' => $this->notificationId,
            'Channel' => $this->channel,
            'Recipient' => $this->recipient,
            'Type' => $this->type,
            'Status' => $this->status,
            'ResponseCode' => $this->responseCode,
            'ResponseBody' => $this->responseBody,
            'DurationMs' => $this->durationMs,
            'ErrorMessage' => $this->errorMessage,
            'CreatedAt' => self::formatDateTime($this->createdAt),
        ];
    }
}
```

---

### NotificationSettings Entity

**File:** `src/Entity/NotificationSettings.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

class NotificationSettings extends BaseEntity
{
    private string $settingKey;
    private string $settingValue;
    
    public function getValue(): mixed
    {
        // Auto-detect type
        if ($this->settingValue === 'true') return true;
        if ($this->settingValue === 'false') return false;
        if (is_numeric($this->settingValue)) {
            return str_contains($this->settingValue, '.') 
                ? (float) $this->settingValue 
                : (int) $this->settingValue;
        }
        return $this->settingValue;
    }
    
    public function setValue(mixed $value): self
    {
        $this->settingValue = is_bool($value) 
            ? ($value ? 'true' : 'false') 
            : (string) $value;
        return $this->touch();
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->settingKey = $row['SettingKey'];
        $entity->settingValue = $row['SettingValue'];
        $entity->updatedAt = self::parseDateTime($row['UpdatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'SettingKey' => $this->settingKey,
            'SettingValue' => $this->settingValue,
            'UpdatedAt' => self::formatDateTime($this->updatedAt),
        ];
    }
}
```

---

## History Database Entities

### ContentVersion Entity

**File:** `src/Entity/ContentVersion.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

class ContentVersion extends BaseEntity
{
    private int $versionNumber;
    private string $contentBefore;
    private string $contentAfter;
    private ?string $elementorDataBefore = null;
    private ?string $elementorDataAfter = null;
    private string $modificationType;
    private ?string $linkUrl = null;
    private ?string $anchorTextBefore = null;
    private ?string $anchorTextAfter = null;
    private ?int $modifiedBy = null;
    private bool $isRolledBack = false;
    private ?DateTimeImmutable $rolledBackAt = null;
    private ?int $rolledBackBy = null;
    private ?int $rolledBackToVersion = null;
    
    public function getDiff(): array
    {
        // Simple diff - could be enhanced with proper diff algorithm
        return [
            'before' => $this->contentBefore,
            'after' => $this->contentAfter,
            'type' => $this->modificationType,
        ];
    }
    
    public function markRolledBack(int $userId, int $toVersion): self
    {
        $this->isRolledBack = true;
        $this->rolledBackAt = new DateTimeImmutable();
        $this->rolledBackBy = $userId;
        $this->rolledBackToVersion = $toVersion;
        return $this;
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->versionNumber = (int) $row['VersionNumber'];
        $entity->contentBefore = $row['ContentBefore'];
        $entity->contentAfter = $row['ContentAfter'];
        $entity->elementorDataBefore = $row['ElementorDataBefore'] ?? null;
        $entity->elementorDataAfter = $row['ElementorDataAfter'] ?? null;
        $entity->modificationType = $row['ModificationType'];
        $entity->linkUrl = $row['LinkUrl'] ?? null;
        $entity->anchorTextBefore = $row['AnchorTextBefore'] ?? null;
        $entity->anchorTextAfter = $row['AnchorTextAfter'] ?? null;
        $entity->modifiedBy = isset($row['ModifiedBy']) ? (int) $row['ModifiedBy'] : null;
        $entity->isRolledBack = (bool) ($row['IsRolledBack'] ?? false);
        $entity->rolledBackAt = self::parseDateTime($row['RolledBackAt'] ?? null);
        $entity->rolledBackBy = isset($row['RolledBackBy']) ? (int) $row['RolledBackBy'] : null;
        $entity->rolledBackToVersion = isset($row['RolledBackToVersion']) ? (int) $row['RolledBackToVersion'] : null;
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'VersionNumber' => $this->versionNumber,
            'ContentBefore' => $this->contentBefore,
            'ContentAfter' => $this->contentAfter,
            'ElementorDataBefore' => $this->elementorDataBefore,
            'ElementorDataAfter' => $this->elementorDataAfter,
            'ModificationType' => $this->modificationType,
            'LinkUrl' => $this->linkUrl,
            'AnchorTextBefore' => $this->anchorTextBefore,
            'AnchorTextAfter' => $this->anchorTextAfter,
            'ModifiedBy' => $this->modifiedBy,
            'IsRolledBack' => $this->isRolledBack ? 1 : 0,
            'RolledBackAt' => self::formatDateTime($this->rolledBackAt),
            'RolledBackBy' => $this->rolledBackBy,
            'RolledBackToVersion' => $this->rolledBackToVersion,
            'CreatedAt' => self::formatDateTime($this->createdAt),
        ];
    }
}
```

---

### ModificationLog Entity

**File:** `src/Entity/ModificationLog.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

class ModificationLog extends BaseEntity
{
    private int $contentVersionId;
    private string $modificationType;
    private ?string $targetSelector = null;
    private ?string $valueBefore = null;
    private ?string $valueAfter = null;
    private ?array $wrapperTagsRemoved = null;
    private ?array $attributesModified = null;
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->contentVersionId = (int) $row['ContentVersionId'];
        $entity->modificationType = $row['ModificationType'];
        $entity->targetSelector = $row['TargetSelector'] ?? null;
        $entity->valueBefore = $row['ValueBefore'] ?? null;
        $entity->valueAfter = $row['ValueAfter'] ?? null;
        $entity->wrapperTagsRemoved = isset($row['WrapperTagsRemoved']) 
            ? json_decode($row['WrapperTagsRemoved'], true) 
            : null;
        $entity->attributesModified = isset($row['AttributesModified']) 
            ? json_decode($row['AttributesModified'], true) 
            : null;
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'ContentVersionId' => $this->contentVersionId,
            'ModificationType' => $this->modificationType,
            'TargetSelector' => $this->targetSelector,
            'ValueBefore' => $this->valueBefore,
            'ValueAfter' => $this->valueAfter,
            'WrapperTagsRemoved' => $this->wrapperTagsRemoved 
                ? json_encode($this->wrapperTagsRemoved) 
                : null,
            'AttributesModified' => $this->attributesModified 
                ? json_encode($this->attributesModified) 
                : null,
            'CreatedAt' => self::formatDateTime($this->createdAt),
        ];
    }
}
```

---

## Yoast SEO Entities

### YoastSettings Entity

**File:** `src/Entity/YoastSettings.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use DateTimeImmutable;

class YoastSettings extends BaseEntity
{
    private string $settingKey;
    private string $settingValue;
    private string $settingType = 'string';
    private ?string $description = null;
    private bool $isUserModified = false;
    private ?string $seedVersion = null;
    
    public function getSettingKey(): string
    {
        return $this->settingKey;
    }
    
    public function setSettingKey(string $settingKey): self
    {
        $this->settingKey = $settingKey;
        return $this;
    }
    
    public function getSettingValue(): string
    {
        return $this->settingValue;
    }
    
    public function setSettingValue(mixed $value): self
    {
        $this->settingValue = is_array($value) || is_object($value) 
            ? json_encode($value) 
            : (string) $value;
        return $this->touch();
    }
    
    public function getSettingType(): string
    {
        return $this->settingType;
    }
    
    public function setSettingType(string $type): self
    {
        $this->settingType = $type;
        return $this;
    }
    
    /**
     * Get typed value based on settingType
     */
    public function getValue(): mixed
    {
        return match($this->settingType) {
            'bool', 'boolean' => filter_var($this->settingValue, FILTER_VALIDATE_BOOLEAN),
            'int', 'integer' => (int) $this->settingValue,
            'float', 'double' => (float) $this->settingValue,
            'array', 'json' => json_decode($this->settingValue, true) ?? [],
            default => $this->settingValue
        };
    }
    
    public function isUserModified(): bool
    {
        return $this->isUserModified;
    }
    
    public function markAsUserModified(): self
    {
        $this->isUserModified = true;
        return $this->touch();
    }
    
    public function getSeedVersion(): ?string
    {
        return $this->seedVersion;
    }
    
    public function setSeedVersion(string $version): self
    {
        $this->seedVersion = $version;
        return $this;
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->settingKey = $row['SettingKey'];
        $entity->settingValue = $row['SettingValue'];
        $entity->settingType = $row['SettingType'] ?? 'string';
        $entity->description = $row['Description'] ?? null;
        $entity->isUserModified = (bool) ($row['IsUserModified'] ?? false);
        $entity->seedVersion = $row['SeedVersion'] ?? null;
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        $entity->updatedAt = self::parseDateTime($row['UpdatedAt']) ?? new DateTimeImmutable();
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'SettingKey' => $this->settingKey,
            'SettingValue' => $this->settingValue,
            'SettingType' => $this->settingType,
            'Description' => $this->description,
            'IsUserModified' => $this->isUserModified ? 1 : 0,
            'SeedVersion' => $this->seedVersion,
            'CreatedAt' => self::formatDateTime($this->createdAt),
            'UpdatedAt' => self::formatDateTime($this->updatedAt),
        ];
    }
}
```

---

### YoastAuditLog Entity

**File:** `src/Entity/YoastAuditLog.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use DateTimeImmutable;

class YoastAuditLog extends BaseEntity
{
    private int $wpPostId;
    private string $postType;
    private string $actionType;
    private string $fieldModified;
    private ?string $oldValue = null;
    private ?string $newValue = null;
    private bool $autoGenerated = true;
    
    public function getWpPostId(): int
    {
        return $this->wpPostId;
    }
    
    public function setWpPostId(int $wpPostId): self
    {
        $this->wpPostId = $wpPostId;
        return $this;
    }
    
    public function getPostType(): string
    {
        return $this->postType;
    }
    
    public function setPostType(string $postType): self
    {
        $this->postType = $postType;
        return $this;
    }
    
    public function getActionType(): string
    {
        return $this->actionType;
    }
    
    public function setActionType(string $actionType): self
    {
        $this->actionType = $actionType;
        return $this;
    }
    
    public function getFieldModified(): string
    {
        return $this->fieldModified;
    }
    
    public function setFieldModified(string $fieldModified): self
    {
        $this->fieldModified = $fieldModified;
        return $this;
    }
    
    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }
    
    public function setOldValue(?string $oldValue): self
    {
        $this->oldValue = $oldValue;
        return $this;
    }
    
    public function getNewValue(): ?string
    {
        return $this->newValue;
    }
    
    public function setNewValue(?string $newValue): self
    {
        $this->newValue = $newValue;
        return $this;
    }
    
    public function isAutoGenerated(): bool
    {
        return $this->autoGenerated;
    }
    
    public function setAutoGenerated(bool $autoGenerated): self
    {
        $this->autoGenerated = $autoGenerated;
        return $this;
    }
    
    /**
     * Check if this change can be reverted
     */
    public function canRevert(): bool
    {
        return $this->oldValue !== null || $this->newValue !== null;
    }
    
    /**
     * Create a revert entry from this audit log
     */
    public function createRevertEntry(): self
    {
        $revert = new static();
        $revert->wpPostId = $this->wpPostId;
        $revert->postType = $this->postType;
        $revert->actionType = $this->actionType;
        $revert->fieldModified = $this->fieldModified;
        $revert->oldValue = $this->newValue;
        $revert->newValue = $this->oldValue;
        $revert->autoGenerated = false;
        return $revert;
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->wpPostId = (int) $row['WpPostId'];
        $entity->postType = $row['PostType'];
        $entity->actionType = $row['ActionType'];
        $entity->fieldModified = $row['FieldModified'];
        $entity->oldValue = $row['OldValue'] ?? null;
        $entity->newValue = $row['NewValue'] ?? null;
        $entity->autoGenerated = (bool) ($row['AutoGenerated'] ?? true);
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        // Note: YoastAuditLog only has CreatedAt, not UpdatedAt
        $entity->updatedAt = $entity->createdAt;
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'WpPostId' => $this->wpPostId,
            'PostType' => $this->postType,
            'ActionType' => $this->actionType,
            'FieldModified' => $this->fieldModified,
            'OldValue' => $this->oldValue,
            'NewValue' => $this->newValue,
            'AutoGenerated' => $this->autoGenerated ? 1 : 0,
            'CreatedAt' => self::formatDateTime($this->createdAt),
        ];
    }
}
```

---

### YoastOptimizationQueue Entity

**File:** `src/Entity/YoastOptimizationQueue.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Entity;

use DateTimeImmutable;
use LinkManager\Enums\YoastOptimizationType;
use LinkManager\Enums\YoastOptimizationStatus;

class YoastOptimizationQueue extends BaseEntity
{
    private int $wpPostId;
    private string $postType;
    private YoastOptimizationType $optimizationType;
    private YoastOptimizationStatus $status;
    private int $priority = 0;
    private ?DateTimeImmutable $scheduledAt = null;
    private ?DateTimeImmutable $processedAt = null;
    private ?string $errorMessage = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->status = YoastOptimizationStatus::PENDING;
        $this->optimizationType = YoastOptimizationType::FOCUS_KEYWORD;
    }
    
    public function getWpPostId(): int
    {
        return $this->wpPostId;
    }
    
    public function setWpPostId(int $wpPostId): self
    {
        $this->wpPostId = $wpPostId;
        return $this;
    }
    
    public function getPostType(): string
    {
        return $this->postType;
    }
    
    public function setPostType(string $postType): self
    {
        $this->postType = $postType;
        return $this;
    }
    
    public function getOptimizationType(): YoastOptimizationType
    {
        return $this->optimizationType;
    }
    
    public function setOptimizationType(YoastOptimizationType $type): self
    {
        $this->optimizationType = $type;
        return $this;
    }
    
    public function getStatus(): YoastOptimizationStatus
    {
        return $this->status;
    }
    
    public function setStatus(YoastOptimizationStatus $status): self
    {
        $this->status = $status;
        return $this->touch();
    }
    
    public function getPriority(): int
    {
        return $this->priority;
    }
    
    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }
    
    public function isPending(): bool
    {
        return $this->status === YoastOptimizationStatus::PENDING;
    }
    
    public function isProcessing(): bool
    {
        return $this->status === YoastOptimizationStatus::PROCESSING;
    }
    
    public function isCompleted(): bool
    {
        return $this->status === YoastOptimizationStatus::COMPLETED;
    }
    
    public function isFailed(): bool
    {
        return $this->status === YoastOptimizationStatus::FAILED;
    }
    
    public function markAsProcessing(): self
    {
        $this->status = YoastOptimizationStatus::PROCESSING;
        return $this->touch();
    }
    
    public function markAsCompleted(): self
    {
        $this->status = YoastOptimizationStatus::COMPLETED;
        $this->processedAt = new DateTimeImmutable();
        $this->errorMessage = null;
        return $this->touch();
    }
    
    public function markAsFailed(string $errorMessage): self
    {
        $this->status = YoastOptimizationStatus::FAILED;
        $this->processedAt = new DateTimeImmutable();
        $this->errorMessage = $errorMessage;
        return $this->touch();
    }
    
    public function markAsCancelled(): self
    {
        $this->status = YoastOptimizationStatus::CANCELLED;
        return $this->touch();
    }
    
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
    
    public function getScheduledAt(): ?DateTimeImmutable
    {
        return $this->scheduledAt;
    }
    
    public function setScheduledAt(?DateTimeImmutable $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;
        return $this;
    }
    
    public function getProcessedAt(): ?DateTimeImmutable
    {
        return $this->processedAt;
    }
    
    public static function fromRow(array $row): static
    {
        $entity = new static();
        $entity->id = (int) $row['Id'];
        $entity->wpPostId = (int) $row['WpPostId'];
        $entity->postType = $row['PostType'];
        $entity->optimizationType = YoastOptimizationType::from($row['OptimizationType']);
        $entity->status = YoastOptimizationStatus::from($row['Status'] ?? 'PENDING');
        $entity->priority = (int) ($row['Priority'] ?? 0);
        $entity->scheduledAt = self::parseDateTime($row['ScheduledAt'] ?? null);
        $entity->processedAt = self::parseDateTime($row['ProcessedAt'] ?? null);
        $entity->errorMessage = $row['ErrorMessage'] ?? null;
        $entity->createdAt = self::parseDateTime($row['CreatedAt']) ?? new DateTimeImmutable();
        $entity->updatedAt = $entity->createdAt;
        return $entity;
    }
    
    public function toRow(): array
    {
        return [
            'Id' => $this->id,
            'WpPostId' => $this->wpPostId,
            'PostType' => $this->postType,
            'OptimizationType' => $this->optimizationType->value,
            'Status' => $this->status->value,
            'Priority' => $this->priority,
            'ScheduledAt' => self::formatDateTime($this->scheduledAt),
            'ProcessedAt' => self::formatDateTime($this->processedAt),
            'ErrorMessage' => $this->errorMessage,
            'CreatedAt' => self::formatDateTime($this->createdAt),
        ];
    }
}
```

---

## Entity Summary

| # | Entity | Table | Category |
|---|--------|-------|----------|
| 1 | `Post` | Post | Core Content |
| 2 | `Page` | Page | Core Content |
| 3 | `Category` | Category | Core Content |
| 4 | `Link` | Link | Core Content |
| 5 | `ScanHistory` | ScanHistory | Scan & History |
| 6 | `Snapshot` | Snapshot | Scan & History |
| 7 | `Settings` | Settings | Scan & History |
| 8 | `ScanJob` | ScanJobs | Cron Jobs |
| 9 | `JobQueue` | JobQueue | Cron Jobs |
| 10 | `LinkTarget` | LinkTarget | Internal Linking |
| 11 | `LinkTemplate` | LinkTemplate | Internal Linking |
| 12 | `LinkVariable` | LinkVariable | Internal Linking |
| 13 | `InternalLink` | InternalLink | Internal Linking |
| 14 | `AutoLinkJob` | AutoLinkJobs | Auto-Link Cron |
| 15 | `AutoLinkQueue` | AutoLinkQueue | Auto-Link Cron |
| 16 | `AutoLinkSchedule` | AutoLinkSchedules | Auto-Link Cron |
| 17 | `LinkHealthCheck` | LinkHealthChecks | Health Monitor |
| 18 | `HealthAlert` | HealthAlerts | Health Monitor |
| 19 | `HealthCheckJob` | HealthCheckJobs | Health Monitor |
| 20 | `HealthExclusion` | HealthExclusions | Health Monitor |
| 21 | `NotificationQueue` | NotificationQueue | Notifications |
| 22 | `NotificationRecipient` | NotificationRecipients | Notifications |
| 23 | `WebhookEndpoint` | WebhookEndpoints | Notifications |
| 24 | `NotificationLog` | NotificationLog | Notifications |
| 25 | `NotificationSettings` | NotificationSettings | Notifications |
| 26 | `YoastSettings` | YoastSettings | Yoast SEO |
| 27 | `YoastAuditLog` | YoastAuditLog | Yoast SEO |
| 28 | `YoastOptimizationQueue` | YoastOptimizationQueue | Yoast SEO |
| 29 | `ContentVersion` | ContentVersion | History DB |
| 30 | `ModificationLog` | ModificationLog | History DB |

---

## Acceptance Criteria

- [ ] All 30 entity classes implement `fromRow()` and `toRow()` methods
- [ ] BaseEntity provides common functionality (id, timestamps, serialization)
- [ ] Enum types properly mapped from database string values
- [ ] JSON columns decoded/encoded correctly
- [ ] DateTime parsing handles null values gracefully
- [ ] Business logic methods encapsulated in entities
- [ ] Type hints on all properties and methods
- [ ] Immutable DateTimeImmutable used for all dates
- [ ] YoastSettings supports typed value retrieval via `getValue()`
- [ ] YoastAuditLog supports revert entry creation
- [ ] YoastOptimizationQueue uses proper enum types

---

## Related Specifications

- `04-database-schema.md` - Table definitions
- `66-shared-constants.md` - Enum definitions
- `09-scan-service.md` - Entity usage in scanning
- `24-notification-service.md` - Notification entity usage
- `27-yoast-seo-integration.md` - Yoast entity usage
