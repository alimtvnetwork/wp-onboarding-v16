# Memory: architecture/php/model-factory-standard
Updated: 2026-02-19

## Overview

PHP models use `final readonly class` with a static `fromRow(array $row, ...)` factory method as the canonical mapper for `TypedQuery` operations. This replaces inline closure mappers with reusable, typed constructors.

## Pattern

```php
final readonly class AgentSite {
    public function __construct(
        public int $id,
        public string $name,
        public string $url,
        // ... typed properties
    ) {}

    public static function fromRow(array $row, ?string $decryptedPassword = null): self {
        return new self(
            id:   (int) $row['id'],
            name: $row['name'],
            url:  $row['url'],
            // ...
        );
    }

    public function toArray(): array { /* backward-compat REST output */ }
}
```

## Usage with TypedQuery

- **Single row**: `$query->queryOne($sql, $params, fn(array $row): AgentSite => AgentSite::fromRow($row))`
- **Multiple rows**: `$query->queryMany($sql, $params, AgentSite::fromRow(...))`

## Consuming Models

Traits and services accept the model type (`AgentSite`) instead of raw `array`, accessing typed properties (`$agent->url`) instead of array keys (`$agent['url']`).

## Migration Approach

When a method must remain backward-compatible (e.g., REST handlers expecting arrays), provide both:
- `getAgentModel(int $id): ?AgentSite` — returns the typed model
- `getAgent(int $id): ?array` — delegates to `getAgentModel()` and calls `->toArray()`
