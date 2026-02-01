# 04. Keyboard Shortcuts Standard

## Overview
Universal keyboard shortcuts standard applicable across all projects (Spec Management Software, WordPress plugins, web applications). Defines patterns for shortcut management, persistence, UI display, and accessibility.

---

## 04.1 Core Principles

### Platform Awareness
- **Mac**: Use `⌘` (Command) as primary modifier
- **Windows/Linux**: Use `Ctrl` as primary modifier
- **Universal notation**: Use `mod` in code to represent platform-specific modifier

### Shortcut Categories

| Category | Prefix | Examples |
|----------|--------|----------|
| Global | None | `mod+k`, `mod+,` |
| Navigation | None | `mod+tab`, `alt+left` |
| Editing | None | `mod+s`, `mod+z` |
| View | `mod+shift` | `mod+shift+p` (preview) |
| Panels | `mod+j/b` | Toggle panels |
| Help | `F1`, `mod+/` | Documentation |

### Conflict Resolution Priority
1. Modal/dialog shortcuts (highest)
2. Focused component shortcuts
3. Page-level shortcuts
4. Global shortcuts (lowest)

---

## 04.2 Shortcut Persistence Schema

### Database Schema (SQLite)

```sql
-- User shortcut preferences
CREATE TABLE user_shortcuts (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    shortcut_id TEXT NOT NULL,
    custom_keys TEXT NOT NULL,          -- JSON array of key combinations
    disabled INTEGER DEFAULT 0,          -- 1 = disabled
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(user_id, shortcut_id)
);

-- Default shortcuts registry
CREATE TABLE shortcut_definitions (
    id TEXT PRIMARY KEY,
    category TEXT NOT NULL,
    action TEXT NOT NULL,
    description TEXT NOT NULL,
    default_keys TEXT NOT NULL,          -- JSON array
    mac_keys TEXT,                       -- Override for Mac
    windows_keys TEXT,                   -- Override for Windows
    context TEXT DEFAULT 'always',       -- When shortcut is active
    customizable INTEGER DEFAULT 1,      -- Can user modify?
    priority INTEGER DEFAULT 0           -- Conflict resolution
);

-- Index for quick lookups
CREATE INDEX idx_user_shortcuts_user ON user_shortcuts(user_id);
CREATE INDEX idx_shortcut_defs_category ON shortcut_definitions(category);
```

### API Endpoints

```
GET    /api/shortcuts                    -- List all shortcuts with user overrides
GET    /api/shortcuts/:category          -- Get shortcuts by category
PUT    /api/shortcuts/:id                -- Update user's shortcut
DELETE /api/shortcuts/:id                -- Reset shortcut to default
POST   /api/shortcuts/reset-all          -- Reset all to defaults
GET    /api/shortcuts/export             -- Export user shortcuts
POST   /api/shortcuts/import             -- Import shortcuts
```

### Response Schema

```typescript
interface ShortcutResponse {
  id: string;
  category: string;
  action: string;
  description: string;
  defaultKeys: string[];
  currentKeys: string[];        // User's current binding
  isCustomized: boolean;
  customizable: boolean;
  context: ShortcutContext;
}

interface ShortcutUpdateRequest {
  keys: string[];
}

interface ShortcutConflict {
  existingId: string;
  existingAction: string;
  keys: string[];
}
```

---

## 04.3 UI Display Standards

### Key Symbol Mapping

| Key | Mac Display | Windows Display | Code Notation |
|-----|-------------|-----------------|---------------|
| Command/Ctrl | ⌘ | Ctrl | `mod` |
| Option/Alt | ⌥ | Alt | `alt` |
| Shift | ⇧ | Shift | `shift` |
| Enter | ↵ | Enter | `enter` |
| Backspace | ⌫ | Backspace | `backspace` |
| Delete | ⌦ | Del | `delete` |
| Escape | Esc | Esc | `escape` |
| Space | ␣ | Space | `space` |
| Tab | ⇥ | Tab | `tab` |
| Arrows | ↑↓←→ | ↑↓←→ | `up/down/left/right` |

### Visual Components

#### Keyboard Key Badge

```
┌─────────────────────────────────────────┐
│  Appearance:                            │
│                                         │
│  ┌───┐ ┌───┐ ┌───┐                     │
│  │ ⌘ │+│ S │      (Mac)                │
│  └───┘ └───┘                            │
│                                         │
│  ┌──────┐ ┌───┐ ┌───┐                  │
│  │ Ctrl │+│ S │      (Windows)         │
│  └──────┘ └───┘                         │
│                                         │
│  Styling:                               │
│  - Background: muted                    │
│  - Border: subtle border with shadow    │
│  - Font: monospace                      │
│  - Padding: compact                     │
│  - Border-radius: rounded-sm            │
└─────────────────────────────────────────┘
```

