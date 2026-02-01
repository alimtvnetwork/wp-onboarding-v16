# Settings System

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Comprehensive application settings system with hover-activated dropdown menus, configurable keyboard shortcuts, health/connectivity diagnostics, and WebSocket monitoring. Settings are persisted per-user and synchronized across sessions.

**Cross-References:**
- [Multi-Theme Seeding](../10-theme-system/03-multi-theme-seeding.md) - Theme configuration
- [Project Editor UI](./15-project-editor-ui.md) - Main interface
- [Error Handling](./19-error-handling-system.md) - Error management
- [Configuration](./05-configuration.md) - Backend settings

---

## Tab Navigation Structure

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  [Project]    [AI]    [Settings ▼]                                              │
│                          │                                                       │
│                          │ ← Hover to open dropdown                             │
│                          ▼                                                       │
│                ┌─────────────────────────────────┐                              │
│                │  Theme                      ▸  │ ← Submenu on hover           │
│                │  Keyboard Shortcuts        ▸  │                               │
│                │  ─────────────────────────    │                               │
│                │  Connection Status              │                               │
│                │  Health Check                   │                               │
│                │  WebSocket Monitor              │                               │
│                │  ─────────────────────────    │                               │
│                │  Error Logs                     │                               │
│                │  Export Settings                │                               │
│                │  Import Settings                │                               │
│                └─────────────────────────────────┘                              │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Hover Dropdown Menu

### Behavior Specification

| Behavior | Description |
|----------|-------------|
| Trigger | Mouse hover over "Settings" tab |
| Open Delay | 100ms hover before open |
| Close Delay | 300ms leave before close |
| Submenus | Hover on `▸` items to expand |
| Click Action | Open settings page / perform action |
| Keyboard | Arrow keys navigate, Enter selects |

### Component Structure

```typescript
interface SettingsDropdownProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  position: 'left' | 'center' | 'right';
}

interface SettingsMenuItem {
  id: string;
  label: string;
  icon: LucideIcon;
  shortcut?: string;
  type: 'action' | 'submenu' | 'separator' | 'link';
  submenu?: SettingsMenuItem[];
  onClick?: () => void;
  href?: string;
  badge?: string | number;  // For notifications
  disabled?: boolean;
}

// Menu configuration
const settingsMenuItems: SettingsMenuItem[] = [
  {
    id: 'theme',
    label: 'Theme',
    icon: Palette,
    type: 'submenu',
    submenu: [
      { id: 'light', label: 'Light', icon: Sun, type: 'action' },
      { id: 'dark', label: 'Dark', icon: Moon, type: 'action' },
      { id: 'ocean', label: 'Ocean Blue', icon: Waves, type: 'action' },
      { id: 'midnight', label: 'Midnight', icon: Moon, type: 'action' },
      { id: 'forest', label: 'Forest Green', icon: TreePine, type: 'action' },
      { id: 'more-themes', label: 'More Themes...', icon: Palette, type: 'link', href: '/settings/themes' },
    ],
  },
  {
    id: 'shortcuts',
    label: 'Keyboard Shortcuts',
    icon: Keyboard,
    type: 'submenu',
    submenu: [
      { id: 'view-shortcuts', label: 'View All Shortcuts', icon: Eye, type: 'link', href: '/settings/shortcuts' },
      { id: 'customize', label: 'Customize...', icon: Settings, type: 'link', href: '/settings/shortcuts/edit' },
      { id: 'reset-shortcuts', label: 'Reset to Defaults', icon: RotateCcw, type: 'action' },
    ],
  },
  { id: 'sep1', type: 'separator', label: '', icon: null as any },
  {
    id: 'connection',
    label: 'Connection Status',
    icon: Wifi,
    type: 'action',
    badge: '●', // Green dot for connected
  },
  {
    id: 'health',
    label: 'Health Check',
    icon: HeartPulse,
    type: 'action',
  },
  {
    id: 'websocket',
    label: 'WebSocket Monitor',
    icon: Activity,
    type: 'link',
    href: '/settings/websocket',
  },
  { id: 'sep2', type: 'separator', label: '', icon: null as any },
  {
    id: 'error-logs',
    label: 'Error Logs',
    icon: AlertTriangle,
    type: 'link',
    href: '/settings/errors',
    badge: 3, // Error count
  },
  {
    id: 'export',
    label: 'Export Settings',
    icon: Download,
    type: 'action',
  },
  {
    id: 'import',
    label: 'Import Settings',
    icon: Upload,
    type: 'action',
  },
];
```

---

## Keyboard Shortcuts Configuration

### Configurable Shortcuts

All keyboard shortcuts can be customized via Settings. Shortcuts are stored per-user and synced across devices.

