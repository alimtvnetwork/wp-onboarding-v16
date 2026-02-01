# 10.1 Theme Provider

**Version:** 1.0.0  
**Status:** Planned  
**Last Updated:** 2026-01-28

---

## Overview

Context-based theme management system supporting multiple visual themes with HSL-based color tokens, persistent preferences, and real-time switching without page reload.

**Cross-References:**
- [Component Library](./02-component-library.md) - Themed UI components
- [Coding Guidelines](../../04-coding-guidelines/00-overview.md) - Design system standards
- [State Management](../16-state-management/00-overview.md) - Theme state persistence

---

## 10.1.1 Supported Themes

| Theme ID | Name | Description |
|----------|------|-------------|
| `light` | Light | Clean, bright interface with high contrast |
| `dark` | Dark | Low-light optimized with reduced eye strain |
| `ocean` | Ocean | Blue-tinted professional theme |
| `forest` | Forest | Green-tinted nature-inspired theme |

---

## 10.1.2 Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      ThemeProvider                          │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  ThemeContext                                        │   │
│  │  - currentTheme: ThemeId                            │   │
│  │  - setTheme: (theme: ThemeId) => void              │   │
│  │  - toggleTheme: () => void                          │   │
│  │  - systemPreference: 'light' | 'dark'              │   │
│  └─────────────────────────────────────────────────────┘   │
│                           │                                 │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  CSS Variable Injection                              │   │
│  │  - :root { --background: hsl(...) }                 │   │
│  │  - :root { --foreground: hsl(...) }                 │   │
│  │  - :root { --primary: hsl(...) }                    │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## 10.1.3 TypeScript Interface

```typescript
type ThemeId = 'light' | 'dark' | 'ocean' | 'forest';

interface ThemeConfig {
  id: ThemeId;
  name: string;
  colors: {
    background: string;      // HSL values
    foreground: string;
    card: string;
    cardForeground: string;
    popover: string;
    popoverForeground: string;
    primary: string;
    primaryForeground: string;
    secondary: string;
    secondaryForeground: string;
    muted: string;
    mutedForeground: string;
    accent: string;
    accentForeground: string;
    destructive: string;
    destructiveForeground: string;
    border: string;
    input: string;
    ring: string;
  };
}

interface ThemeContextValue {
  theme: ThemeId;
  setTheme: (theme: ThemeId) => void;
  toggleTheme: () => void;
  resolvedTheme: ThemeId;
  systemTheme: 'light' | 'dark';
}
```

---

## 10.1.4 useTheme Hook

```typescript
function useTheme(): ThemeContextValue;

// Usage
const { theme, setTheme, toggleTheme } = useTheme();

// Switch to dark theme
setTheme('dark');

// Cycle through themes
toggleTheme();
```

---

## 10.1.5 Persistence Strategy

| Storage | Purpose | Sync |
|---------|---------|------|
| localStorage | Quick client-side access | Immediate |
| Database (UserSettings) | Cross-device sync | On auth |
| System preference | Fallback detection | Auto |

**Priority Order:**
1. User explicit selection (DB/localStorage)
2. System preference (`prefers-color-scheme`)
3. Default: `light`

---

## 10.1.6 CSS Variable Tokens

```css
:root {
  /* Base colors - HSL format */
  --background: 0 0% 100%;
  --foreground: 222.2 84% 4.9%;
  
  /* Component colors */
  --card: 0 0% 100%;
  --card-foreground: 222.2 84% 4.9%;
  --popover: 0 0% 100%;
  --popover-foreground: 222.2 84% 4.9%;
  
  /* Brand colors */
  --primary: 222.2 47.4% 11.2%;
  --primary-foreground: 210 40% 98%;
  
  /* State colors */
  --muted: 210 40% 96.1%;
  --muted-foreground: 215.4 16.3% 46.9%;
  --accent: 210 40% 96.1%;
  --accent-foreground: 222.2 47.4% 11.2%;
  
  /* Semantic colors */
  --destructive: 0 84.2% 60.2%;
  --destructive-foreground: 210 40% 98%;
  
  /* Border & input */
  --border: 214.3 31.8% 91.4%;
  --input: 214.3 31.8% 91.4%;
  --ring: 222.2 84% 4.9%;
  
  /* Radius */
  --radius: 0.5rem;
}

.dark {
  --background: 222.2 84% 4.9%;
  --foreground: 210 40% 98%;
  /* ... dark overrides */
}

.ocean {
  --background: 210 50% 98%;
  --primary: 210 100% 40%;
  /* ... ocean overrides */
}

.forest {
  --background: 120 20% 98%;
  --primary: 142 76% 36%;
  /* ... forest overrides */
}
```

---

## 10.1.7 Theme Transition Animation

```css
* {
  transition: background-color 0.2s ease-in-out,
              border-color 0.2s ease-in-out,
              color 0.15s ease-in-out;
}

/* Disable transitions during theme switch to prevent flash */
.theme-switching * {
  transition: none !important;
}
```

---

## 10.1.8 Server-Side Rendering Support

```typescript
// Prevent flash of wrong theme
const ThemeScript = () => (
  <script
    dangerouslySetInnerHTML={{
      __html: `
        (function() {
          const stored = localStorage.getItem('theme');
          const system = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
          const theme = stored || system;
          document.documentElement.classList.add(theme);
        })();
      `,
    }}
  />
);
```

---

## Related Specs

- [Component Library](./02-component-library.md)
- [Coding Guidelines](../../04-coding-guidelines/00-overview.md)
- [Database Schema](../../07-database-design/01-schema.md)
