# Internationalization (i18n) Patterns

> Version: 1.0.0 | Last Updated: 2025-01-26

## Overview

This document defines internationalization patterns for building applications that support multiple languages, locales, and cultural conventions.

---

## 1. Core Concepts

### 1.1 Terminology

| Term | Definition |
|------|------------|
| **i18n** | Internationalization - designing for multiple languages |
| **l10n** | Localization - adapting for specific locale |
| **Locale** | Language + region (e.g., `en-US`, `fr-CA`) |
| **Translation Key** | Identifier for translatable string |
| **Interpolation** | Variable substitution in strings |
| **Pluralization** | Number-based string variations |

### 1.2 Locale Format

```
Format: {language}-{REGION}

Examples:
- en-US (English, United States)
- en-GB (English, United Kingdom)
- fr-FR (French, France)
- fr-CA (French, Canada)
- zh-CN (Chinese, Simplified)
- zh-TW (Chinese, Traditional)
- pt-BR (Portuguese, Brazil)
```

---

## 2. Translation Key Design

### 2.1 Key Naming Convention

```
Format: {namespace}.{context}.{element}

Examples:
- common.buttons.submit
- common.buttons.cancel
- auth.login.title
- auth.login.errors.invalidEmail
- dashboard.widgets.recentActivity.title
- orders.status.pending
- orders.status.shipped
```

### 2.2 Key Structure

```json
{
  "common": {
    "buttons": {
      "submit": "Submit",
      "cancel": "Cancel",
      "save": "Save",
      "delete": "Delete",
      "edit": "Edit"
    },
    "labels": {
      "email": "Email",
      "password": "Password",
      "name": "Name"
    },
    "messages": {
      "loading": "Loading...",
      "error": "An error occurred",
      "success": "Operation successful"
    }
  },
  "auth": {
    "login": {
      "title": "Sign In",
      "subtitle": "Welcome back",
      "forgotPassword": "Forgot password?",
      "noAccount": "Don't have an account?",
      "errors": {
        "invalidEmail": "Please enter a valid email",
        "invalidCredentials": "Invalid email or password"
      }
    }
  }
}
```

### 2.3 Key Rules

```
DO:
✓ Use descriptive, hierarchical keys
✓ Group by feature/namespace
✓ Keep keys stable (don't rename frequently)
✓ Use English keys for readability

DON'T:
✗ Use sequential numbers (msg1, msg2)
✗ Use the source text as key
✗ Create deeply nested structures (max 4 levels)
✗ Include formatting in keys
```

---

## 3. String Interpolation

### 3.1 Variable Placeholders

**TypeScript (react-i18next)**
```typescript
// Translation file
{
  "greeting": "Hello, {{name}}!",
  "itemCount": "You have {{count}} items in your cart",
  "welcome": "Welcome to {{appName}}, {{userName}}"
}

// Usage
import { useTranslation } from 'react-i18next';

function Greeting({ user }: { user: User }) {
  const { t } = useTranslation();
  
  return (
    <h1>{t('greeting', { name: user.firstName })}</h1>
  );
}

// With multiple variables
function Welcome({ user }: { user: User }) {
  const { t } = useTranslation();
  
  return (
    <p>
      {t('welcome', { 
        appName: 'MyApp', 
        userName: user.name 
      })}
    </p>
  );
}
```

**PHP**
```php
class Translator {
    private array $translations = [];
    
    public function translate(string $key, array $params = []): string {
        $template = $this->get($key);
        
        foreach ($params as $name => $value) {
            $template = str_replace("{{$name}}", $value, $template);
        }
        
        return $template;
    }
    
    private function get(string $key): string {
        $keys = explode('.', $key);
        $value = $this->translations;
        
        foreach ($keys as $k) {
            $value = $value[$k] ?? null;
            if ($value === null) {
                return $key; // Fallback to key
            }
        }
        
        return $value;
    }
}

// Usage
$t = new Translator();
echo $t->translate('greeting', ['name' => 'John']);
// Output: Hello, John!
```