```typescript
interface KeyboardShortcut {
  id: string;
  action: string;          // Action identifier
  label: string;           // Display name
  category: ShortcutCategory;
  defaultKey: string;      // Default key combination
  currentKey: string;      // User-defined key
  isCustomized: boolean;
  scope: ShortcutScope;
}

type ShortcutCategory = 
  | 'navigation'
  | 'editor'
  | 'file'
  | 'ai'
  | 'validation'
  | 'view';

type ShortcutScope = 
  | 'global'       // Works anywhere
  | 'editor'       // Only in editor pane
  | 'fileTree'     // Only in file tree
  | 'aiPanel';     // Only in AI panel

// Default shortcuts
const defaultShortcuts: KeyboardShortcut[] = [
  // Navigation
  { id: 'goto-project', action: 'nav.project', label: 'Go to Project Tab', category: 'navigation', defaultKey: 'Ctrl+1', currentKey: 'Ctrl+1', isCustomized: false, scope: 'global' },
  { id: 'goto-ai', action: 'nav.ai', label: 'Go to AI Tab', category: 'navigation', defaultKey: 'Ctrl+2', currentKey: 'Ctrl+2', isCustomized: false, scope: 'global' },
  { id: 'goto-settings', action: 'nav.settings', label: 'Open Settings', category: 'navigation', defaultKey: 'Ctrl+,', currentKey: 'Ctrl+,', isCustomized: false, scope: 'global' },
  { id: 'command-palette', action: 'nav.commandPalette', label: 'Command Palette', category: 'navigation', defaultKey: 'Ctrl+Shift+P', currentKey: 'Ctrl+Shift+P', isCustomized: false, scope: 'global' },
  { id: 'quick-open', action: 'nav.quickOpen', label: 'Quick Open File', category: 'navigation', defaultKey: 'Ctrl+P', currentKey: 'Ctrl+P', isCustomized: false, scope: 'global' },
  
  // File operations
  { id: 'save', action: 'file.save', label: 'Save File', category: 'file', defaultKey: 'Ctrl+S', currentKey: 'Ctrl+S', isCustomized: false, scope: 'editor' },
  { id: 'save-all', action: 'file.saveAll', label: 'Save All Files', category: 'file', defaultKey: 'Ctrl+Shift+S', currentKey: 'Ctrl+Shift+S', isCustomized: false, scope: 'global' },
  { id: 'new-file', action: 'file.new', label: 'New File', category: 'file', defaultKey: 'Ctrl+N', currentKey: 'Ctrl+N', isCustomized: false, scope: 'fileTree' },
  { id: 'close-tab', action: 'file.closeTab', label: 'Close Tab', category: 'file', defaultKey: 'Ctrl+W', currentKey: 'Ctrl+W', isCustomized: false, scope: 'editor' },
  { id: 'close-all', action: 'file.closeAll', label: 'Close All Tabs', category: 'file', defaultKey: 'Ctrl+Shift+W', currentKey: 'Ctrl+Shift+W', isCustomized: false, scope: 'global' },
  
  // Editor
  { id: 'find', action: 'editor.find', label: 'Find in File', category: 'editor', defaultKey: 'Ctrl+F', currentKey: 'Ctrl+F', isCustomized: false, scope: 'editor' },
  { id: 'find-replace', action: 'editor.findReplace', label: 'Find & Replace', category: 'editor', defaultKey: 'Ctrl+H', currentKey: 'Ctrl+H', isCustomized: false, scope: 'editor' },
  { id: 'find-in-project', action: 'editor.findInProject', label: 'Find in Project', category: 'editor', defaultKey: 'Ctrl+Shift+F', currentKey: 'Ctrl+Shift+F', isCustomized: false, scope: 'global' },
  { id: 'goto-line', action: 'editor.gotoLine', label: 'Go to Line', category: 'editor', defaultKey: 'Ctrl+G', currentKey: 'Ctrl+G', isCustomized: false, scope: 'editor' },
  { id: 'toggle-comment', action: 'editor.toggleComment', label: 'Toggle Comment', category: 'editor', defaultKey: 'Ctrl+/', currentKey: 'Ctrl+/', isCustomized: false, scope: 'editor' },
  
  // AI operations
  { id: 'toggle-ai', action: 'ai.toggle', label: 'Toggle AI Panel', category: 'ai', defaultKey: 'Ctrl+J', currentKey: 'Ctrl+J', isCustomized: false, scope: 'global' },
  { id: 'voice-input', action: 'ai.voice', label: 'Start Voice Input', category: 'ai', defaultKey: 'Ctrl+Shift+M', currentKey: 'Ctrl+Shift+M', isCustomized: false, scope: 'aiPanel' },
  { id: 'send-message', action: 'ai.send', label: 'Send Message', category: 'ai', defaultKey: 'Ctrl+Enter', currentKey: 'Ctrl+Enter', isCustomized: false, scope: 'aiPanel' },
  
  // Validation
  { id: 'validate', action: 'validate.once', label: 'Run Validation', category: 'validation', defaultKey: 'Ctrl+Shift+V', currentKey: 'Ctrl+Shift+V', isCustomized: false, scope: 'global' },
  { id: 'loop-validate', action: 'validate.loop', label: 'Loop Validation', category: 'validation', defaultKey: 'Ctrl+Shift+L', currentKey: 'Ctrl+Shift+L', isCustomized: false, scope: 'global' },
  { id: 'build-check', action: 'validate.build', label: 'Build Check', category: 'validation', defaultKey: 'Ctrl+B', currentKey: 'Ctrl+B', isCustomized: false, scope: 'global' },
  
  // View
  { id: 'toggle-sidebar', action: 'view.sidebar', label: 'Toggle Sidebar', category: 'view', defaultKey: 'Ctrl+B', currentKey: 'Ctrl+B', isCustomized: false, scope: 'global' },
  { id: 'toggle-preview', action: 'view.preview', label: 'Toggle Preview', category: 'view', defaultKey: 'Ctrl+Shift+P', currentKey: 'Ctrl+Shift+P', isCustomized: false, scope: 'editor' },
  { id: 'zoom-in', action: 'view.zoomIn', label: 'Zoom In', category: 'view', defaultKey: 'Ctrl+=', currentKey: 'Ctrl+=', isCustomized: false, scope: 'global' },
  { id: 'zoom-out', action: 'view.zoomOut', label: 'Zoom Out', category: 'view', defaultKey: 'Ctrl+-', currentKey: 'Ctrl+-', isCustomized: false, scope: 'global' },
];
```

