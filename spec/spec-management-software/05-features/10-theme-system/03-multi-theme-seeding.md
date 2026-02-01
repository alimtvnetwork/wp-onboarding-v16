# Multi-Theme Seeding System

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Extended theme system with multiple pre-seeded color themes beyond light/dark. Themes are stored as seeded database records and CSS variable definitions, allowing users to switch themes in-app and administrators to add custom themes without code changes.

**Cross-References:**
- [Theme Provider](./01-theme-provider.md) - Core theme context
- [Component Library](./02-component-library.md) - Themed components
- [Configuration](../24-code-generation-system/08-configuration.md) - Theme settings
- [Settings System](../24-code-generation-system/23-settings-system.md) - Theme selection UI

---

## Supported Themes

### Core Themes (Always Available)

| ID | Name | Description | Base |
|----|------|-------------|------|
| `light` | Light | Clean white background, high contrast | Light |
| `dark` | Dark | Low-light optimized, reduced eye strain | Dark |

### Extended Themes (Seeded)

| ID | Name | Description | Base |
|----|------|-------------|------|
| `ocean` | Ocean Blue | Professional blue-tinted theme | Light |
| `forest` | Forest Green | Nature-inspired green accents | Light |
| `midnight` | Midnight | Deep blue-black, subtle highlights | Dark |
| `sunset` | Sunset | Warm orange and amber tones | Light |
| `nord` | Nord | Arctic, bluish color palette | Dark |
| `rose` | Rosé | Soft pink and rose gold accents | Light |
| `dracula` | Dracula | Popular purple dark theme | Dark |
| `solarized-light` | Solarized Light | Low-contrast warm light theme | Light |
| `solarized-dark` | Solarized Dark | Low-contrast warm dark theme | Dark |
| `monokai` | Monokai | Classic code editor dark theme | Dark |

---

## Database Schema

### Theme Table

```sql
CREATE TABLE Theme (
    Id TEXT PRIMARY KEY,                -- Theme identifier (slug)
    Name TEXT NOT NULL,                 -- Display name
    Description TEXT,                   -- Theme description
    BaseTheme TEXT NOT NULL CHECK (BaseTheme IN ('light', 'dark')),
    
    -- Color Tokens (HSL format: "H S% L%")
    ColorBackground TEXT NOT NULL,
    ColorForeground TEXT NOT NULL,
    ColorCard TEXT NOT NULL,
    ColorCardForeground TEXT NOT NULL,
    ColorPopover TEXT NOT NULL,
    ColorPopoverForeground TEXT NOT NULL,
    ColorPrimary TEXT NOT NULL,
    ColorPrimaryForeground TEXT NOT NULL,
    ColorSecondary TEXT NOT NULL,
    ColorSecondaryForeground TEXT NOT NULL,
    ColorMuted TEXT NOT NULL,
    ColorMutedForeground TEXT NOT NULL,
    ColorAccent TEXT NOT NULL,
    ColorAccentForeground TEXT NOT NULL,
    ColorDestructive TEXT NOT NULL,
    ColorDestructiveForeground TEXT NOT NULL,
    ColorBorder TEXT NOT NULL,
    ColorInput TEXT NOT NULL,
    ColorRing TEXT NOT NULL,
    
    -- Additional Tokens
    ColorSuccess TEXT,                  -- Success state color
    ColorWarning TEXT,                  -- Warning state color
    ColorInfo TEXT,                     -- Info state color
    
    -- Radius & Shadows
    RadiusBase TEXT DEFAULT '0.5rem',
    ShadowSm TEXT,
    ShadowMd TEXT,
    ShadowLg TEXT,
    
    -- Metadata
    IsBuiltIn INTEGER NOT NULL DEFAULT 0,   -- System theme (non-deletable)
    IsDefault INTEGER NOT NULL DEFAULT 0,   -- Default for new users
    SortOrder INTEGER NOT NULL DEFAULT 100,
    
    -- Timestamps
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IX_Theme_BaseTheme ON Theme(BaseTheme);
CREATE INDEX IX_Theme_SortOrder ON Theme(SortOrder);
```

### UserThemePreference Table