#### Tooltip Display

```
┌─────────────────────────────────────────┐
│  Button or menu item                    │
│  ┌───────────────────────────────────┐  │
│  │                                   │  │
│  └───────────────────────────────────┘  │
│           ↓                             │
│  ┌───────────────────────────────────┐  │
│  │ Save file        ┌───┐ ┌───┐     │  │
│  │                  │ ⌘ │ │ S │     │  │
│  │                  └───┘ └───┘     │  │
│  └───────────────────────────────────┘  │
│                                         │
│  - Show after 300ms delay               │
│  - Description on left                  │
│  - Shortcut keys on right               │
└─────────────────────────────────────────┘
```

#### Shortcuts Overlay/Modal

```
┌─────────────────────────────────────────────────────────┐
│  ┌───────────────────────────────────────────────────┐  │
│  │  Keyboard Shortcuts                           ✕   │  │
│  │  ─────────────────────────────────────────────────│  │
│  │  🔍 Search shortcuts...                           │  │
│  │  ─────────────────────────────────────────────────│  │
│  │                                                   │  │
│  │  GLOBAL                                           │  │
│  │  ┌─────────────────────────────────────────────┐  │  │
│  │  │ Command Palette              ⌘  K           │  │  │
│  │  │ Quick Search                 ⌘  P           │  │  │
│  │  │ Settings                     ⌘  ,           │  │  │
│  │  └─────────────────────────────────────────────┘  │  │
│  │                                                   │  │
│  │  EDITOR                                           │  │
│  │  ┌─────────────────────────────────────────────┐  │  │
│  │  │ Save                         ⌘  S           │  │  │
│  │  │ Undo                         ⌘  Z           │  │  │
│  │  │ Find                         ⌘  F           │  │  │
│  │  └─────────────────────────────────────────────┘  │  │
│  │                                                   │  │
│  │  ─────────────────────────────────────────────────│  │
│  │  [ Customize Shortcuts ]                          │  │
│  └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

---

## 04.4 Settings UI Pattern

### Shortcut Customization Page

```
┌─────────────────────────────────────────────────────────┐
│  Settings > Keyboard Shortcuts                          │
│  ─────────────────────────────────────────────────────  │
│                                                         │
│  Customize keyboard shortcuts to match your workflow    │
│                                    [ Reset All Defaults]│
│  ─────────────────────────────────────────────────────  │
│                                                         │
│  🔍 Filter shortcuts...                                 │
│                                                         │
│  ┌─────────────────────────────────────────────────────┐│
│  │                                                     ││
│  │  Command Palette                                    ││
│  │  Global                                             ││
│  │                               ┌───┐┌───┐ [Edit]    ││
│  │                               │ ⌘ ││ K │ [Reset]   ││
│  │                               └───┘└───┘           ││
│  │─────────────────────────────────────────────────────││
│  │                                                     ││
│  │  Save File                        ● Modified       ││
│  │  Editor                                             ││
│  │                          ┌──────┐┌───┐ [Edit]      ││
│  │                          │ Ctrl ││ S │ [Reset]     ││
│  │                          └──────┘└───┘             ││
│  │─────────────────────────────────────────────────────││
│  └─────────────────────────────────────────────────────┘│
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### Recording New Shortcut

```
┌─────────────────────────────────────────────────────────┐
│  Recording mode:                                        │
│                                                         │
│  ┌─────────────────────────────────────────────────────┐│
│  │  ⌨️  Press new shortcut...     [Cancel]            ││
│  │  ────────────────────────────────────────────────── ││
│  │  Current: ⌘ + Shift + S                            ││
│  └─────────────────────────────────────────────────────┘│
│                                                         │
│  Conflict detected:                                     │
│                                                         │
│  ┌─────────────────────────────────────────────────────┐│
│  │  ⚠️  This shortcut is already used by:             ││
│  │      "Save All" (Editor)                           ││
│  │                                                    ││
│  │      [Reassign Anyway]  [Choose Different]         ││
│  └─────────────────────────────────────────────────────┘│
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## 04.5 Implementation Guidelines

### Platform Detection

```typescript
const isMac = (): boolean => {
  if (typeof navigator === 'undefined') return false;
  return /Mac|iPod|iPhone|iPad/.test(navigator.platform);
};