### Shortcuts Editor UI

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  Keyboard Shortcuts                                    [Reset All] [Search 🔍] │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ▼ Navigation                                                                    │
│  ┌─────────────────────────────────────────────────────────────────────────────┐│
│  │  Go to Project Tab          │ Ctrl+1         │ [Edit] [Reset]              ││
│  │  Go to AI Tab               │ Ctrl+2         │ [Edit] [Reset]              ││
│  │  Command Palette            │ Ctrl+Shift+P   │ [Edit] [Reset]  ● Modified  ││
│  │  Quick Open File            │ Ctrl+P         │ [Edit] [Reset]              ││
│  └─────────────────────────────────────────────────────────────────────────────┘│
│                                                                                  │
│  ▼ File Operations                                                               │
│  ┌─────────────────────────────────────────────────────────────────────────────┐│
│  │  Save File                  │ Ctrl+S         │ [Edit] [Reset]              ││
│  │  Save All Files             │ Ctrl+Shift+S   │ [Edit] [Reset]              ││
│  │  ...                                                                         ││
│  └─────────────────────────────────────────────────────────────────────────────┘│
│                                                                                  │
│  ▼ Editor                                                                        │
│  ...                                                                             │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Key Recording Dialog

```typescript
interface KeyRecordingDialogProps {
  isOpen: boolean;
  shortcutId: string;
  currentKey: string;
  onKeyRecorded: (newKey: string) => void;
  onCancel: () => void;
  conflictCheck: (key: string) => ShortcutConflict | null;
}

interface ShortcutConflict {
  existingShortcut: KeyboardShortcut;
  message: string;
}
```

---

## Connection & Health Monitoring

### Health Check Panel

```typescript
interface HealthStatus {
  overall: 'healthy' | 'degraded' | 'unhealthy';
  timestamp: Date;
  checks: HealthCheck[];
}

interface HealthCheck {
  name: string;
  status: 'pass' | 'warn' | 'fail';
  latencyMs?: number;
  message?: string;
  lastChecked: Date;
}

// Health check endpoints
const healthChecks: HealthCheckConfig[] = [
  { name: 'API Server', endpoint: '/health/ready', timeout: 5000 },
  { name: 'Database', endpoint: '/health/db', timeout: 3000 },
  { name: 'LLM Server', endpoint: 'http://localhost:8080/health', timeout: 10000 },
  { name: 'WebSocket', type: 'websocket', endpoint: 'ws://localhost:8080/ws' },
  { name: 'File System', endpoint: '/health/fs', timeout: 2000 },
];
```

### Health Check UI

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  System Health                                           [Refresh] [Auto: 30s] │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Overall Status: ● Healthy                              Last check: 2s ago     │
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────────┐│
│  │  ● API Server       │ Healthy     │ 23ms   │ Ready                         ││
│  │  ● Database         │ Healthy     │ 5ms    │ 12 connections                ││
│  │  ● LLM Server       │ Healthy     │ 120ms  │ Model: gemini-3-flash loaded  ││
│  │  ● WebSocket        │ Healthy     │ 8ms    │ Connected (session: abc123)   ││
│  │  ○ File System      │ Warning     │ 45ms   │ Disk 87% used                 ││
│  └─────────────────────────────────────────────────────────────────────────────┘│
│                                                                                  │
│  ▶ View Detailed Logs                                                           │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## WebSocket Monitor

