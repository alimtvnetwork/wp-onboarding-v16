# Enum Info-Object Pattern

**Version:** 1.1.0
**Status:** Complete
**Updated:** 2026-04-09

---

## Purpose

Define the **preferred pattern** for attaching structured metadata to enum cases. This replaces scattered `match`/`switch` statements with a centralised map-based lookup that returns an info object.

This pattern applies to **any language** — PHP, Go, TypeScript. Language-specific syntax differs, but the design principle is universal.

---

## Problem

When an enum needs labels or other metadata, the naive approach duplicates logic:

### ❌ Anti-Pattern — Separate Match per Field

```php
enum StatusType: string
{
    case Success = 'Success';
    case Failed  = 'Failed';

    // ❌ One match for label
    public function label(): string
    {
        return match ($this) {
            self::Success => 'Operation succeeded',
            self::Failed  => 'Operation failed',
        };
    }

    // ❌ Another match for icon — duplicated branching
    public function icon(): string
    {
        return match ($this) {
            self::Success => '✅',
            self::Failed  => '❌',
        };
    }
}
```

**Problems:**
- Every new metadata field requires a new `match` block
- Adding a new enum case requires updating N separate methods
- Metadata is scattered, not centralised

---

## Solution — Info Object with Map Lookup

### Step 1: Define the Info Class

A simple, immutable value object holding metadata for one enum case. **Only `label` is required** — keep it minimal.

```php
namespace PluginName\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * EnumInfo — Immutable metadata value object for enum cases.
 *
 * @package PluginName\Enums
 * @since   1.0.0
 */
final readonly class EnumInfo
{
    public function __construct(
        public string $label,
    ) {}
}
```

> **Extensibility:** If a future enum genuinely needs additional fields (e.g. `icon`, `cssClass`), add them with defaults. But start with `label` only — most enums don't need more.

### Step 2: Define the Map

Inside the enum, create a `private static` method that returns an associative array mapping enum values to their info objects. **Use a `static` local variable** so the array is constructed only once per request:

```php
enum SomeStatusType: string
{
    case Active   = 'Active';
    case Inactive = 'Inactive';
    case Archived = 'Archived';

    /**
     * Centralised metadata map — single source of truth.
     * Built once per request via static local variable.
     *
     * @return array<string, EnumInfo>
     */
    private static function infoMap(): array
    {
        static $map = null;

        if ($map === null) {
            $map = [
                self::Active->value   => new EnumInfo(label: 'Currently active'),
                self::Inactive->value => new EnumInfo(label: 'Inactive'),
                self::Archived->value => new EnumInfo(label: 'Archived'),
            ];
        }

        return $map;
    }
}
```

### Step 3: Expose `info()` Method

```php
    /**
     * Get the structured metadata for this enum case.
     */
    public function info(): EnumInfo
    {
        return self::infoMap()[$this->value];
    }
```

### Step 4: Delegate `label()` to `info()`

```php
    /**
     * Human-readable label — delegates to info object.
     */
    public function label(): string
    {
        return $this->info()->label;
    }
```

---

## Resolution Flow

```
┌──────────────┐     ┌──────────────────┐     ┌──────────────┐
│  Enum Value   │────▶│  info()           │────▶│  EnumInfo     │
│  (e.g. Active)│     │  Lookup in map    │     │  .label       │
└──────────────┘     └──────────────────┘     └──────────────┘
                                                      │
                      ┌──────────────────┐            │
                      │  label()          │◀───────────┘
                      │  return info.label│
                      └──────────────────┘
```

**Key principle:** `label()` never contains its own logic. It always calls `info()` and reads `->label`.

---

## Individual `is*()` Methods — Per-Case Helpers

For enums where callers frequently check specific cases, **add an `is*()` method for every case**. This makes code more readable and avoids raw comparison calls scattered through the codebase.

### Rule: Every case gets its own `is*()` method

```php
enum SomeStatusType: string
{
    case Active   = 'Active';
    case Inactive = 'Inactive';
    case Archived = 'Archived';

    // ── Per-Case Helpers ────────────────────────────────────
    public function isActive(): bool   { return $this->isEqual(self::Active); }
    public function isInactive(): bool { return $this->isEqual(self::Inactive); }
    public function isArchived(): bool { return $this->isEqual(self::Archived); }

    // ── Group Helpers ───────────────────────────────────────
    public function isDisabled(): bool
    {
        return $this->isAnyOf(self::Inactive, self::Archived);
    }

    // ── Standard Comparison Methods ─────────────────────────
    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
```

### Why

| Without `is*()` | With `is*()` |
|-----------------|-------------|
| `$status->isEqual(SomeStatusType::Active)` | `$status->isActive()` |
| `$status->isAnyOf(StatusType::Inactive, StatusType::Archived)` | `$status->isDisabled()` |

