# 56. Theming System

## Overview

The theming system provides centralized control over all visual aspects of both admin and frontend interfaces. Themes are seeded from configuration files on first boot and stored in the SQLite `settings.db` database, allowing runtime customization through an admin panel.

---

## 1. Theme Architecture

### 1.1 Three-Tier Theme Hierarchy

```
┌─────────────────────────────────────────────────────────────────┐
│                    THEME CONFIGURATION HIERARCHY                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Tier 1: JSON Seed (config/themes.json)                         │
│  └── Default themes installed on first boot/upgrade             │
│                                                                  │
│  Tier 2: Database (settings.db → theme table)                   │
│  └── Runtime customizations saved by admin                      │
│                                                                  │
│  Tier 3: Class Constants (ThemeConsts.php)                      │
│  └── Ultimate fallback if database unavailable                  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 1.2 Theme Scope

| Scope | Description | Customizable Elements |
|-------|-------------|----------------------|
| `ADMIN` | WordPress admin panel styling | Colors, typography, spacing, form styles |
| `FRONTEND` | Participant-facing pages | Colors, typography, markdown, layouts |
| `SHARED` | Applied to both scopes | Base colors, font families, border radius |

---

## 2. Database Schema

### 2.1 Theme Table

```
Table: eqm_theme
├── id (INTEGER, PK, AUTO)
├── slug (VARCHAR(50), UNIQUE, NOT NULL) -- e.g., 'default', 'dark', 'high-contrast'
├── name (VARCHAR(100), NOT NULL)
├── scope (ENUM: 'ADMIN', 'FRONTEND', 'SHARED')
├── isActive (BOOLEAN, DEFAULT false)
├── isDefault (BOOLEAN, DEFAULT false) -- Cannot be deleted
├── config (JSON, NOT NULL) -- Full theme configuration
├── createdAt (DATETIME)
├── updatedAt (DATETIME)
└── createdBy (INTEGER, FK → user.id, NULLABLE)
```

### 2.2 Theme Override Table

```
Table: eqm_themeOverride
├── id (INTEGER, PK, AUTO)
├── themeId (INTEGER, FK → theme.id)
├── examId (INTEGER, FK → exam.id, NULLABLE) -- Exam-specific override
├── overrideKey (VARCHAR(100)) -- Dot-notation path
├── overrideValue (TEXT) -- JSON value
├── createdAt (DATETIME)
└── updatedAt (DATETIME)
```

---

## 3. Theme Configuration Structure

### 3.1 Complete Theme Config Schema

```json
{
  "meta": {
    "version": "1.0.0",
    "author": "System",
    "description": "Default light theme"
  },
  
  "colors": {
    "primary": {
      "base": "222.2 47.4% 11.2%",
      "foreground": "210 40% 98%",
      "hover": "222.2 47.4% 15%",
      "active": "222.2 47.4% 8%"
    },
    "secondary": {
      "base": "210 40% 96.1%",
      "foreground": "222.2 47.4% 11.2%"
    },
    "accent": {
      "base": "210 40% 96.1%",
      "foreground": "222.2 47.4% 11.2%"
    },
    "background": {
      "base": "0 0% 100%",
      "card": "0 0% 100%",
      "popover": "0 0% 100%",
      "muted": "210 40% 96.1%"
    },
    "foreground": {
      "base": "222.2 84% 4.9%",
      "muted": "215.4 16.3% 46.9%"
    },
    "border": {
      "base": "214.3 31.8% 91.4%",
      "input": "214.3 31.8% 91.4%",
      "ring": "222.2 84% 4.9%"
    },
    "status": {
      "success": "142 76% 36%",
      "warning": "38 92% 50%",
      "error": "0 84.2% 60.2%",
      "info": "199 89% 48%"
    },
    "deadline": {
      "onTrack": "142 76% 36%",
      "softPassed": "48 96% 53%",
      "approaching": "25 95% 53%",
      "passed": "0 84% 60%",
      "locked": "0 72% 51%",
      "expired": "0 0% 9%"
    }
  },
  
  "typography": {
    "fontFamily": {
      "sans": "Inter, system-ui, sans-serif",
      "mono": "JetBrains Mono, monospace",
      "display": "Inter, system-ui, sans-serif"
    },
    "fontSize": {
      "xs": "0.75rem",
      "sm": "0.875rem",
      "base": "1rem",
      "lg": "1.125rem",
      "xl": "1.25rem",
      "2xl": "1.5rem",
      "3xl": "1.875rem",
      "4xl": "2.25rem"
    },
    "fontWeight": {
      "normal": "400",
      "medium": "500",
      "semibold": "600",
      "bold": "700"
    },
    "lineHeight": {
      "tight": "1.25",
      "normal": "1.5",
      "relaxed": "1.75"
    }
  },
  
  "spacing": {
    "unit": "0.25rem",
    "containerPadding": "1.5rem",
    "sectionGap": "2rem",
    "cardPadding": "1.5rem",
    "inputPadding": "0.75rem 1rem"
  },
  
  "borders": {
    "radius": {
      "sm": "0.25rem",
      "md": "0.5rem",
      "lg": "0.75rem",
      "xl": "1rem",
      "full": "9999px"
    },
    "width": {
      "thin": "1px",
      "medium": "2px",
      "thick": "4px"
    }
  },
  
  "shadows": {
    "sm": "0 1px 2px 0 rgb(0 0 0 / 0.05)",
    "md": "0 4px 6px -1px rgb(0 0 0 / 0.1)",
    "lg": "0 10px 15px -3px rgb(0 0 0 / 0.1)",
    "xl": "0 20px 25px -5px rgb(0 0 0 / 0.1)"
  },
  
  "forms": {
    "input": {
      "height": "2.5rem",
      "borderRadius": "0.5rem",
      "borderColor": "var(--border)",
      "focusRing": "2px solid var(--ring)",
      "placeholderColor": "var(--muted-foreground)"
    },
    "textarea": {
      "minHeight": "6rem",
      "maxHeight": "20rem",
      "lineHeight": "1.5"
    },
    "select": {
      "height": "2.5rem",
      "indicatorColor": "var(--foreground)"
    },
    "checkbox": {
      "size": "1.25rem",
      "borderRadius": "0.25rem"
    },
    "button": {
      "height": {
        "sm": "2rem",
        "md": "2.5rem",
        "lg": "3rem"
      },
      "paddingX": {
        "sm": "0.75rem",
        "md": "1rem",
        "lg": "1.5rem"
      }
    }
  },
  
  "markdown": {
    "content": {
      "maxWidth": "65ch",
      "lineHeight": "1.75",
      "paragraphSpacing": "1.5rem"
    },
    "headings": {
      "h1": { "size": "2.25rem", "weight": "700", "marginTop": "2rem" },
      "h2": { "size": "1.875rem", "weight": "600", "marginTop": "1.75rem" },
      "h3": { "size": "1.5rem", "weight": "600", "marginTop": "1.5rem" },
      "h4": { "size": "1.25rem", "weight": "600", "marginTop": "1.25rem" }
    },
    "code": {
      "inline": {
        "background": "var(--muted)",
        "padding": "0.125rem 0.375rem",
        "borderRadius": "0.25rem",
        "fontFamily": "var(--font-mono)"
      },
      "block": {
        "background": "222.2 84% 4.9%",
        "foreground": "210 40% 98%",
        "padding": "1rem",
        "borderRadius": "0.5rem",
        "lineNumbers": true
      }
    },
    "blockquote": {
      "borderLeft": "4px solid var(--primary)",
      "background": "var(--muted)",
      "padding": "1rem 1.5rem"
    },
    "table": {
      "headerBackground": "var(--muted)",
      "borderColor": "var(--border)",
      "cellPadding": "0.75rem 1rem",
      "stripedRows": true
    },
    "list": {
      "markerColor": "var(--primary)",
      "nestedIndent": "1.5rem",
      "itemSpacing": "0.5rem"
    }
  },
  
  "layout": {
    "sidebar": {
      "width": "280px",
      "collapsedWidth": "64px",
      "background": "var(--sidebar-background)"
    },
    "header": {
      "height": "64px",
      "background": "var(--background)"
    },
    "container": {
      "maxWidth": {
        "sm": "640px",
        "md": "768px",
        "lg": "1024px",
        "xl": "1280px"
      }
    },
    "breakpoints": {
      "sm": "640px",
      "md": "768px",
      "lg": "1024px",
      "xl": "1280px"
    }
  },
  
  "animations": {
    "duration": {
      "fast": "150ms",
      "normal": "300ms",
      "slow": "500ms"
    },
    "easing": {
      "default": "cubic-bezier(0.4, 0, 0.2, 1)",
      "in": "cubic-bezier(0.4, 0, 1, 1)",
      "out": "cubic-bezier(0, 0, 0.2, 1)"
    }
  }
}
```

---

## 4. Seed Configuration

### 4.1 Seed File Location

```
config/
├── themes.json          # Theme definitions
├── themes/
│   ├── default.json     # Default light theme
│   ├── dark.json        # Dark theme
│   ├── high-contrast.json
│   └── minimal.json
└── defaults.json        # References active theme
```

### 4.2 themes.json Structure

```json
{
  "version": "1.0.0",
  "activeThemes": {
    "admin": "default",
    "frontend": "default"
  },
  "themes": [
    {
      "slug": "default",
      "name": "Default Light",
      "scope": "SHARED",
      "isDefault": true,
      "configFile": "themes/default.json"
    },
    {
      "slug": "dark",
      "name": "Dark Mode",
      "scope": "SHARED",
      "isDefault": true,
      "configFile": "themes/dark.json"
    },
    {
      "slug": "high-contrast",
      "name": "High Contrast",
      "scope": "SHARED",
      "isDefault": true,
      "configFile": "themes/high-contrast.json"
    }
  ]
}
```

### 4.3 Seeding Logic

```php
class ThemeSeeder
{
    /**
     * CRITICAL: Only runs on:
     * - First installation (no theme table exists)
     * - Version upgrade (config version > db version)
     */
    public function seed(): void
    {
        $configVersion = $this->getConfigVersion();
        $dbVersion = $this->getDbVersion();
        
        if ($this->isFirstInstall()) {
            $this->seedAllThemes();
            return;
        }
        
        if (version_compare($configVersion, $dbVersion, '>')) {
            $this->mergeThemeUpdates();
        }
    }
    