**Python**
```python
from typing import Dict, Any

class Translator:
    def __init__(self, translations: Dict[str, Any]):
        self.translations = translations
    
    def t(self, key: str, **params) -> str:
        """Translate key with optional parameters."""
        template = self._get(key)
        
        for name, value in params.items():
            template = template.replace(f"{{{name}}}", str(value))
        
        return template
    
    def _get(self, key: str) -> str:
        keys = key.split('.')
        value = self.translations
        
        for k in keys:
            if isinstance(value, dict):
                value = value.get(k)
            else:
                return key  # Fallback to key
        
        return value if isinstance(value, str) else key

# Usage
translator = Translator(translations)
print(translator.t('greeting', name='John'))
```

---

## 4. Pluralization

### 4.1 Plural Rules

```
English: 2 forms (one, other)
French: 3 forms (one, few, other) - some contexts
Russian: 4 forms (one, few, many, other)
Arabic: 6 forms (zero, one, two, few, many, other)
```

### 4.2 Plural Implementation

**TypeScript**
```typescript
// Translation file with ICU MessageFormat
{
  "items": "{count, plural, =0 {No items} one {# item} other {# items}}",
  "messages": "{count, plural, =0 {No new messages} one {# new message} other {# new messages}}",
  "days": "{count, plural, =0 {Today} one {# day ago} other {# days ago}}"
}

// Usage with react-i18next
import { useTranslation, Trans } from 'react-i18next';

function ItemCount({ count }: { count: number }) {
  const { t } = useTranslation();
  
  return <span>{t('items', { count })}</span>;
}

// Alternative: separate keys
{
  "items_zero": "No items",
  "items_one": "{{count}} item",
  "items_other": "{{count}} items"
}
```

**PHP**
```php
class PluralTranslator {
    public function pluralize(string $key, int $count, array $params = []): string {
        $form = $this->getPluralForm($count);
        $translationKey = "{$key}_{$form}";
        
        return $this->translate($translationKey, array_merge($params, ['count' => $count]));
    }
    
    private function getPluralForm(int $count): string {
        // English plural rules
        if ($count === 0) return 'zero';
        if ($count === 1) return 'one';
        return 'other';
    }
    
    // For other locales
    private function getPluralFormRussian(int $count): string {
        $mod10 = $count % 10;
        $mod100 = $count % 100;
        
        if ($mod10 === 1 && $mod100 !== 11) {
            return 'one';
        }
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
            return 'few';
        }
        return 'many';
    }
}

// Usage
$t->pluralize('items', 0);   // "No items"
$t->pluralize('items', 1);   // "1 item"
$t->pluralize('items', 5);   // "5 items"
```

---

## 5. Date and Time Formatting

### 5.1 Locale-Aware Dates

**TypeScript**
```typescript
class DateFormatter {
  private locale: string;
  
  constructor(locale: string = 'en-US') {
    this.locale = locale;
  }
  
  formatDate(date: Date, style: 'short' | 'medium' | 'long' | 'full' = 'medium'): string {
    return new Intl.DateTimeFormat(this.locale, {
      dateStyle: style,
    }).format(date);
  }
  
  formatTime(date: Date, style: 'short' | 'medium' | 'long' = 'short'): string {
    return new Intl.DateTimeFormat(this.locale, {
      timeStyle: style,
    }).format(date);
  }
  
  formatDateTime(date: Date): string {
    return new Intl.DateTimeFormat(this.locale, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(date);
  }
  
  formatRelative(date: Date): string {
    const rtf = new Intl.RelativeTimeFormat(this.locale, { numeric: 'auto' });
    const now = new Date();
    const diffMs = date.getTime() - now.getTime();
    const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));
    
    if (Math.abs(diffDays) < 1) {
      const diffHours = Math.round(diffMs / (1000 * 60 * 60));
      if (Math.abs(diffHours) < 1) {
        const diffMinutes = Math.round(diffMs / (1000 * 60));
        return rtf.format(diffMinutes, 'minute');
      }
      return rtf.format(diffHours, 'hour');
    }
    
    return rtf.format(diffDays, 'day');
  }
}

// Usage
const formatter = new DateFormatter('de-DE');
formatter.formatDate(new Date());      // "26. Jan. 2025"
formatter.formatDateTime(new Date());  // "26. Jan. 2025, 14:30"
formatter.formatRelative(yesterday);   // "gestern"
```

