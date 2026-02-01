# 07 - Validation Utilities

> **Phase:** Foundation  
> **Dependencies:** `04-enums-constants.md`  
> **Estimated Time:** 2-3 hours

---

## 📋 Scope

Create input validation and sanitization utilities used across the plugin.

---

## 🔧 Validator Class

**File:** `src/Utils/Validator.php`

```php
<?php
namespace ExamQuestionsManager\Utils;

class Validator {
    private array $errors = [];
    private array $data;
    
    public function __construct(array $data) {
        $this->data = $data;
    }
    
    /**
     * Create new validator instance
     */
    public static function make(array $data): self {
        return new self($data);
    }
    
    /**
     * Validate email format
     */
    public function email(string $field, bool $required = true): self {
        $value = $this->data[$field] ?? null;
        
        if ($required && empty($value)) {
            $this->errors[$field] = "{$field} is required";
            return $this;
        }
        
        if (!empty($value) && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$field] = "Invalid email format";
        }
        
        return $this;
    }
    
    /**
     * Validate required field
     */
    public function required(string $field, string $label = null): self {
        $value = $this->data[$field] ?? null;
        $label = $label ?? $field;
        
        if (empty($value) && $value !== '0' && $value !== 0) {
            $this->errors[$field] = "{$label} is required";
        }
        
        return $this;
    }
    
    /**
     * Validate string length
     */
    public function length(string $field, int $min = 0, int $max = PHP_INT_MAX): self {
        $value = $this->data[$field] ?? '';
        $length = mb_strlen($value);
        
        if ($length < $min) {
            $this->errors[$field] = "{$field} must be at least {$min} characters";
        } elseif ($length > $max) {
            $this->errors[$field] = "{$field} must be at most {$max} characters";
        }
        
        return $this;
    }
    
    /**
     * Validate positive integer
     */
    public function positiveInt(string $field, bool $required = true): self {
        $value = $this->data[$field] ?? null;
        
        if ($required && ($value === null || $value === '')) {
            $this->errors[$field] = "{$field} is required";
            return $this;
        }
        
        if ($value !== null && $value !== '') {
            if (!is_numeric($value) || intval($value) <= 0) {
                $this->errors[$field] = "{$field} must be a positive integer";
            }
        }
        
        return $this;
    }
    
    /**
     * Validate integer range
     */
    public function intRange(string $field, int $min, int $max): self {
        $value = $this->data[$field] ?? null;
        
        if ($value !== null && $value !== '') {
            $intVal = intval($value);
            if ($intVal < $min || $intVal > $max) {
                $this->errors[$field] = "{$field} must be between {$min} and {$max}";
            }
        }
        
        return $this;
    }
    
    /**
     * Validate URL format
     */
    public function url(string $field, bool $required = false): self {
        $value = $this->data[$field] ?? null;
        
        if ($required && empty($value)) {
            $this->errors[$field] = "{$field} is required";
            return $this;
        }
        
        if (!empty($value) && filter_var($value, FILTER_VALIDATE_URL) === false) {
            $this->errors[$field] = "Invalid URL format";
        }
        
        return $this;
    }
    
    /**
     * Validate slug format (lowercase, hyphens, alphanumeric)
     */
    public function slug(string $field): self {
        $value = $this->data[$field] ?? '';
        
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            $this->errors[$field] = "Invalid slug format (use lowercase letters, numbers, and hyphens only)";
        }
        
        return $this;
    }
    
    /**
     * Validate password strength
     */
    public function password(string $field, int $minLength = 8): self {
        $value = $this->data[$field] ?? '';
        
        if (mb_strlen($value) < $minLength) {
            $this->errors[$field] = "Password must be at least {$minLength} characters";
        }
        
        return $this;
    }
    
    /**
     * Validate WhatsApp number format
     */
    public function whatsapp(string $field, bool $required = false): self {
        $value = $this->data[$field] ?? null;
        
        if ($required && empty($value)) {
            $this->errors[$field] = "WhatsApp number is required";
            return $this;
        }
        
        // Allow + and digits, 10-15 characters
        if (!empty($value) && !preg_match('/^\+?[0-9]{10,15}$/', $value)) {
            $this->errors[$field] = "Invalid WhatsApp number format";
        }
        
        return $this;
    }
    
    /**
     * Validate LinkedIn URL
     */
    public function linkedIn(string $field, bool $required = false): self {
        $value = $this->data[$field] ?? null;
        
        if ($required && empty($value)) {
            $this->errors[$field] = "LinkedIn URL is required";
            return $this;
        }
        
        if (!empty($value)) {
            if (!preg_match('/^https?:\/\/(www\.)?linkedin\.com\/in\/[\w-]+\/?$/', $value)) {
                $this->errors[$field] = "Invalid LinkedIn profile URL";
            }
        }
        
        return $this;
    }
    
    /**
     * Validate enum value
     */
    public function enum(string $field, string $enumClass): self {
        $value = $this->data[$field] ?? null;
        
        if ($value !== null && $value !== '') {
            try {
                $enumClass::from($value);
            } catch (\ValueError $e) {
                $cases = implode(', ', array_map(fn($c) => $c->value, $enumClass::cases()));
                $this->errors[$field] = "Invalid value. Must be one of: {$cases}";
            }
        }
        
        return $this;
    }
    
    /**
     * Validate in array
     */
    public function in(string $field, array $allowed): self {
        $value = $this->data[$field] ?? null;
        
        if ($value !== null && !in_array($value, $allowed, true)) {
            $allowedStr = implode(', ', $allowed);
            $this->errors[$field] = "{$field} must be one of: {$allowedStr}";
        }
        
        return $this;
    }
    
    /**
     * Validate file upload
     */
    public function file(string $field, array $allowedMimes = [], int $maxSizeBytes = 5242880): self {
        $file = $this->data[$field] ?? null;
        
        if (!$file || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return $this; // No file, not required validation
        }
        
        // Check size
        if ($file['size'] > $maxSizeBytes) {
            $maxMb = round($maxSizeBytes / 1048576, 1);
            $this->errors[$field] = "File size exceeds maximum of {$maxMb}MB";
            return $this;
        }
        
        // Check mime type
        if (!empty($allowedMimes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedMimes)) {
                $this->errors[$field] = "File type not allowed";
            }
        }
        
        return $this;
    }
    
    /**
     * Custom validation with callback
     */
    public function custom(string $field, callable $callback, string $message): self {
        $value = $this->data[$field] ?? null;
        
        if (!$callback($value)) {
            $this->errors[$field] = $message;
        }
        
        return $this;
    }
    
    /**
     * Check if validation passed
     */
    public function passes(): bool {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     */
    public function fails(): bool {
        return !$this->passes();
    }
    
    /**
     * Get validation errors
     */
    public function errors(): array {
        return $this->errors;
    }
    
    /**
     * Get first error message
     */
    public function firstError(): ?string {
        return $this->errors[array_key_first($this->errors)] ?? null;
    }
    
    /**
     * Throw exception if validation fails
     */
    public function validate(): void {
        if ($this->fails()) {
            throw new \InvalidArgumentException($this->firstError());
        }
    }
}
```