    /**
     * Merge strategy: Only add NEW keys, never overwrite customizations
     */
    private function mergeThemeUpdates(): void
    {
        // Deep merge using dot-notation
        // Existing values preserved
        // New keys from config added
    }
}
```

---

## 5. Theme Service

### 5.1 ThemeService Class

```php
class ThemeService
{
    // Get active theme for scope
    public function getActiveTheme(ThemeScope $scope): Theme;
    
    // Get theme by slug
    public function getBySlug(string $slug): ?Theme;
    
    // Get all themes
    public function getAll(): array;
    
    // Set active theme
    public function setActive(string $slug, ThemeScope $scope): void;
    
    // Update theme config (partial)
    public function updateConfig(string $slug, array $updates): void;
    
    // Reset theme to seed values
    public function resetToDefault(string $slug): void;
    
    // Duplicate theme
    public function duplicate(string $slug, string $newSlug, string $newName): Theme;
    
    // Delete custom theme (not defaults)
    public function delete(string $slug): void;
    
    // Get resolved CSS variables
    public function getCssVariables(ThemeScope $scope): string;
    
    // Get theme value by path
    public function getValue(string $path, ThemeScope $scope): mixed;
}
```

### 5.2 Theme Resolution

```php
/**
 * Resolution order:
 * 1. Exam-specific override (if examId provided)
 * 2. Theme override table
 * 3. Theme config (database)
 * 4. Seed config (fallback)
 * 5. ThemeConsts (ultimate fallback)
 */
