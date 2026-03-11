# Memory: architecture/php/namespace-import-incident-v1580
Updated: 2026-02-20

## Incident: v1.58.0 Critical Site Crash

Two missing `use` import statements caused a full WordPress site crash ("critical error on this website"):

1. **PluginRoutesTrait.php** — `use SnapshotRouteRegistrationTrait;` in the trait body without a file-level `use RiseupAsia\Traits\Snapshot\SnapshotRouteRegistrationTrait;`. PHP resolved it in the current namespace (`RiseupAsia\Traits\Plugin\`), file not found → fatal error at compile time.

2. **ActivationHandler.php** — Referenced `PluginConfigType` enum without `use RiseupAsia\Enums\PluginConfigType;`. Worked with warm OPcache but crashed on cold activation.

## Root Cause

PHP traits resolve `use TraitName;` statements at compile time. If the trait is in a different namespace and no file-level `use` import exists, PHP looks in the current file's namespace → autoloader maps to wrong path → fatal error before any error handler can run.

## Key Lesson

Every PHP file must explicitly import every cross-namespace symbol it references. Traits do NOT inherit imports from the class that mixes them in. OPcache can mask missing imports until it's flushed, creating time-bomb failures in production.

## Fix Applied

Added the missing `use` imports to both files. Bumped version to 1.59.0. Updated spec/06-php-standards/forbidden-patterns.md with section 5A covering this exact failure mode, dos/don'ts, and pre-release checklist items.