The per-case methods make domain logic read naturally and remove the need for callers to import and reference enum cases.

### When to add group helpers

Group helpers (`isDisabled()`, `isRollbackReason()`, `isLifecycle()`) should be added when:
- Multiple cases share a **domain concept** (e.g. "these 3 cases all mean the item is not active")
- The combination appears in **2+ call sites**
- The prefix-based shortcut (`str_starts_with`) is applicable for enums with consistent naming

---

## Complete PHP Example

```php
namespace PluginName\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum SomeStatusType: string
{
    case Active   = 'Active';
    case Inactive = 'Inactive';
    case Archived = 'Archived';

    /**
     * @return array<string, EnumInfo>
     */
    private static function infoMap(): array
    {
        static $map = null;

        if ($map === null) {
            $map = [
                self::Active->value   => new EnumInfo(label: 'Currently active'),
                self::Inactive->value => new EnumInfo(label: 'Inactive'),
                self::Archived->value => new EnumInfo(label: 'Archived'),
            ];
        }

        return $map;
    }

    public function info(): EnumInfo
    {
        return self::infoMap()[$this->value];
    }

    public function label(): string
    {
        return $this->info()->label;
    }

    // ── Per-Case Helpers ────────────────────────────────────
    public function isActive(): bool   { return $this->isEqual(self::Active); }
    public function isInactive(): bool { return $this->isEqual(self::Inactive); }
    public function isArchived(): bool { return $this->isEqual(self::Archived); }

    // ── Group Helpers ───────────────────────────────────────
    public function isDisabled(): bool
    {
        return $this->isAnyOf(self::Inactive, self::Archived);
    }

    // ── Standard Comparison Methods ─────────────────────────
    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
```

---

## When to Use This Pattern

| Scenario | Use Info-Object? | Why |
|----------|-----------------|-----|
| Enum has only `label()` | Optional | Simple `match` is acceptable for ≤5 cases |
| Enum has 2+ metadata fields | ✅ Yes | Avoids duplicate match blocks |
| Enum has 10+ cases | ✅ Mandatory | Map is easier to audit than long match chains |
| Config enums (int-backed values) | ❌ No | No metadata needed — just `->value` |

---

## Rules

### R1: Map is the Single Source of Truth

All metadata for an enum case lives in **one place** — the info map entry. No metadata may be defined outside the map via separate `match`/`switch` blocks.

### R2: `label()` Delegates — Never Contains Logic

```php
// ✅ Correct
public function label(): string { return $this->info()->label; }

// ❌ Forbidden — duplicates the map
public function label(): string { return match ($this) { ... }; }
```

### R3: Every Case Must Have a Map Entry

If the enum uses the info-object pattern, every case must have a corresponding entry in `infoMap()`. Missing entries cause runtime errors.

### R4: Static Caching is Mandatory

`infoMap()` must use a `static` local variable so the array and all `EnumInfo` instances are constructed **once per request**, not on every call.

```php
// ✅ Correct — built once, reused
private static function infoMap(): array
{
    static $map = null;

    if ($map === null) {
        $map = [ /* ... */ ];
    }

    return $map;
}

// ❌ Forbidden — rebuilds on every call
private static function infoMap(): array
{
    return [ /* ... */ ];
}
```

### R5: Info Class is `readonly`

The `EnumInfo` class must be `final readonly` to prevent mutation after construction.

### R6: Per-Case `is*()` Methods

Every enum should provide individual `is*()` methods for each case. Group helpers should be added when a domain concept spans multiple cases.

---

## Cross-Language Application

| Language | Info Type | Map Structure | Lookup |
|----------|----------|---------------|--------|
| PHP 8.1+ | `readonly class EnumInfo` | `array<string, EnumInfo>` | `$map[$this->value]` |
| Go | `struct EnumInfo` | `map[Variant]EnumInfo` | `infoMap[v]` |
| TypeScript | `interface EnumInfo` | `Record<EnumValue, EnumInfo>` | `infoMap[value]` |

See [Go Info-Object Pattern](../../06-golang-standards/01-enum-specification/05-info-object-pattern.md) for the Go-specific implementation.

---

## Cross-References

- [01-enum-architecture.md](01-enum-architecture.md) — core enum structure and comparison methods
- [03-self-update-status-enum.md](03-self-update-status-enum.md) — reference impl (17 cases, deployment domain)
- [04-action-type-enum.md](04-action-type-enum.md) — reference impl (40+ cases, transaction logging)
- [Go Info-Object Pattern](../../06-golang-standards/01-enum-specification/05-info-object-pattern.md)

---

*Centralised enum metadata pattern — applicable across languages.*