const getModifierKey = (): 'metaKey' | 'ctrlKey' => {
  return isMac() ? 'metaKey' : 'ctrlKey';
};
```

### Key Matching Logic

```typescript
interface KeyMatch {
  key: string;
  modifiers: {
    mod: boolean;    // Command on Mac, Ctrl on Windows
    alt: boolean;
    shift: boolean;
  };
}

const parseKeyBinding = (binding: string): KeyMatch => {
  const parts = binding.toLowerCase().split('+');
  return {
    key: parts[parts.length - 1],
    modifiers: {
      mod: parts.includes('mod') || parts.includes('cmd') || parts.includes('ctrl'),
      alt: parts.includes('alt') || parts.includes('option'),
      shift: parts.includes('shift')
    }
  };
};

const matchesEvent = (event: KeyboardEvent, binding: KeyMatch): boolean => {
  const modKey = getModifierKey();
  
  return (
    event.key.toLowerCase() === binding.key &&
    event[modKey] === binding.modifiers.mod &&
    event.altKey === binding.modifiers.alt &&
    event.shiftKey === binding.modifiers.shift
  );
};
```

### Shortcut Registration Pattern

```typescript
// Central shortcut manager
class ShortcutManager {
  private shortcuts: Map<string, ShortcutConfig> = new Map();
  private userOverrides: Map<string, string[]> = new Map();
  
  register(config: ShortcutConfig): void {
    this.shortcuts.set(config.id, config);
  }
  
  setUserOverride(id: string, keys: string[]): void {
    this.userOverrides.set(id, keys);
    this.persistToDatabase(id, keys);
  }
  
  getActiveKeys(id: string): string[] {
    return this.userOverrides.get(id) ?? 
           this.shortcuts.get(id)?.defaultKeys ?? 
           [];
  }
  
  checkConflict(keys: string[], excludeId?: string): ShortcutConfig | null {
    for (const [id, config] of this.shortcuts) {
      if (id === excludeId) continue;
      const activeKeys = this.getActiveKeys(id);
      if (arraysEqual(activeKeys, keys)) {
        return config;
      }
    }
    return null;
  }
}
```

---

## 04.6 Accessibility Requirements

### ARIA Labels

```html
<!-- Keyboard badge with accessibility -->
<kbd aria-label="Command plus S">⌘ S</kbd>

<!-- Shortcut in tooltip -->
<button aria-keyshortcuts="Control+S" aria-label="Save (Ctrl+S)">
  Save
</button>

<!-- Shortcuts dialog -->
<dialog aria-label="Keyboard shortcuts reference">
  <!-- ... -->
</dialog>
```

### Focus Management

- Shortcuts overlay traps focus when open
- Escape closes shortcuts overlay
- Return focus to trigger element on close
- Tab navigation within shortcuts list

### Screen Reader Announcements

```typescript
// Announce shortcut conflicts
announceToScreenReader(`Shortcut conflict: ${keys} is already assigned to ${action}`);

// Announce shortcut changes
announceToScreenReader(`Shortcut updated: ${action} is now ${newKeys}`);
```

---

## 04.7 WordPress Plugin Integration

For WordPress plugins, keyboard shortcuts integrate with the admin interface:

```php
// Register shortcuts with WordPress
add_action('admin_enqueue_scripts', function() {
    wp_localize_script('plugin-shortcuts', 'pluginShortcuts', [
        'shortcuts' => get_user_shortcuts(get_current_user_id()),
        'defaults' => get_default_shortcuts(),
        'nonce' => wp_create_nonce('shortcuts_update')
    ]);
});

// REST endpoint for saving shortcuts
register_rest_route('plugin/v1', '/shortcuts', [
    'methods' => 'PUT',
    'callback' => 'update_user_shortcuts',
    'permission_callback' => function() {
        return current_user_can('manage_options');
    }
]);
```

---

## 04.8 Testing Requirements

### Unit Tests
- Key binding parsing
- Platform detection
- Conflict detection
- User override application

### Integration Tests
- Shortcut registration and firing
- Database persistence
- Cross-component communication

### E2E Tests
- Shortcut customization flow
- Import/export functionality
- Platform-specific behavior

---

## 04.9 Acceptance Criteria

- [ ] Shortcuts work consistently across Mac/Windows/Linux
- [ ] User customizations persist to database
- [ ] Conflict detection prevents duplicate bindings
- [ ] Platform-specific symbols display correctly
- [ ] Tooltips show shortcuts with proper formatting
- [ ] Shortcuts overlay is searchable
- [ ] Reset to defaults works correctly
- [ ] Import/export functionality works
- [ ] Screen reader accessible
- [ ] Focus management in overlays correct

---

*This standard applies to: Spec Management Software, WordPress Exam Manager, and all future projects.*