---

## 🔧 Sanitizer Class

**File:** `src/Utils/Sanitizer.php`

```php
<?php
namespace ExamQuestionsManager\Utils;

class Sanitizer {
    /**
     * Sanitize email
     */
    public static function email(string $email): string {
        return sanitize_email($email);
    }
    
    /**
     * Sanitize text field
     */
    public static function text(string $text): string {
        return sanitize_text_field($text);
    }
    
    /**
     * Sanitize textarea content
     */
    public static function textarea(string $text): string {
        return sanitize_textarea_field($text);
    }
    
    /**
     * Sanitize slug
     */
    public static function slug(string $slug): string {
        return sanitize_title($slug);
    }
    
    /**
     * Sanitize URL
     */
    public static function url(string $url): string {
        return esc_url_raw($url);
    }
    
    /**
     * Sanitize integer
     */
    public static function int(mixed $value): int {
        return (int) $value;
    }
    
    /**
     * Sanitize positive integer
     */
    public static function positiveInt(mixed $value): int {
        $int = self::int($value);
        return max(0, $int);
    }
    
    /**
     * Sanitize boolean
     */
    public static function bool(mixed $value): bool {
        if (is_string($value)) {
            $value = strtolower($value);
            return in_array($value, ['true', '1', 'yes', 'on'], true);
        }
        return (bool) $value;
    }
    
    /**
     * Sanitize array of strings
     */
    public static function stringArray(array $arr): array {
        return array_map([self::class, 'text'], $arr);
    }
    
    /**
     * Sanitize HTML (allow basic formatting)
     */
    public static function html(string $html): string {
        return wp_kses_post($html);
    }
    
    /**
     * Sanitize filename
     */
    public static function filename(string $filename): string {
        return sanitize_file_name($filename);
    }
    
    /**
     * Sanitize JSON input
     */
    public static function json(string $json): ?array {
        $decoded = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return $decoded;
    }
    
    /**
     * Sanitize request data array
     */
    public static function request(array $data, array $rules): array {
        $sanitized = [];
        
        foreach ($rules as $field => $type) {
            $value = $data[$field] ?? null;
            
            if ($value === null) {
                continue;
            }
            
            $sanitized[$field] = match($type) {
                'email' => self::email($value),
                'text' => self::text($value),
                'textarea' => self::textarea($value),
                'slug' => self::slug($value),
                'url' => self::url($value),
                'int' => self::int($value),
                'positive_int' => self::positiveInt($value),
                'bool' => self::bool($value),
                'html' => self::html($value),
                'filename' => self::filename($value),
                default => self::text($value),
            };
        }
        
        return $sanitized;
    }
}
```