**PHP**
```php
class DateFormatter {
    private string $locale;
    
    public function __construct(string $locale = 'en_US') {
        $this->locale = $locale;
    }
    
    public function formatDate(DateTimeInterface $date, int $style = IntlDateFormatter::MEDIUM): string {
        $formatter = new IntlDateFormatter(
            $this->locale,
            $style,
            IntlDateFormatter::NONE
        );
        
        return $formatter->format($date);
    }
    
    public function formatDateTime(DateTimeInterface $date): string {
        $formatter = new IntlDateFormatter(
            $this->locale,
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::SHORT
        );
        
        return $formatter->format($date);
    }
    
    public function formatRelative(DateTimeInterface $date): string {
        $now = new DateTime();
        $diff = $now->diff($date);
        
        if ($diff->days === 0) {
            if ($diff->h > 0) {
                return $this->pluralize('hours_ago', $diff->h);
            }
            return $this->pluralize('minutes_ago', $diff->i);
        }
        
        if ($diff->days === 1) {
            return $diff->invert ? 'yesterday' : 'tomorrow';
        }
        
        return $this->pluralize('days_ago', $diff->days);
    }
}
```

---

## 6. Number and Currency Formatting

### 6.1 Number Formatting

**TypeScript**
```typescript
class NumberFormatter {
  private locale: string;
  
  constructor(locale: string = 'en-US') {
    this.locale = locale;
  }
  
  format(value: number): string {
    return new Intl.NumberFormat(this.locale).format(value);
  }
  
  formatDecimal(value: number, decimals: number = 2): string {
    return new Intl.NumberFormat(this.locale, {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    }).format(value);
  }
  
  formatPercent(value: number): string {
    return new Intl.NumberFormat(this.locale, {
      style: 'percent',
      minimumFractionDigits: 0,
      maximumFractionDigits: 1,
    }).format(value);
  }
  
  formatCompact(value: number): string {
    return new Intl.NumberFormat(this.locale, {
      notation: 'compact',
      compactDisplay: 'short',
    }).format(value);
  }
  
  formatCurrency(value: number, currency: string = 'USD'): string {
    return new Intl.NumberFormat(this.locale, {
      style: 'currency',
      currency,
    }).format(value);
  }
}

// Usage
const fmt = new NumberFormatter('de-DE');
fmt.format(1234567);           // "1.234.567"
fmt.formatDecimal(1234.5);     // "1.234,50"
fmt.formatPercent(0.156);      // "15,6 %"
fmt.formatCompact(1500000);    // "1,5 Mio."
fmt.formatCurrency(99.99, 'EUR'); // "99,99 €"
```

---

## 7. Translation File Organization

### 7.1 File Structure

```
locales/
├── en/
│   ├── common.json       # Shared strings
│   ├── auth.json         # Authentication
│   ├── dashboard.json    # Dashboard feature
│   ├── orders.json       # Orders feature
│   └── errors.json       # Error messages
├── de/
│   ├── common.json
│   ├── auth.json
│   └── ...
├── fr/
│   └── ...
└── index.ts              # Loader/aggregator
```

### 7.2 Namespace Loading

**TypeScript**
```typescript
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import Backend from 'i18next-http-backend';
import LanguageDetector from 'i18next-browser-languagedetector';

i18n
  .use(Backend)
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    fallbackLng: 'en',
    supportedLngs: ['en', 'de', 'fr', 'es'],
    
    ns: ['common', 'auth', 'dashboard'],
    defaultNS: 'common',
    
    backend: {
      loadPath: '/locales/{{lng}}/{{ns}}.json',
    },
    
    detection: {
      order: ['querystring', 'cookie', 'localStorage', 'navigator'],
      lookupQuerystring: 'lang',
      lookupCookie: 'i18next',
      lookupLocalStorage: 'i18nextLng',
      caches: ['localStorage', 'cookie'],
    },
    
    interpolation: {
      escapeValue: false, // React already escapes
    },
  });

export default i18n;
```

