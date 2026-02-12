# Memory: architecture/php/coding-standards-semantic-and-safety
Updated: 2026-02-12

PHP development follows strict semantic logic and safety standards across four pillars:

## 1. Boolean Logic
Boolean checks must use semantic inverse methods (e.g., `$plugin->is_disabled()`) rather than negating positive checks or using wrapper helpers like `RiseupBooleanHelpers`. Variables use descriptive prefixes (`$is_value`, `$has_permission`).

## 2. File Path Resolution
Manual file path construction is prohibited. All paths use fully-typed accessors in `RiseupPathUtils` that internally compose directory methods + `PathEnum` constants. Callers never see the composition — only `RiseupPathUtils::getRootDb()`, never `getDataDir() . PathEnum::ROOT_DB`.

## 3. Hook & Action Management
WordPress hooks use `HookEnum` constants (e.g., `HookEnum::INIT`, `HookEnum::REST_API_INIT`) instead of magic strings in all `add_action()` and `add_filter()` calls.

## 4. Error Detection
Fatal error logic is centralized in `ErrorChecker::is_fatal_error($error)` which delegates to `ErrorTypeEnum::FATAL_TYPES`. No inline `in_array()` checks for `E_ERROR`, `E_PARSE`, etc.

## PHP Enum Spec
A dedicated enum specification at `spec/04-php-standards/enums.md` documents all Enum classes (`HookEnum`, `PathEnum`, `ErrorTypeEnum`) with complete constant listings, `RiseupPathUtils` typed accessors, and `ErrorChecker` implementation.

## Catch Rule
All try-catch blocks must catch `\Throwable`, not `Exception`, to capture PHP 7+ `Error` and `TypeError` types.