public function resolveValue(string $path, ?int $examId = null): mixed
{
    // Check exam override
    if (is_not_null($examId)) {
        $override = $this->getExamOverride($examId, $path);
        if (is_not_null($override)) {
            return $override;
        }
    }
    
    // Check theme override
    $themeOverride = $this->getThemeOverride($path);
    if (is_not_null($themeOverride)) {
        return $themeOverride;
    }
    
    // Get from active theme config
    $theme = $this->getActiveTheme($this->currentScope);
    $value = $this->getNestedValue($theme->config, $path);
    if (is_not_null($value)) {
        return $value;
    }
    
    // Fallback to constants
    return ThemeConsts::getDefault($path);
}
```

---

## 6. Admin Panel UI

### 6.1 Theme Manager Page

```
WordPress Admin → EQM → Settings → Themes

┌─────────────────────────────────────────────────────────────────┐
│ Theme Manager                                    [+ New Theme]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────┬─────────────────┬─────────────────────────┐│
│  │ Available Themes│ Theme Editor    │ Live Preview            ││
│  ├─────────────────┼─────────────────┼─────────────────────────┤│
│  │                 │                 │                         ││
│  │ ● Default Light │ [Colors]        │ ┌─────────────────────┐ ││
│  │   ✓ Active      │ [Typography]    │ │                     │ ││
│  │                 │ [Forms]         │ │   Preview iframe    │ ││
│  │ ○ Dark Mode     │ [Markdown]      │ │   shows real-time   │ ││
│  │                 │ [Layout]        │ │   theme changes     │ ││
│  │ ○ High Contrast │ [Animations]    │ │                     │ ││
│  │                 │                 │ └─────────────────────┘ ││
│  │ ○ Custom Theme  │ ─────────────── │                         ││
│  │   [Edit][Delete]│                 │ Scope: [Admin ▼]        ││
│  │                 │ Primary Color   │        [Frontend ▼]     ││
│  │                 │ [████████] #1e3a│                         ││
│  │                 │                 │ [Apply Theme]           ││
│  │                 │ Border Radius   │ [Reset to Default]      ││
│  │                 │ [─────●───] 8px │                         ││
│  │                 │                 │                         ││
│  └─────────────────┴─────────────────┴─────────────────────────┘│
│                                                                  │
│  [Export Theme JSON]  [Import Theme]  [Reset All to Defaults]   │
└─────────────────────────────────────────────────────────────────┘
```

### 6.2 Color Picker Component

```
┌─────────────────────────────────────────┐
│ Primary Color                           │
├─────────────────────────────────────────┤
│ ┌─────────────────────────────────────┐ │
│ │                                     │ │
│ │         Color Gradient Picker       │ │
│ │                                     │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ Hue:        [──────────●────] 222°      │
│ Saturation: [────●──────────] 47%       │
│ Lightness:  [●────────────────] 11%      │
│                                         │
│ HSL: 222.2 47.4% 11.2%                  │
│ HEX: #1e3a5f                            │
│                                         │
│ Presets: [●][●][●][●][●][●][●][●]      │
│                                         │
│ [Apply] [Cancel]                        │
└─────────────────────────────────────────┘
```

### 6.3 Typography Editor

```
┌─────────────────────────────────────────┐
│ Typography Settings                     │
├─────────────────────────────────────────┤
│                                         │
│ Font Family                             │
│ Sans:    [Inter, system-ui    ▼]        │
│ Mono:    [JetBrains Mono      ▼]        │
│ Display: [Inter               ▼]        │
│                                         │
│ [+ Add Google Font]                     │
│                                         │
│ ─────────────────────────────────────── │
│                                         │
│ Font Sizes                              │
│ Base:  [────●──────] 16px               │
│ Scale: [1.125 - Minor Second ▼]         │
│                                         │
│ Computed sizes:                         │
│ xs: 12px  sm: 14px  base: 16px          │
│ lg: 18px  xl: 20px  2xl: 24px           │
│                                         │
└─────────────────────────────────────────┘
```

### 6.4 Markdown Preview

```
┌─────────────────────────────────────────┐
│ Markdown Styling                        │
├─────────────────────────────────────────┤
│                                         │
│ Content Width                           │
│ [────────●────] 65ch                    │
│                                         │
│ Line Height                             │
│ [Tight] [Normal ●] [Relaxed]            │
│                                         │
│ ─────────────────────────────────────── │
│                                         │
│ Code Block Theme                        │
│ [GitHub Dark ▼]                         │
│                                         │
│ □ Show line numbers                     │
│ □ Enable syntax highlighting            │
│                                         │
│ ─────────────────────────────────────── │
│                                         │
│ Preview:                                │
│ ┌─────────────────────────────────────┐ │
│ │ # Heading 1                         │ │
│ │                                     │ │
│ │ This is paragraph text with        │ │
│ │ `inline code` and **bold**.        │ │
│ │                                     │ │
│ │ ```javascript                       │ │
│ │ const x = 42;                       │ │
│ │ ```                                 │ │
│ └─────────────────────────────────────┘ │
└─────────────────────────────────────────┘
```

---

## 7. Frontend Theme Application

### 7.1 CSS Variable Injection

```php
// In header template
public function injectThemeStyles(): void
{
    $theme = $this->themeService->getActiveTheme(ThemeScope::FRONTEND);
    $css = $this->themeService->getCssVariables(ThemeScope::FRONTEND);
    
    echo "<style id='eqm-theme-variables'>{$css}</style>";
}
```

### 7.2 Generated CSS Output

```css
:root {
  /* Colors - Primary */
  --primary: 222.2 47.4% 11.2%;
  --primary-foreground: 210 40% 98%;
  --primary-hover: 222.2 47.4% 15%;
  
  /* Colors - Background */
  --background: 0 0% 100%;
  --card: 0 0% 100%;
  --muted: 210 40% 96.1%;
  
  /* Typography */
  --font-sans: Inter, system-ui, sans-serif;
  --font-mono: JetBrains Mono, monospace;
  --text-base: 1rem;
  --line-height-normal: 1.5;
  
  /* Spacing */
  --spacing-unit: 0.25rem;
  --container-padding: 1.5rem;
  
  /* Borders */
  --radius-md: 0.5rem;
  --border-thin: 1px;
  
  /* Forms */
  --input-height: 2.5rem;
  --input-radius: 0.5rem;
  
  /* Markdown */
  --prose-max-width: 65ch;
  --prose-line-height: 1.75;
  
  /* Deadline Colors */
  --deadline-on-track: 142 76% 36%;
  --deadline-soft-passed: 48 96% 53%;
  --deadline-approaching: 25 95% 53%;
  --deadline-passed: 0 84% 60%;
  --deadline-locked: 0 72% 51%;
  --deadline-expired: 0 0% 9%;
}