```sql
CREATE TABLE UserThemePreference (
    Id TEXT PRIMARY KEY,
    UserId TEXT NOT NULL UNIQUE,
    ThemeId TEXT NOT NULL,
    UseSystemPreference INTEGER NOT NULL DEFAULT 0,  -- Auto light/dark
    LightThemeId TEXT,                               -- Theme when system=light
    DarkThemeId TEXT,                                -- Theme when system=dark
    
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    
    FOREIGN KEY (UserId) REFERENCES User(Id) ON DELETE CASCADE,
    FOREIGN KEY (ThemeId) REFERENCES Theme(Id) ON DELETE SET NULL,
    FOREIGN KEY (LightThemeId) REFERENCES Theme(Id) ON DELETE SET NULL,
    FOREIGN KEY (DarkThemeId) REFERENCES Theme(Id) ON DELETE SET NULL
);
```

---

## Seeding Configuration

### Theme Seed File

```yaml
# config/seeds/themes.yaml
themes:
  - id: light
    name: Light
    description: Clean white background with high contrast
    baseTheme: light
    isBuiltIn: true
    isDefault: true
    sortOrder: 1
    colors:
      background: "0 0% 100%"
      foreground: "222.2 84% 4.9%"
      card: "0 0% 100%"
      cardForeground: "222.2 84% 4.9%"
      popover: "0 0% 100%"
      popoverForeground: "222.2 84% 4.9%"
      primary: "222.2 47.4% 11.2%"
      primaryForeground: "210 40% 98%"
      secondary: "210 40% 96.1%"
      secondaryForeground: "222.2 47.4% 11.2%"
      muted: "210 40% 96.1%"
      mutedForeground: "215.4 16.3% 46.9%"
      accent: "210 40% 96.1%"
      accentForeground: "222.2 47.4% 11.2%"
      destructive: "0 84.2% 60.2%"
      destructiveForeground: "210 40% 98%"
      border: "214.3 31.8% 91.4%"
      input: "214.3 31.8% 91.4%"
      ring: "222.2 84% 4.9%"

  - id: dark
    name: Dark
    description: Low-light optimized with reduced eye strain
    baseTheme: dark
    isBuiltIn: true
    sortOrder: 2
    colors:
      background: "222.2 84% 4.9%"
      foreground: "210 40% 98%"
      card: "222.2 84% 4.9%"
      cardForeground: "210 40% 98%"
      popover: "222.2 84% 4.9%"
      popoverForeground: "210 40% 98%"
      primary: "210 40% 98%"
      primaryForeground: "222.2 47.4% 11.2%"
      secondary: "217.2 32.6% 17.5%"
      secondaryForeground: "210 40% 98%"
      muted: "217.2 32.6% 17.5%"
      mutedForeground: "215 20.2% 65.1%"
      accent: "217.2 32.6% 17.5%"
      accentForeground: "210 40% 98%"
      destructive: "0 62.8% 30.6%"
      destructiveForeground: "210 40% 98%"
      border: "217.2 32.6% 17.5%"
      input: "217.2 32.6% 17.5%"
      ring: "212.7 26.8% 83.9%"

  - id: ocean
    name: Ocean Blue
    description: Professional blue-tinted theme
    baseTheme: light
    isBuiltIn: true
    sortOrder: 3
    colors:
      background: "210 50% 98%"
      foreground: "210 50% 10%"
      card: "210 50% 100%"
      cardForeground: "210 50% 10%"
      popover: "210 50% 100%"
      popoverForeground: "210 50% 10%"
      primary: "210 100% 40%"
      primaryForeground: "0 0% 100%"
      secondary: "210 30% 90%"
      secondaryForeground: "210 50% 20%"
      muted: "210 30% 92%"
      mutedForeground: "210 20% 45%"
      accent: "200 100% 45%"
      accentForeground: "0 0% 100%"
      destructive: "0 84% 60%"
      destructiveForeground: "0 0% 100%"
      border: "210 30% 85%"
      input: "210 30% 88%"
      ring: "210 100% 40%"

  - id: midnight
    name: Midnight
    description: Deep blue-black with subtle highlights
    baseTheme: dark
    isBuiltIn: true
    sortOrder: 4
    colors:
      background: "222 47% 8%"
      foreground: "213 31% 91%"
      card: "222 47% 11%"
      cardForeground: "213 31% 91%"
      popover: "222 47% 11%"
      popoverForeground: "213 31% 91%"
      primary: "217 91% 60%"
      primaryForeground: "222 47% 8%"
      secondary: "222 47% 15%"
      secondaryForeground: "213 31% 91%"
      muted: "223 47% 15%"
      mutedForeground: "215 20% 55%"
      accent: "217 91% 65%"
      accentForeground: "222 47% 8%"
      destructive: "0 63% 31%"
      destructiveForeground: "213 31% 91%"
      border: "222 47% 18%"
      input: "222 47% 18%"
      ring: "217 91% 60%"

  - id: forest
    name: Forest Green
    description: Nature-inspired green accents
    baseTheme: light
    isBuiltIn: true
    sortOrder: 5
    colors:
      background: "120 20% 98%"
      foreground: "140 30% 10%"
      card: "120 20% 100%"
      cardForeground: "140 30% 10%"
      popover: "120 20% 100%"
      popoverForeground: "140 30% 10%"
      primary: "142 76% 36%"
      primaryForeground: "0 0% 100%"
      secondary: "120 25% 92%"
      secondaryForeground: "140 30% 15%"
      muted: "120 20% 93%"
      mutedForeground: "140 15% 45%"
      accent: "152 69% 42%"
      accentForeground: "0 0% 100%"
      destructive: "0 84% 60%"
      destructiveForeground: "0 0% 100%"
      border: "120 20% 85%"
      input: "120 20% 88%"
      ring: "142 76% 36%"

  - id: nord
    name: Nord
    description: Arctic, bluish color palette
    baseTheme: dark
    isBuiltIn: true
    sortOrder: 6
    colors:
      background: "220 16% 22%"
      foreground: "218 27% 92%"
      card: "220 17% 26%"
      cardForeground: "218 27% 92%"
      popover: "220 17% 26%"
      popoverForeground: "218 27% 92%"
      primary: "193 43% 67%"
      primaryForeground: "220 16% 22%"
      secondary: "220 16% 32%"
      secondaryForeground: "218 27% 92%"
      muted: "220 16% 28%"
      mutedForeground: "219 28% 66%"
      accent: "179 25% 65%"
      accentForeground: "220 16% 22%"
      destructive: "354 42% 56%"
      destructiveForeground: "218 27% 92%"
      border: "220 17% 32%"
      input: "220 17% 32%"
      ring: "193 43% 67%"

  - id: dracula
    name: Dracula
    description: Popular purple dark theme
    baseTheme: dark
    isBuiltIn: true
    sortOrder: 7
    colors:
      background: "231 15% 18%"
      foreground: "60 30% 96%"
      card: "232 14% 20%"
      cardForeground: "60 30% 96%"
      popover: "232 14% 20%"
      popoverForeground: "60 30% 96%"
      primary: "265 89% 78%"
      primaryForeground: "231 15% 18%"
      secondary: "232 14% 25%"
      secondaryForeground: "60 30% 96%"
      muted: "232 14% 25%"
      mutedForeground: "225 14% 58%"
      accent: "326 100% 74%"
      accentForeground: "231 15% 18%"
      destructive: "0 100% 67%"
      destructiveForeground: "60 30% 96%"
      border: "232 14% 28%"
      input: "232 14% 28%"
      ring: "265 89% 78%"

  - id: rose
    name: Rosé
    description: Soft pink and rose gold accents
    baseTheme: light
    isBuiltIn: true
    sortOrder: 8
    colors:
      background: "350 30% 99%"
      foreground: "350 25% 15%"
      card: "0 0% 100%"
      cardForeground: "350 25% 15%"
      popover: "0 0% 100%"
      popoverForeground: "350 25% 15%"
      primary: "346 77% 50%"
      primaryForeground: "0 0% 100%"
      secondary: "350 30% 94%"
      secondaryForeground: "350 25% 25%"
      muted: "350 20% 95%"
      mutedForeground: "350 15% 50%"
      accent: "12 76% 61%"
      accentForeground: "0 0% 100%"
      destructive: "0 84% 60%"
      destructiveForeground: "0 0% 100%"
      border: "350 20% 88%"
      input: "350 20% 90%"
      ring: "346 77% 50%"
```