### 7.3 Lazy Loading Namespaces

```typescript
import { useTranslation } from 'react-i18next';

function OrdersPage() {
  // Load 'orders' namespace on demand
  const { t, ready } = useTranslation('orders', { useSuspense: false });
  
  if (!ready) {
    return <Loading />;
  }
  
  return (
    <div>
      <h1>{t('title')}</h1>
      {/* ... */}
    </div>
  );
}
```

---

## 8. React Integration

### 8.1 Translation Hook

```typescript
import { useTranslation } from 'react-i18next';

function LoginForm() {
  const { t } = useTranslation('auth');
  
  return (
    <form>
      <h1>{t('login.title')}</h1>
      
      <label htmlFor="email">{t('common:labels.email')}</label>
      <input id="email" type="email" />
      
      <label htmlFor="password">{t('common:labels.password')}</label>
      <input id="password" type="password" />
      
      <button type="submit">{t('common:buttons.submit')}</button>
    </form>
  );
}
```

### 8.2 Trans Component (Rich Text)

```typescript
import { Trans, useTranslation } from 'react-i18next';

// Translation
{
  "termsAgreement": "By signing up, you agree to our <terms>Terms of Service</terms> and <privacy>Privacy Policy</privacy>."
}

function TermsAgreement() {
  const { t } = useTranslation();
  
  return (
    <p>
      <Trans
        i18nKey="termsAgreement"
        components={{
          terms: <a href="/terms" />,
          privacy: <a href="/privacy" />,
        }}
      />
    </p>
  );
}
```

### 8.3 Language Switcher

```typescript
import { useTranslation } from 'react-i18next';

const LANGUAGES = [
  { code: 'en', name: 'English', flag: '🇺🇸' },
  { code: 'de', name: 'Deutsch', flag: '🇩🇪' },
  { code: 'fr', name: 'Français', flag: '🇫🇷' },
  { code: 'es', name: 'Español', flag: '🇪🇸' },
];

function LanguageSwitcher() {
  const { i18n } = useTranslation();
  
  const handleChange = (langCode: string) => {
    i18n.changeLanguage(langCode);
    document.documentElement.lang = langCode;
  };
  
  return (
    <select 
      value={i18n.language} 
      onChange={(e) => handleChange(e.target.value)}
      aria-label="Select language"
    >
      {LANGUAGES.map((lang) => (
        <option key={lang.code} value={lang.code}>
          {lang.flag} {lang.name}
        </option>
      ))}
    </select>
  );
}
```

---

## 9. RTL Support

### 9.1 Direction Detection

```typescript
const RTL_LANGUAGES = ['ar', 'he', 'fa', 'ur'];

function getDirection(locale: string): 'ltr' | 'rtl' {
  const lang = locale.split('-')[0];
  return RTL_LANGUAGES.includes(lang) ? 'rtl' : 'ltr';
}

// Apply to document
function setDocumentDirection(locale: string) {
  const dir = getDirection(locale);
  document.documentElement.dir = dir;
  document.documentElement.lang = locale;
}
```

### 9.2 RTL-Aware CSS

```css
/* Use logical properties */
.card {
  /* Instead of margin-left, margin-right */
  margin-inline-start: 1rem;
  margin-inline-end: 2rem;
  
  /* Instead of padding-left, padding-right */
  padding-inline: 1.5rem;
  
  /* Instead of text-align: left */
  text-align: start;
  
  /* Instead of border-left */
  border-inline-start: 2px solid var(--primary);
}

/* Flexbox direction auto-flips with dir="rtl" */
.nav {
  display: flex;
  gap: 1rem;
}

/* For icons that shouldn't flip */
.icon-arrow {
  /* Flip in RTL */
}

[dir="rtl"] .icon-arrow {
  transform: scaleX(-1);
}

.icon-checkmark {
  /* Don't flip checkmarks, universal symbols */
}
```

