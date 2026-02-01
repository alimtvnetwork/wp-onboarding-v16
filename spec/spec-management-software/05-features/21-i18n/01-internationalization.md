# 21.1 Internationalization

**Version:** 1.0.0  
**Status:** Planned  
**Last Updated:** 2026-01-28

---

## Overview

Internationalization (i18n) system supporting multiple languages, locale-specific formatting, and RTL layouts for global accessibility.

**Cross-References:**
- [Theme System](../10-theme-system/00-overview.md) - RTL styling
- [State Management](../16-state-management/00-overview.md) - Language state
- [Component Library](../10-theme-system/02-component-library.md) - Localized components

---

## 21.1.1 Supported Languages

| Code | Language | Direction | Status |
|------|----------|-----------|--------|
| `en` | English | LTR | ✅ Default |
| `de` | German | LTR | Planned |
| `fr` | French | LTR | Planned |
| `es` | Spanish | LTR | Planned |
| `ja` | Japanese | LTR | Planned |
| `zh` | Chinese (Simplified) | LTR | Planned |
| `ar` | Arabic | RTL | Planned |

---

## 21.1.2 Translation Architecture

```
src/
├── locales/
│   ├── en/
│   │   ├── common.json      # Shared strings
│   │   ├── dashboard.json   # Dashboard page
│   │   ├── editor.json      # Editor page
│   │   ├── errors.json      # Error messages
│   │   └── validation.json  # Form validation
│   ├── de/
│   │   └── ...
│   └── fr/
│       └── ...
└── i18n/
    ├── config.ts            # i18n configuration
    ├── provider.tsx         # I18nProvider component
    └── hooks.ts             # useTranslation, useLocale
```

---

## 21.1.3 Translation Files

```json
// locales/en/common.json
{
  "app": {
    "name": "Spec Manager",
    "tagline": "Manage your specifications with ease"
  },
  "navigation": {
    "home": "Home",
    "projects": "Projects",
    "settings": "Settings",
    "logout": "Log out"
  },
  "actions": {
    "save": "Save",
    "cancel": "Cancel",
    "delete": "Delete",
    "edit": "Edit",
    "create": "Create",
    "confirm": "Confirm"
  },
  "status": {
    "loading": "Loading...",
    "saving": "Saving...",
    "saved": "Saved",
    "error": "Error"
  }
}

// locales/en/dashboard.json
{
  "title": "Dashboard",
  "welcome": "Welcome back, {{name}}",
  "stats": {
    "projects": "{{count}} project",
    "projects_plural": "{{count}} projects",
    "files": "{{count}} file",
    "files_plural": "{{count}} files"
  },
  "newProject": {
    "title": "Create New Project",
    "nameLabel": "Project Name",
    "namePlaceholder": "Enter project name",
    "descriptionLabel": "Description",
    "descriptionPlaceholder": "Describe your project"
  }
}
```

---

## 21.1.4 Usage Patterns

```typescript
// Basic translation
import { useTranslation } from '@/i18n/hooks';

const Dashboard = () => {
  const { t } = useTranslation('dashboard');
  
  return (
    <div>
      <h1>{t('title')}</h1>
      <p>{t('welcome', { name: user.name })}</p>
    </div>
  );
};

// Pluralization
const ProjectCount = ({ count }: { count: number }) => {
  const { t } = useTranslation('dashboard');
  
  return <span>{t('stats.projects', { count })}</span>;
  // "1 project" or "5 projects"
};

// Namespace switching
const { t: tCommon } = useTranslation('common');
const { t: tErrors } = useTranslation('errors');
```

---

## 21.1.5 Locale Formatting

```typescript
// Date formatting
const formatDate = (date: Date, locale: string) => {
  return new Intl.DateTimeFormat(locale, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(date);
};
// en: "January 28, 2026"
// de: "28. Januar 2026"

// Number formatting
const formatNumber = (num: number, locale: string) => {
  return new Intl.NumberFormat(locale).format(num);
};
// en: "1,234.56"
// de: "1.234,56"

// Relative time
const formatRelativeTime = (date: Date, locale: string) => {
  const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
  const diff = (date.getTime() - Date.now()) / 1000;
  
  if (Math.abs(diff) < 60) return rtf.format(Math.round(diff), 'second');
  if (Math.abs(diff) < 3600) return rtf.format(Math.round(diff / 60), 'minute');
  // ...
};
// en: "2 hours ago"
// de: "vor 2 Stunden"
```

---

## 21.1.6 RTL Support

```typescript
// Direction-aware styling
const useDirection = () => {
  const { locale } = useLocale();
  return RTL_LOCALES.includes(locale) ? 'rtl' : 'ltr';
};

// CSS logical properties
.sidebar {
  margin-inline-start: 1rem;  /* margin-left in LTR, margin-right in RTL */
  padding-inline-end: 0.5rem; /* padding-right in LTR, padding-left in RTL */
}

// Tailwind RTL utilities
<div className="ml-4 rtl:mr-4 rtl:ml-0">
  {/* ... */}
</div>
```

---

## 21.1.7 Language Detection

```typescript
const detectLanguage = (): string => {
  // 1. Check URL parameter
  const urlLang = new URLSearchParams(location.search).get('lang');
  if (urlLang && SUPPORTED_LOCALES.includes(urlLang)) return urlLang;
  
  // 2. Check localStorage
  const storedLang = localStorage.getItem('language');
  if (storedLang && SUPPORTED_LOCALES.includes(storedLang)) return storedLang;
  
  // 3. Check browser preference
  const browserLang = navigator.language.split('-')[0];
  if (SUPPORTED_LOCALES.includes(browserLang)) return browserLang;
  
  // 4. Default
  return 'en';
};
```

---

## 21.1.8 Translation Workflow

1. **Extraction**: Use `i18next-parser` to extract keys from code
2. **Translation**: Send JSON files to translators
3. **Review**: QA translated strings in context
4. **Deploy**: Load translations asynchronously

```bash
# Extract translation keys
npx i18next-parser

# Validate translations (check for missing keys)
npm run i18n:validate
```

---

## Related Specs

- [Theme System](../10-theme-system/00-overview.md)
- [State Management](../16-state-management/01-state-architecture.md)
- [Mobile Responsive](../14-mobile-responsive/01-responsive-layouts.md)