/* Dark mode overrides */
.dark {
  --background: 222.2 84% 4.9%;
  --foreground: 210 40% 98%;
  /* ... */
}
```

### 7.3 REST API Endpoint

```
GET /wp-json/eqm/v1/theme
Response:
{
  "scope": "FRONTEND",
  "theme": {
    "slug": "default",
    "name": "Default Light",
    "css": ":root { --primary: 222.2 47.4% 11.2%; ... }",
    "config": { ... }
  }
}
```

---

## 8. Exam-Level Theme Overrides

### 8.1 Per-Exam Customization

```
Exam Editor → Metadata Tab → Theme Settings

┌─────────────────────────────────────────┐
│ Theme Settings                          │
├─────────────────────────────────────────┤
│                                         │
│ Theme: [Default ▼] [Use Global Theme]   │
│                                         │
│ Overrides:                              │
│ ┌─────────────────────────────────────┐ │
│ │ □ Primary Color    [████████]       │ │
│ │ □ Header Logo      [Upload]         │ │
│ │ □ Custom CSS       [Edit...]        │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ [Preview with Overrides]                │
└─────────────────────────────────────────┘
```

---

## 9. Theme Import/Export

### 9.1 Export Format

```json
{
  "exportVersion": "1.0.0",
  "exportedAt": "2024-01-15T10:30:00Z",
  "theme": {
    "slug": "custom-brand",
    "name": "Custom Brand Theme",
    "scope": "SHARED",
    "config": { ... }
  },
  "overrides": [
    {
      "examSlug": "certification-2024",
      "overrides": { ... }
    }
  ]
}
```

---

## 10. Common Pitfalls

### ❌ WRONG: Hardcoded Colors

```php
// WRONG
echo '<div style="color: #1e3a5f;">';
```

### ✅ CORRECT: Theme Variables

```php
// CORRECT
echo '<div style="color: hsl(var(--primary));">';
```

### ❌ WRONG: Direct Database Access

```php
// WRONG
$theme = $wpdb->get_row("SELECT * FROM eqm_theme WHERE slug = 'default'");
```

### ✅ CORRECT: Service Layer

```php
// CORRECT
$theme = $this->themeService->getBySlug('default');
```

### ❌ WRONG: Ignoring Scope

```php
// WRONG - gets wrong theme for context
$theme = $this->themeService->getActiveTheme();
```

### ✅ CORRECT: Explicit Scope

```php
// CORRECT
$theme = $this->themeService->getActiveTheme(ThemeScope::FRONTEND);
```

---

## 11. Cross-References

- **Database Schema**: `04-database-schema.md` (theme tables)
- **Seeding Logic**: `07-logging-system.md` (config hierarchy)
- **Admin UI**: `37-admin-dashboard.md` (settings panel)
- **Frontend Application**: `02-frontend/split-spec/31-theme-application.md`
- **Shared Constants**: `../../66-shared-constants.md` (Theme default values)
