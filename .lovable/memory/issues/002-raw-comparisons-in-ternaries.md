# Issue #002: Raw Comparisons in Ternary Conditions

**Severity:** Medium  
**Discovered:** 2026-03-03  
**Status:** ✅ Complete

## Root Cause

Ternary conditions contained raw comparisons (`!== false`, `!== null`) instead of extracting them to named boolean variables with positive polarity. This produces unreadable code with no semantic meaning.

## Fix (3 blocks)

### Riseup Asia Uploader
- `Admin/Traits/AdminErrorStateTrait.php` — `getFlashValue()`: `($val !== false) ?` → `$isFound = ($val !== false); return $isFound ?` (1)
- `Traits/Status/StatusPayloadTrait.php` — `readPayloadFile()`: `($content !== false) ?` → `$isReadSuccess = ...; return $isReadSuccess ?` (1)
- `Traits/Plugin/PluginExportTrait.php` — `buildPluginZip()`: `($zipContent !== false) ?` → `$isReadSuccess = ...; return $isReadSuccess ?` (1)

## Prevention

New coding standard `.lovable/memory/coding-standards/php-positive-polarity-ternaries.md` — raw comparisons in ternary conditions are now prohibited. Must extract to named boolean with positive polarity.