---

## GORM Models

```go
package models

import "time"

// Theme represents a visual theme configuration
type Theme struct {
    Id          string `gorm:"primaryKey;type:TEXT"`
    Name        string `gorm:"type:TEXT;not null"`
    Description string `gorm:"type:TEXT"`
    BaseTheme   string `gorm:"type:TEXT;not null"` // light, dark
    
    // Colors (HSL format)
    ColorBackground          string `gorm:"type:TEXT;not null"`
    ColorForeground          string `gorm:"type:TEXT;not null"`
    ColorCard                string `gorm:"type:TEXT;not null"`
    ColorCardForeground      string `gorm:"type:TEXT;not null"`
    ColorPopover             string `gorm:"type:TEXT;not null"`
    ColorPopoverForeground   string `gorm:"type:TEXT;not null"`
    ColorPrimary             string `gorm:"type:TEXT;not null"`
    ColorPrimaryForeground   string `gorm:"type:TEXT;not null"`
    ColorSecondary           string `gorm:"type:TEXT;not null"`
    ColorSecondaryForeground string `gorm:"type:TEXT;not null"`
    ColorMuted               string `gorm:"type:TEXT;not null"`
    ColorMutedForeground     string `gorm:"type:TEXT;not null"`
    ColorAccent              string `gorm:"type:TEXT;not null"`
    ColorAccentForeground    string `gorm:"type:TEXT;not null"`
    ColorDestructive         string `gorm:"type:TEXT;not null"`
    ColorDestructiveForeground string `gorm:"type:TEXT;not null"`
    ColorBorder              string `gorm:"type:TEXT;not null"`
    ColorInput               string `gorm:"type:TEXT;not null"`
    ColorRing                string `gorm:"type:TEXT;not null"`
    
    // Optional colors
    ColorSuccess *string `gorm:"type:TEXT"`
    ColorWarning *string `gorm:"type:TEXT"`
    ColorInfo    *string `gorm:"type:TEXT"`
    
    // Radius & Shadows
    RadiusBase string  `gorm:"type:TEXT;default:'0.5rem'"`
    ShadowSm   *string `gorm:"type:TEXT"`
    ShadowMd   *string `gorm:"type:TEXT"`
    ShadowLg   *string `gorm:"type:TEXT"`
    
    // Metadata
    IsBuiltIn bool `gorm:"not null;default:false"`
    IsDefault bool `gorm:"not null;default:false"`
    SortOrder int  `gorm:"not null;default:100"`
    
    CreatedAt time.Time `gorm:"not null"`
    UpdatedAt time.Time `gorm:"not null"`
}

// UserThemePreference stores user theme selection
type UserThemePreference struct {
    Id                  string  `gorm:"primaryKey;type:TEXT"`
    UserId              string  `gorm:"type:TEXT;not null;uniqueIndex"`
    ThemeId             string  `gorm:"type:TEXT;not null"`
    UseSystemPreference bool    `gorm:"not null;default:false"`
    LightThemeId        *string `gorm:"type:TEXT"`
    DarkThemeId         *string `gorm:"type:TEXT"`
    
    User       *User  `gorm:"foreignKey:UserId"`
    Theme      *Theme `gorm:"foreignKey:ThemeId"`
    LightTheme *Theme `gorm:"foreignKey:LightThemeId"`
    DarkTheme  *Theme `gorm:"foreignKey:DarkThemeId"`
    
    CreatedAt time.Time `gorm:"not null"`
    UpdatedAt time.Time `gorm:"not null"`
}
```

