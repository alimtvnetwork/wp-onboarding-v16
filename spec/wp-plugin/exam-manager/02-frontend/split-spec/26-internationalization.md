# 26. Internationalization (i18n)

## Overview
Strategy for making the frontend translatable, including string extraction, date/time localization, number formatting, and RTL support considerations.

---

## 26.1 Translation Architecture

### File Structure
```
src/
├── locales/
│   ├── en/
│   │   ├── common.json      # Shared UI strings
│   │   ├── auth.json        # Login, signup, password
│   │   ├── exam.json        # Exam-related strings
│   │   ├── dashboard.json   # Dashboard page
│   │   └── errors.json      # Error messages
│   ├── es/
│   │   └── (same structure)
│   ├── de/
│   │   └── (same structure)
│   └── index.ts             # Locale loader
```

### Translation File Format (JSON)
```json
{
  "auth": {
    "login": {
      "title": "Sign In",
      "email": "Email Address",
      "password": "Password",
      "submit": "Sign In",
      "forgot": "Forgot Password?",
      "noAccount": "Don't have an account?",
      "signUp": "Sign Up"
    },
    "errors": {
      "invalidCredentials": "Invalid email or password",
      "accountLocked": "Account is locked. Please try again later."
    }
  }
}
```

### Namespacing Convention
| Namespace | Content | Example Key |
|-----------|---------|-------------|
| `common` | Buttons, labels, shared | `common.save`, `common.cancel` |
| `auth` | Authentication flows | `auth.login.title` |
| `exam` | Exam content, sections | `exam.section.markDone` |
| `dashboard` | Dashboard page | `dashboard.progress.title` |
| `deadline` | Deadline displays | `deadline.daysRemaining` |
| `errors` | All error messages | `errors.network.timeout` |
| `validation` | Form validation | `validation.email.invalid` |

---

## 26.2 String Extraction Rules

### DO: Use Translation Keys
```tsx
// ✅ Correct
<Button>{t('common.submit')}</Button>
<h1>{t('auth.login.title')}</h1>
<p>{t('errors.invalidCredentials')}</p>
```

### DON'T: Hardcode Strings
```tsx
// ❌ Wrong - hardcoded strings
<Button>Submit</Button>
<h1>Sign In</h1>
<p>Invalid email or password</p>
```

### Interpolation
```json
{
  "deadline": {
    "daysRemaining": "{{count}} day remaining",
    "daysRemaining_plural": "{{count}} days remaining"
  },
  "greeting": "Hello, {{name}}!"
}
```

```tsx
// Usage
t('deadline.daysRemaining', { count: 5 })  // "5 days remaining"
t('greeting', { name: 'John' })            // "Hello, John!"
```

### Pluralization
```json
{
  "items": {
    "count_zero": "No items",
    "count_one": "{{count}} item",
    "count_other": "{{count}} items"
  }
}
```

---

## 26.3 Date & Time Localization

### Date Formatting

| Format | EN-US | DE-DE | Key Usage |
|--------|-------|-------|-----------|
| Short | 1/25/26 | 25.1.26 | Tables, lists |
| Medium | Jan 25, 2026 | 25. Jan. 2026 | Cards, details |
| Long | January 25, 2026 | 25. Januar 2026 | Formal documents |
| Full | Saturday, January 25, 2026 | Samstag, 25. Januar 2026 | Headers |

### Time Formatting

| Format | EN-US | DE-DE |
|--------|-------|-------|
| Short | 2:30 PM | 14:30 |
| Long | 2:30:45 PM | 14:30:45 |
| With zone | 2:30 PM EST | 14:30 MEZ |

### Implementation Pattern
```typescript
// Use Intl.DateTimeFormat
const formatDate = (date: Date, locale: string, style: 'short' | 'medium' | 'long') => {
  const options: Intl.DateTimeFormatOptions = {
    short: { month: 'numeric', day: 'numeric', year: '2-digit' },
    medium: { month: 'short', day: 'numeric', year: 'numeric' },
    long: { month: 'long', day: 'numeric', year: 'numeric' },
  }[style];
  
  return new Intl.DateTimeFormat(locale, options).format(date);
};
```

### Relative Time
```typescript
// "2 days ago", "in 3 hours"
const formatRelative = (date: Date, locale: string) => {
  const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
  const diff = date.getTime() - Date.now();
  const days = Math.round(diff / (1000 * 60 * 60 * 24));
  
  if (Math.abs(days) < 1) {
    const hours = Math.round(diff / (1000 * 60 * 60));
    return rtf.format(hours, 'hour');
  }
  return rtf.format(days, 'day');
};
```

---

## 26.4 Number Formatting

### Number Display

| Type | EN-US | DE-DE | ES-ES |
|------|-------|-------|-------|
| Decimal | 1,234.56 | 1.234,56 | 1.234,56 |
| Percent | 75% | 75 % | 75 % |
| Currency | $1,234.00 | 1.234,00 € | 1.234,00 € |

### Implementation
```typescript
const formatNumber = (value: number, locale: string) => {
  return new Intl.NumberFormat(locale).format(value);
};

const formatPercent = (value: number, locale: string) => {
  return new Intl.NumberFormat(locale, {
    style: 'percent',
    minimumFractionDigits: 0,
    maximumFractionDigits: 1,
  }).format(value / 100);
};
```