Real-time WebSocket connection monitoring with message logging.

```typescript
interface WebSocketMonitorState {
  connectionStatus: 'connecting' | 'connected' | 'disconnected' | 'error';
  sessionId: string | null;
  connectedAt: Date | null;
  lastMessageAt: Date | null;
  messageCount: number;
  reconnectAttempts: number;
  latency: number;  // Ping latency in ms
  
  // Message log (ring buffer)
  messages: WebSocketMessage[];
  maxMessages: number;  // Default: 100
  
  // Filters
  messageFilter: string;
  typeFilter: string[];
}

interface WebSocketMessage {
  id: string;
  direction: 'sent' | 'received';
  type: string;
  data: any;
  timestamp: Date;
  size: number;  // bytes
}
```

### WebSocket Monitor UI

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  WebSocket Monitor                                      [Clear] [Pause] [Copy] │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Status: ● Connected                     Session: ws_abc123def456               │
│  Latency: 12ms                           Messages: 1,234 ↑ 567 ↓                │
│  Connected: 2h 34m ago                   Reconnects: 0                          │
│                                                                                  │
│  Filter: [__________] Type: [All ▼]                     [Show only errors ☐]   │
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────────┐│
│  │ ↓ 10:30:45.123  segment:progress     { phase: "writing", progress: 45 }    ││
│  │ ↑ 10:30:45.000  ping                 { timestamp: 1706... }                 ││
│  │ ↓ 10:30:44.890  ai:token             { content: "the", delta: true }        ││
│  │ ↓ 10:30:44.850  ai:token             { content: "Here is", delta: true }    ││
│  │ ↓ 10:30:43.200  presence:sync        { users: [...], cursors: [...] }       ││
│  │ ↓ 10:30:40.100  error:new            { code: 3001, message: "..." }  ⚠️     ││
│  │ ...                                                                          ││
│  └─────────────────────────────────────────────────────────────────────────────┘│
│                                                                                  │
│  ▶ Expand selected message for full JSON view                                   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Database Schema

### UserSettings Table

```sql
CREATE TABLE UserSettings (
    Id TEXT PRIMARY KEY,
    UserId TEXT NOT NULL UNIQUE,
    
    -- Theme
    ThemeId TEXT,
    UseSystemTheme INTEGER NOT NULL DEFAULT 0,
    LightThemeId TEXT,
    DarkThemeId TEXT,
    
    -- Shortcuts (JSON)
    CustomShortcuts TEXT,  -- JSON object of overridden shortcuts
    
    -- Editor preferences
    FontSize INTEGER DEFAULT 14,
    FontFamily TEXT DEFAULT 'JetBrains Mono',
    TabSize INTEGER DEFAULT 2,
    WordWrap INTEGER DEFAULT 1,
    LineNumbers INTEGER DEFAULT 1,
    Minimap INTEGER DEFAULT 0,
    
    -- UI preferences
    SidebarWidth INTEGER DEFAULT 250,
    AIPanelHeight INTEGER DEFAULT 300,
    SplitViewMode TEXT DEFAULT 'split-v',
    
    -- Notifications
    DesktopNotifications INTEGER DEFAULT 1,
    SoundEnabled INTEGER DEFAULT 0,
    
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    
    FOREIGN KEY (UserId) REFERENCES User(Id) ON DELETE CASCADE,
    FOREIGN KEY (ThemeId) REFERENCES Theme(Id) ON DELETE SET NULL
);
```

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/settings` | Get user settings |
| PUT | `/api/v1/settings` | Update user settings |
| GET | `/api/v1/settings/shortcuts` | Get all shortcuts |
| PUT | `/api/v1/settings/shortcuts/{id}` | Update shortcut |
| POST | `/api/v1/settings/shortcuts/reset` | Reset all shortcuts |
| GET | `/api/v1/health` | Overall health status |
| GET | `/api/v1/health/detailed` | Detailed health checks |
| GET | `/api/v1/settings/export` | Export settings JSON |
| POST | `/api/v1/settings/import` | Import settings JSON |

---

## Related Specifications

- [Multi-Theme Seeding](../10-theme-system/03-multi-theme-seeding.md)
- [Error Handling System](./19-error-handling-system.md)
- [Project Editor UI](./15-project-editor-ui.md)
- [WebSocket Protocol](../14-realtime/01-websocket-protocol.md)