---

## Theme Service

```go
package themes

import (
    "context"
    "fmt"
)

// ThemeService manages theme operations
type ThemeService struct {
    themeRepo    ThemeRepository
    prefRepo     UserThemePreferenceRepository
    cssGenerator CSSGenerator
}

// GetAllThemes returns all available themes
func (s *ThemeService) GetAllThemes(ctx context.Context) ([]Theme, error) {
    return s.themeRepo.FindAllOrdered(ctx)
}

// GetUserTheme returns the theme for a user
func (s *ThemeService) GetUserTheme(ctx context.Context, userId string) (*Theme, error) {
    pref, err := s.prefRepo.FindByUserId(ctx, userId)
    if err != nil {
        // Return default theme
        return s.themeRepo.FindDefault(ctx)
    }
    return s.themeRepo.FindById(ctx, pref.ThemeId)
}

// SetUserTheme updates user theme preference
func (s *ThemeService) SetUserTheme(ctx context.Context, userId, themeId string) error {
    // Validate theme exists
    if _, err := s.themeRepo.FindById(ctx, themeId); err != nil {
        return fmt.Errorf("theme not found: %s", themeId)
    }
    
    return s.prefRepo.Upsert(ctx, &UserThemePreference{
        UserId:  userId,
        ThemeId: themeId,
    })
}

// GenerateCSS generates CSS variables for a theme
func (s *ThemeService) GenerateCSS(theme *Theme) string {
    return fmt.Sprintf(`.%s {
  --background: %s;
  --foreground: %s;
  --card: %s;
  --card-foreground: %s;
  --popover: %s;
  --popover-foreground: %s;
  --primary: %s;
  --primary-foreground: %s;
  --secondary: %s;
  --secondary-foreground: %s;
  --muted: %s;
  --muted-foreground: %s;
  --accent: %s;
  --accent-foreground: %s;
  --destructive: %s;
  --destructive-foreground: %s;
  --border: %s;
  --input: %s;
  --ring: %s;
  --radius: %s;
}`,
        theme.Id,
        theme.ColorBackground,
        theme.ColorForeground,
        theme.ColorCard,
        theme.ColorCardForeground,
        theme.ColorPopover,
        theme.ColorPopoverForeground,
        theme.ColorPrimary,
        theme.ColorPrimaryForeground,
        theme.ColorSecondary,
        theme.ColorSecondaryForeground,
        theme.ColorMuted,
        theme.ColorMutedForeground,
        theme.ColorAccent,
        theme.ColorAccentForeground,
        theme.ColorDestructive,
        theme.ColorDestructiveForeground,
        theme.ColorBorder,
        theme.ColorInput,
        theme.ColorRing,
        theme.RadiusBase,
    )
}
```

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/themes` | List all themes |
| GET | `/api/v1/themes/{id}` | Get theme details |
| POST | `/api/v1/themes` | Create custom theme |
| PUT | `/api/v1/themes/{id}` | Update theme |
| DELETE | `/api/v1/themes/{id}` | Delete theme (non-builtin) |
| GET | `/api/v1/user/theme` | Get user preference |
| PUT | `/api/v1/user/theme` | Set user preference |
| GET | `/api/v1/themes/css` | Get CSS for all themes |

---

## Frontend Integration

### Theme Selector Component

```typescript
interface ThemeSelectorProps {
  themes: Theme[];
  currentThemeId: string;
  onThemeChange: (themeId: string) => void;
  showPreview?: boolean;
}

// Theme preview card with color swatches
interface ThemePreviewCardProps {
  theme: Theme;
  isSelected: boolean;
  onClick: () => void;
}
```

### Theme Application

```typescript
function applyTheme(themeId: string) {
  // Remove all theme classes
  document.documentElement.classList.remove(
    'light', 'dark', 'ocean', 'midnight', 'forest', 'nord', 'dracula', 'rose'
  );
  
  // Add new theme class
  document.documentElement.classList.add(themeId);
  
  // Persist to localStorage for fast load
  localStorage.setItem('theme', themeId);
  
  // Sync to backend
  api.setUserTheme(themeId);
}
```

---

## Related Specifications

- [Theme Provider](./01-theme-provider.md)
- [Component Library](./02-component-library.md)
- [Settings System](../24-code-generation-system/23-settings-system.md)
- [Seeding System](../07-database-design/03-seeding.md)