### Progress Display
```typescript
// "75% complete" in different locales
const formatProgress = (percent: number, locale: string) => {
  return t('progress.complete', { 
    percent: formatPercent(percent, locale) 
  });
};
```

---

## 26.5 RTL (Right-to-Left) Support

### Supported RTL Languages
- Arabic (ar)
- Hebrew (he)
- Persian/Farsi (fa)
- Urdu (ur)

### HTML Direction
```html
<!-- Set on <html> element -->
<html lang="ar" dir="rtl">
```

### CSS Logical Properties
```css
/* ❌ Physical properties - don't use */
.element {
  margin-left: 1rem;
  padding-right: 2rem;
  text-align: left;
}

/* ✅ Logical properties - use these */
.element {
  margin-inline-start: 1rem;
  padding-inline-end: 2rem;
  text-align: start;
}
```

### Property Mapping

| Physical | Logical (LTR) | Logical (RTL) |
|----------|---------------|---------------|
| `left` | `inline-start` | `inline-end` |
| `right` | `inline-end` | `inline-start` |
| `margin-left` | `margin-inline-start` | `margin-inline-end` |
| `padding-right` | `padding-inline-end` | `padding-inline-start` |
| `text-align: left` | `text-align: start` | `text-align: end` |
| `float: left` | `float: inline-start` | `float: inline-end` |

### Icon Mirroring
```css
/* Mirror directional icons in RTL */
[dir="rtl"] .icon-arrow-right {
  transform: scaleX(-1);
}

/* Don't mirror universal icons */
[dir="rtl"] .icon-check,
[dir="rtl"] .icon-close {
  transform: none;
}
```

---

## 26.6 Locale Detection & Switching

### Detection Priority
1. URL parameter (`?lang=de`)
2. User preference (stored in localStorage)
3. Browser language (`navigator.language`)
4. Default fallback (`en`)

### Implementation
```typescript
const detectLocale = (): string => {
  // 1. URL parameter
  const urlParams = new URLSearchParams(window.location.search);
  const urlLang = urlParams.get('lang');
  if (urlLang && SUPPORTED_LOCALES.includes(urlLang)) {
    return urlLang;
  }
  
  // 2. Stored preference
  const stored = localStorage.getItem('preferred_locale');
  if (stored && SUPPORTED_LOCALES.includes(stored)) {
    return stored;
  }
  
  // 3. Browser language
  const browserLang = navigator.language.split('-')[0];
  if (SUPPORTED_LOCALES.includes(browserLang)) {
    return browserLang;
  }
  
  // 4. Default
  return 'en';
};
```

### Locale Switcher UI
```tsx
<Select value={currentLocale} onChange={setLocale}>
  <SelectItem value="en">English</SelectItem>
  <SelectItem value="de">Deutsch</SelectItem>
  <SelectItem value="es">Español</SelectItem>
  <SelectItem value="fr">Français</SelectItem>
</Select>
```

---

## 26.7 Backend Integration

### Language Header
```http
Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7
```

### API Response Localization
- Error messages returned in requested language
- Date formats in API use ISO 8601 (`2026-01-25T14:30:00Z`)
- Frontend handles locale-specific formatting

### Email Templates
- Store email templates per locale
- Fallback to default locale if translation missing

---

## 26.8 Translation Workflow

### For Developers
1. Add new string to appropriate namespace JSON
2. Use translation key in component
3. Add to translation memory/glossary
4. Mark for translation review

### For Translators
1. Export strings needing translation
2. Use translation management tool (Crowdin, Phrase)
3. Import translated strings
4. Run validation tests

### Quality Checks
- [ ] No untranslated strings in UI
- [ ] Pluralization works in all locales
- [ ] Date/number formatting correct
- [ ] RTL layout renders properly
- [ ] Text fits in allocated space (expansion)

---

## 26.9 String Expansion Guidelines

Text typically expands when translated:

| Source Length | Expected Expansion |
|--------------|-------------------|
| 1-10 chars | 200-300% |
| 11-20 chars | 180-200% |
| 21-30 chars | 160-180% |
| 31-50 chars | 140-160% |
| 51-70 chars | 130-140% |
| 70+ chars | 120-130% |

### Design Guidelines
- Avoid fixed-width containers for text
- Use flexible layouts (flexbox, grid)
- Test with pseudo-localization (extended strings)
- Button text: allow wrapping or truncation

---

## Acceptance Criteria

- [ ] All UI strings extracted to locale files
- [ ] No hardcoded strings in components
- [ ] Date/time formatting uses Intl APIs
- [ ] Number formatting respects locale
- [ ] Pluralization works correctly
- [ ] RTL layout basics functional
- [ ] Locale detection and switching works
- [ ] Fallback to English when translation missing

---

## Related Specifications

| Topic | Spec |
|-------|------|
| Error Messages | [16-error-handling](16-error-handling.md) |
| Form Validation | [19-form-validation](19-form-validation.md) |
| Email Templates | [33-email-templates](../../01-admin-backend/split-spec/33-email-templates.md) |

---

*Next: `27-performance-targets.md`*
