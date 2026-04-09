# Enum Info-Object Pattern

**Version:** 1.0.0
**Status:** Complete
**Updated:** 2026-04-09

---

## Purpose

Define the **preferred pattern** for attaching structured metadata to enum cases. This replaces scattered `match`/`switch` statements with a centralised map-based lookup that returns an info object.

This pattern applies to **any language** — PHP, Go, TypeScript. Language-specific syntax differs, but the design principle is universal.

---

## Problem

When an enum needs labels, descriptions, icons, or other metadata, the naive approach duplicates logic:

### ❌ Anti-Pattern — Separate Switch/Match per Field

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

    // ❌ Yet another match for CSS class
    public function cssClass(): string
    {
        return match ($this) {
            self::Success => 'text-green-600',
            self::Failed  => 'text-red-600',
        };
    }
}
```

**Problems:**
- Every new metadata field requires a new `match` block
- Adding a new enum case requires updating N separate methods
- Metadata is scattered, not centralised
- High risk of forgetting a case in one of the match blocks

---

## Solution — Info Object with Map Lookup

### Step 1: Define the Info Class

Create a simple value object to hold all metadata for one enum case:

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
        public string $details = '',
    ) {}
}
```

> **Extensibility:** Add fields as needed — `icon`, `cssClass`, `severity`, `sortOrder`, `isTerminal`, etc. The class is `readonly` to prevent mutation after construction.

### Step 2: Define the Map

Inside the enum, create a `private static` method that returns an associative array mapping enum values to their info objects. **Use a `static` local variable** so the array and its `EnumInfo` instances are constructed only once per request:

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
                self::Active->value   => new EnumInfo(
                    label: 'Currently active',
                    details: 'This item is live and visible to users.',
                ),
                self::Inactive->value => new EnumInfo(
                    label: 'Inactive',
                    details: 'This item is disabled but can be re-activated.',
                ),
                self::Archived->value => new EnumInfo(
                    label: 'Archived',
                    details: 'This item has been archived and is read-only.',
                ),
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
│  Enum Value   │────▶│  info()           │────▶│  Info Object  │
│  (e.g. Active)│     │  Lookup in map    │     │  .label       │
└──────────────┘     └──────────────────┘     │  .details     │
                                               │  .icon (opt)  │
                                               └──────────────┘
                                                      │
                      ┌──────────────────┐            │
                      │  label()          │◀───────────┘
                      │  return info.label│
                      └──────────────────┘
```

**Key principle:** `label()` never contains its own logic. It always calls `info()` and reads `->label`.

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
                self::Active->value   => new EnumInfo(
                    label: 'Currently active',
                    details: 'This item is live and visible.',
                ),
                self::Inactive->value => new EnumInfo(
                    label: 'Inactive',
                    details: 'Disabled but can be re-activated.',
                ),
                self::Archived->value => new EnumInfo(
                    label: 'Archived',
                    details: 'Read-only, cannot be modified.',
                ),
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

    // Standard comparison methods (always required)
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
| Enum has `label()` + `details()` | ✅ Yes | Avoids duplicate match blocks |
| Enum has 3+ metadata fields | ✅ Mandatory | Scattered matches become unmaintainable |
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

### R4: Info Class is `readonly`

The `EnumInfo` class (or equivalent) must be `readonly` to prevent accidental mutation.

### R5: Extending the Info Object

When a new metadata field is needed:

1. Add the field to `EnumInfo` (with a default value for backward compatibility)
2. Update relevant `infoMap()` entries
3. Add accessor method on the enum if needed (delegate to `info()->newField`)

---

## Anti-Patterns

### ❌ Switch Statement for Metadata

```php
// FORBIDDEN — use map instead
public function label(): string
{
    return match ($this) {
        self::Success => 'Completed',
        self::Failed  => 'Failed',
    };
}
```

### ❌ External Metadata Maps

```php
// FORBIDDEN — metadata belongs inside the enum
$labels = [
    SomeStatusType::Active->value   => 'Active',
    SomeStatusType::Inactive->value => 'Inactive',
];
```

### ❌ Separate Methods Without Info Delegation

```php
// FORBIDDEN — scattered logic
public function label(): string { return match ($this) { ... }; }
public function icon(): string  { return match ($this) { ... }; }  // Duplicate branching
```

---

## Cross-Language Application

This pattern is universal. The design principle stays the same — only syntax changes.

| Language | Info Type | Map Structure | Lookup |
|----------|----------|---------------|--------|
| PHP 8.1+ | `readonly class EnumInfo` | `array<string, EnumInfo>` | `$map[$this->value]` |
| Go | `struct EnumInfo` | `map[Variant]EnumInfo` or `[...]EnumInfo` array | `infoMap[v]` |
| TypeScript | `interface EnumInfo` | `Record<EnumValue, EnumInfo>` | `infoMap[value]` |

See [Go Info-Object Pattern](../../06-golang-standards/01-enum-specification/05-info-object-pattern.md) for the Go-specific implementation.

---

## Cross-References

- [01-enum-architecture.md](01-enum-architecture.md) — core enum structure and comparison methods
- [03-self-update-status-enum.md](03-self-update-status-enum.md) — real-world example using this pattern
- [Go Info-Object Pattern](../../06-golang-standards/01-enum-specification/05-info-object-pattern.md)

---

*Centralised enum metadata pattern — applicable across languages.*
