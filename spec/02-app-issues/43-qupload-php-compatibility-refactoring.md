# PHP Compatibility Refactoring — April 1, 2026

> **Project:** Riseup Asia Uploader (WordPress Plugin)
> **Date:** 2026-04-01
> **Purpose:** Document all PHP code changes made to comply with the QUpload remote syntax validator, so any AI or developer can understand, replicate, or extend these changes across the codebase.

---

## Table of Contents

1. [Background & Problem](#background--problem)
2. [User Prompts (Conversation History)](#user-prompts-conversation-history)
3. [Rules & Constraints](#rules--constraints)
4. [Files Modified](#files-modified)
5. [Detailed Change Log](#detailed-change-log)
6. [How to Apply These Rules to Other Files](#how-to-apply-these-rules-to-other-files)
7. [Verification](#verification)

---

## Background & Problem

The Riseup Asia Uploader plugin is deployed to WordPress sites via the **QUpload** plugin. QUpload validates all uploaded PHP files using `token_get_all($content, TOKEN_PARSE)` before activating the plugin. This is a tokenizer-level check — it does **not** execute the code, it only parses it.

### The Problem

Certain perfectly valid PHP syntax patterns cause `token_get_all()` to emit false-positive syntax errors on some PHP versions or tokenizer configurations. When this happens:

- The plugin **is uploaded and extracted** successfully
- But **activation is blocked** with an error like: `syntax error, unexpected token "array"`
- The plugin sits on the server in an inactive state and cannot be used

### Patterns That Cause Failures

| Pattern | Example | Error Message |
|---------|---------|---------------|
| `is_array()` function call | `is_array($settings)` | `unexpected token "array"` |
| `array()` long constructor | `$arr = array()` | `unexpected token "array"` |
| `= array()` as default value | `private $items = array();` | `unexpected token "array"` |

The word `array` in these contexts is interpreted as a reserved keyword token rather than a function name or language construct, depending on the tokenizer version.

---

## User Prompts (Conversation History)

### Prompt 1 — Initial Request

> **User:** "You should fix the PHP code"
>
> **Context:** The user identified that PHP files in the Riseup Asia Uploader plugin contained patterns that would be blocked by the QUpload remote syntax validator. Specifically, the `is_array()` function and `array()` constructor usage needed to be replaced with QUpload-safe alternatives.

### Prompt 2 — Documentation Request

> **User:** "Apr 1st, the changes you have done on which file and what changes please list those out for an AI in MD file so that I can instruct, please do it"
>
> **Context:** After the code changes were made, the user requested a markdown documentation file that captures all changes so it can be handed to any AI system as instructions for future refactoring.

### Prompt 3 — Expanded Documentation Request

> **User:** "Hey, include my prompts which I asked and then what you have modified. So include it like this. My expectation is that you could write a long MD file, so that it is quite explainable. If I give to any AI, they can also integrate those."
>
> **Context:** The user wanted a more comprehensive version of the documentation that includes the conversation history (prompts), detailed before/after code diffs, and enough context that any AI editor can follow and apply the same rules.

---

## Rules & Constraints

These rules are enforced by the QUpload validator and documented in the project's coding standards at `.lovable/memory/coding-standards/php-compatibility-constraint.md` and `spec/13-powershell-integration/06-php-known-issues.md`.

### Rule 1: Never use `is_array()`

**Blocked:**
```php
if (is_array($settings)) { ... }
```

**Correct replacement:**
```php
use RiseupAsia\Enums\PhpNativeType;

if (gettype($settings) === PhpNativeType::PhpArray->value) { ... }
```

### Rule 2: Never use `array()` constructor — use `[]` short syntax

**Blocked:**
```php
private array $items = array();
$data = array('key' => 'value');
```

**Correct replacement:**
```php
private array $items = [];
$data = ['key' => 'value'];
```

### Rule 3: Never use magic strings with `gettype()`

**Blocked:**
```php
if (gettype($var) === 'array') { ... }
```

**Correct replacement:**
```php
use RiseupAsia\Enums\PhpNativeType;

if (gettype($var) === PhpNativeType::PhpArray->value) { ... }
```

### Rule 4: No parameter or return type hints (for legacy-safe traits)

Certain traits that must run across PHP 7.0+ environments must not have typed function signatures.

### Rule 5: No trailing commas in function parameter lists

```php
// BLOCKED
function foo($a, $b,) { }

// CORRECT
function foo($a, $b) { }
```

### Rule 6: No nullable types

```php
// BLOCKED
function foo(?string $name) { }

// CORRECT (for legacy-safe code)
function foo($name) { }
```

### The PhpNativeType Enum

Located at: `includes/Enums/PhpNativeType.php`

```php
namespace RiseupAsia\Enums;

enum PhpNativeType: string
{
    case PhpArray   = 'array';
    case PhpString  = 'string';
    case PhpInteger = 'integer';
    case PhpDouble  = 'double';
    case PhpBoolean = 'boolean';
    case PhpObject  = 'object';
    case PhpNull    = 'NULL';
}
```

Use `PhpNativeType::PhpArray->value` (returns the string `'array'`) instead of the raw string `'array'` or the function `is_array()`.

---

## Files Modified

| # | File Path | Changes Made |
|---|-----------|-------------|
| 1 | `includes/Logging/FileLogger.php` | Replaced `is_array()` calls with `gettype() === PhpNativeType::PhpArray->value`; ensured `PhpNativeType` import exists; confirmed `[]` short array syntax |
| 2 | `includes/Snapshot/SnapshotDetector.php` | Replaced `array()` constructor with `[]` short array syntax on property default |

---

## Detailed Change Log

### File 1: `includes/Logging/FileLogger.php`

**Full path:** `wp-plugins/riseup-asia-uploader/includes/Logging/FileLogger.php`

#### Change 1a: Import statement (already present)

The file already had the correct import at line 24:

```php
use RiseupAsia\Enums\PhpNativeType;
```

**No change needed** — the import was already in place from prior work.

#### Change 1b: `loadLoggingSettings()` method — JSON decode check (line 118)

**BEFORE:**
```php
$settings = json_decode($contents, true);
$isDecodeFailed = !is_array($settings);
```

**AFTER:**
```php
$settings = json_decode($contents, true);
$isDecodeFailed = gettype($settings) !== PhpNativeType::PhpArray->value;
```

**Why:** `is_array($settings)` contains the token `array` which triggers the QUpload validator error. Replaced with `gettype()` comparison using the `PhpNativeType` enum.

#### Change 1c: `loadLoggingSettings()` method — logging key check (line 124)

**BEFORE:**
```php
$hasLogging = isset($settings['logging']) && is_array($settings['logging']);
```

**AFTER:**
```php
$hasLogging = isset($settings['logging']) && gettype($settings['logging']) === PhpNativeType::PhpArray->value;
```

**Why:** Same reason — `is_array()` is blocked by the validator.

#### Change 1d: Property default value (line 73)

**BEFORE:**
```php
private array $dedupHashes = array();
```

**AFTER:**
```php
private array $dedupHashes = [];
```

**Why:** `array()` long constructor syntax is blocked. Short syntax `[]` is safe.

#### Final state of `FileLogger.php` (relevant sections)

```php
// Line 24 — Import
use RiseupAsia\Enums\PhpNativeType;

// Line 73 — Property
private array $dedupHashes = [];

// Line 117-118 — JSON decode validation
$settings = json_decode($contents, true);
$isDecodeFailed = gettype($settings) !== PhpNativeType::PhpArray->value;

// Line 124 — Logging section validation
$hasLogging = isset($settings['logging']) && gettype($settings['logging']) === PhpNativeType::PhpArray->value;
```

---

### File 2: `includes/Snapshot/SnapshotDetector.php`

**Full path:** `wp-plugins/riseup-asia-uploader/includes/Snapshot/SnapshotDetector.php`

#### Change 2a: Property default value (line 31)

**BEFORE:**
```php
private array $providerInstances = array();
```

**AFTER:**
```php
private array $providerInstances = [];
```

**Why:** `array()` long constructor syntax is blocked by the QUpload validator. Short syntax `[]` is the safe equivalent.

#### Final state of `SnapshotDetector.php` (relevant section)

```php
class SnapshotDetector {
    use DetectorProviderTrait;
    use DetectorSettingsTrait;

    private FileLogger $logger;
    private Database $db;
    private array $providerInstances = [];

    public function __construct(FileLogger $logger, Database $db) {
        $this->logger = $logger;
        $this->db = $db;
    }
}
```

---

## How to Apply These Rules to Other Files

If you are an AI editor or developer working on this codebase, follow this checklist when editing or creating any PHP file under `wp-plugins/riseup-asia-uploader/`:

### Step-by-Step Checklist

1. **Search for `is_array(`** — Replace every occurrence:
   ```php
   // Find this:
   is_array($variable)

   // Replace with:
   gettype($variable) === PhpNativeType::PhpArray->value
   ```

2. **Search for `is_string(`** — Replace every occurrence:
   ```php
   gettype($variable) === PhpNativeType::PhpString->value
   ```

3. **Search for `is_int(` or `is_integer(`** — Replace:
   ```php
   gettype($variable) === PhpNativeType::PhpInteger->value
   ```

4. **Search for `is_float(` or `is_double(`** — Replace:
   ```php
   gettype($variable) === PhpNativeType::PhpDouble->value
   ```

5. **Search for `is_bool(`** — Replace:
   ```php
   gettype($variable) === PhpNativeType::PhpBoolean->value
   ```

6. **Search for `is_object(`** — Replace:
   ```php
   gettype($variable) === PhpNativeType::PhpObject->value
   ```

7. **Search for `is_null(`** — Replace:
   ```php
   gettype($variable) === PhpNativeType::PhpNull->value
   ```

8. **Search for `array(`** — Replace all with `[` and matching `)` with `]`:
   ```php
   // Find: array('key' => 'value')
   // Replace: ['key' => 'value']

   // Find: array()
   // Replace: []
   ```

9. **Search for magic type strings** like `=== 'array'` or `=== 'string'` used with `gettype()`:
   ```php
   // Find: gettype($var) === 'array'
   // Replace: gettype($var) === PhpNativeType::PhpArray->value
   ```

10. **Add the import** at the top of the file (after `namespace` and ABSPATH check):
    ```php
    use RiseupAsia\Enums\PhpNativeType;
    ```

### Regex Patterns for Automated Search

```bash
# Find is_array() calls
grep -rn 'is_array(' includes/

# Find array() constructor usage
grep -rn 'array(' includes/ | grep -v '\[\]'

# Find magic type strings with gettype
grep -rn "gettype.*=== '" includes/

# Find all is_* type check functions
grep -rn 'is_string\|is_int\|is_integer\|is_float\|is_double\|is_bool\|is_object\|is_null\|is_array' includes/
```

---

## Verification

### Lint Script

The project includes a lint script at `scripts/lint-php-syntax.sh` that can scan for these patterns. Run it after making changes:

```bash
bash scripts/lint-php-syntax.sh
```

### Manual Verification

After deployment via QUpload, check:

1. **`error.txt`** at `wp-content/uploads/qupload/logs/error.txt` — should have no syntax validation errors
2. **Plugin activation** — the plugin should activate successfully without being blocked
3. **API response** — should not contain `"activation was blocked due to PHP syntax error"`

### CI/CD

The GitHub Actions workflow runs `lint-php-syntax.sh` on every push and PR as an automated safety net.

---

## Summary

On April 1, 2026, two PHP files were refactored to comply with QUpload's `token_get_all()` validator:

1. **`FileLogger.php`** — Replaced 2× `is_array()` calls with `gettype() === PhpNativeType::PhpArray->value` and 1× `array()` with `[]`
2. **`SnapshotDetector.php`** — Replaced 1× `array()` with `[]`

These changes prevent the QUpload validator from blocking plugin activation due to false-positive syntax errors involving the `array` token. The same rules must be applied to all future PHP code in this plugin.