---

## 🔧 Static Validation Helpers

**File:** `src/Utils/ValidationHelpers.php`

```php
<?php
namespace ExamQuestionsManager\Utils;

class ValidationHelpers {
    /**
     * Quick email validation
     */
    public static function isValidEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Quick slug validation
     */
    public static function isValidSlug(string $slug): bool {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }
    
    /**
     * Quick URL validation
     */
    public static function isValidUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Quick positive integer check
     */
    public static function isPositiveInt(mixed $value): bool {
        return is_numeric($value) && intval($value) > 0;
    }
    
    /**
     * Quick non-empty string check
     */
    public static function isNotEmpty(mixed $value): bool {
        if ($value === null) return false;
        if (is_string($value)) return trim($value) !== '';
        if (is_array($value)) return !empty($value);
        return true;
    }
    
    /**
     * Check if soft deadline < hard deadline
     */
    public static function isValidDeadlinePair(int $soft, int $hard): bool {
        return $soft > 0 && $hard > 0 && $soft < $hard;
    }
}
```

---

## 📝 Usage Examples

```php
use ExamQuestionsManager\Utils\Validator;
use ExamQuestionsManager\Utils\Sanitizer;
use ExamQuestionsManager\Enums\ParticipantStatus;

// Validate signup data
$validator = Validator::make($data)
    ->required('email', 'Email')
    ->email('email')
    ->required('password', 'Password')
    ->password('password', 8)
    ->whatsapp('whatsapp', false)
    ->linkedIn('linkedinUrl', false);

if ($validator->fails()) {
    return ['errors' => $validator->errors()];
}

// Sanitize input
$sanitized = Sanitizer::request($_POST, [
    'email' => 'email',
    'title' => 'text',
    'description' => 'textarea',
    'slug' => 'slug',
    'url' => 'url',
    'softDeadlineDays' => 'positive_int',
    'isEnabled' => 'bool',
]);

// Validate enum
$validator = Validator::make(['status' => 'ACTIVE'])
    ->enum('status', ParticipantStatus::class);

// Custom validation
$validator = Validator::make($data)
    ->custom('softDeadlineDays', function($value) use ($data) {
        return $value < ($data['hardDeadlineDays'] ?? PHP_INT_MAX);
    }, 'Soft deadline must be less than hard deadline');
```

---

## ✅ Acceptance Criteria

### Validator Class
- [ ] `email()` validates email format
- [ ] `required()` checks non-empty values
- [ ] `length()` validates string length range
- [ ] `positiveInt()` validates positive integers
- [ ] `url()` validates URL format
- [ ] `slug()` validates slug pattern
- [ ] `password()` validates minimum length
- [ ] `whatsapp()` validates phone format
- [ ] `linkedIn()` validates LinkedIn URL
- [ ] `enum()` validates against PHP enum
- [ ] `file()` validates uploaded files
- [ ] `custom()` allows callback validation

### Validator Flow
- [ ] `passes()` returns true when no errors
- [ ] `fails()` returns true when errors exist
- [ ] `errors()` returns associative array
- [ ] `firstError()` returns first error message
- [ ] `validate()` throws on failure

### Sanitizer Class
- [ ] All sanitize methods work correctly
- [ ] `request()` bulk sanitizes by type map
- [ ] Uses WordPress sanitization functions
- [ ] Handles null/missing values gracefully

---

*Next: `08-rbac-system.md`*