---

## 10. Testing Translations

### 10.1 Translation Coverage

```typescript
import fs from 'fs';
import path from 'path';

function getTranslationKeys(obj: object, prefix = ''): string[] {
  return Object.entries(obj).flatMap(([key, value]) => {
    const fullKey = prefix ? `${prefix}.${key}` : key;
    if (typeof value === 'object' && value !== null) {
      return getTranslationKeys(value, fullKey);
    }
    return [fullKey];
  });
}

function checkTranslationCoverage(
  baseLocale: string,
  targetLocale: string
): { missing: string[]; extra: string[] } {
  const basePath = path.join('locales', baseLocale);
  const targetPath = path.join('locales', targetLocale);
  
  const baseKeys = new Set(getTranslationKeys(loadTranslations(basePath)));
  const targetKeys = new Set(getTranslationKeys(loadTranslations(targetPath)));
  
  const missing = [...baseKeys].filter(k => !targetKeys.has(k));
  const extra = [...targetKeys].filter(k => !baseKeys.has(k));
  
  return { missing, extra };
}

// Test
describe('Translation Coverage', () => {
  const locales = ['de', 'fr', 'es'];
  
  locales.forEach(locale => {
    it(`${locale} should have all keys from en`, () => {
      const { missing } = checkTranslationCoverage('en', locale);
      expect(missing).toHaveLength(0);
    });
  });
});
```

### 10.2 Interpolation Testing

```typescript
describe('Translation Interpolation', () => {
  it('should handle all placeholder types', () => {
    const t = createTranslator('en');
    
    // Simple interpolation
    expect(t('greeting', { name: 'John' })).toBe('Hello, John!');
    
    // Pluralization
    expect(t('items', { count: 0 })).toBe('No items');
    expect(t('items', { count: 1 })).toBe('1 item');
    expect(t('items', { count: 5 })).toBe('5 items');
    
    // Multiple variables
    expect(t('welcome', { appName: 'App', userName: 'John' }))
      .toBe('Welcome to App, John');
  });
});
```

---

## 11. Best Practices

### 11.1 Translation Guidelines

```
DO:
✓ Use complete sentences (context for translators)
✓ Avoid string concatenation
✓ Include translator comments for ambiguous terms
✓ Use ICU MessageFormat for complex plurals
✓ Keep keys stable across versions

DON'T:
✗ Split sentences across multiple keys
✗ Include HTML in translation strings
✗ Use technical jargon as keys
✗ Hardcode any user-facing text
✗ Assume word order is universal
```

### 11.2 Translator Comments

```json
{
  "_comment_status": "Order status - context: e-commerce order tracking",
  "status": {
    "pending": "Pending",
    "processing": "Processing",
    "shipped": "Shipped"
  },
  
  "_comment_greeting": "Displayed on dashboard, {name} is user's first name",
  "greeting": "Hello, {{name}}!"
}
```

---

## i18n Checklist

| Category | Requirement | Priority |
|----------|-------------|----------|
| Keys | Hierarchical, descriptive naming | Required |
| Strings | No hardcoded user-facing text | Required |
| Plurals | Use ICU MessageFormat | Required |
| Dates | Use Intl.DateTimeFormat | Required |
| Numbers | Use Intl.NumberFormat | Required |
| RTL | Logical CSS properties | Required |
| Loading | Lazy load namespaces | Recommended |
| Testing | Coverage checks for all locales | Required |
| Fallback | Graceful fallback to base locale | Required |

---

## Cross-References

- [01-coding-standards-foundation.md](../01-foundation/01-coding-standards-foundation.md) - Naming conventions
- [02-file-organization-quality.md](../03-quality/02-file-organization-quality.md) - Locale file structure
- [02-accessibility-standards-ux.md](./02-accessibility-standards-ux.md) - Accessible language switcher
