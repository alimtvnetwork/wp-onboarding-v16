# 30 - AI Provider Settings Page

> **Version:** 1.0.0  
> **Created:** 2026-01-31  
> **Status:** ✅ Complete  
> **Depends On:** `29-ai-provider-settings.md`

---

## 🎯 Purpose

Admin UI for managing AI provider connections, credentials, OAuth flows, and model customization. Accessible from Settings → AI Providers tab.

---

## 📍 Location

- **Menu Path:** Link Manager → Settings → AI Providers (tab)
- **URL:** `/wp-admin/admin.php?page=link-manager-settings&tab=ai-providers`

---

## 🖥️ Page Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│  AI Provider Settings                                    [Re-seed] [?]  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─ Provider Cards ───────────────────────────────────────────────────┐ │
│  │                                                                     │ │
│  │  ┌─────────────────────┐  ┌─────────────────────┐                  │ │
│  │  │ 🟢 OpenAI          │  │ ⚪ Google Gemini    │                  │ │
│  │  │ GPT-4o (default)   │  │ Not configured      │                  │ │
│  │  │ [Configure] [Test] │  │ [Configure]         │                  │ │
│  │  └─────────────────────┘  └─────────────────────┘                  │ │
│  │                                                                     │ │
│  │  ┌─────────────────────┐  ┌─────────────────────┐                  │ │
│  │  │ ⚪ Anthropic       │  │ ⚪ Mistral AI       │                  │ │
│  │  │ Not configured      │  │ Not configured      │                  │ │
│  │  │ [Configure]         │  │ [Configure]         │                  │ │
│  │  └─────────────────────┘  └─────────────────────┘                  │ │
│  │                                                                     │ │
│  │  ┌─────────────────────┐  ┌─────────────────────┐                  │ │
│  │  │ ⚪ Groq            │  │ ⚪ Ollama (Local)   │                  │ │
│  │  │ Not configured      │  │ Not configured      │                  │ │
│  │  │ [Configure]         │  │ [Configure]         │                  │ │
│  │  └─────────────────────┘  └─────────────────────┘                  │ │
│  │                                                                     │ │
│  └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌─ Custom Providers ─────────────────────────────────────────────────┐ │
│  │                                                                     │ │
│  │  ┌─────────────────────┐                                           │ │
│  │  │ 🟢 Azure OpenAI    │  [+ Add Custom Provider]                  │ │
│  │  │ GPT-4 (Azure)      │                                           │ │
│  │  │ [Edit] [Delete]    │                                           │ │
│  │  └─────────────────────┘                                           │ │
│  │                                                                     │ │
│  └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 🧩 Components

### 1. Provider Card

```
┌────────────────────────────────────────┐
│  [Toggle]  OpenAI                    🟢│  ← Status indicator
│           ─────────────────────────    │
│  Default: GPT-4o                       │
│  Models: 4 available                   │
│  Auth: Bearer Token                    │
│                                        │
│  [Configure]  [Test Connection]        │
│                                        │
│  ℹ️ User modified • Seed v1.0.0        │  ← Footer info
└────────────────────────────────────────┘
```

**States:**
- 🟢 Green: Enabled and configured
- 🟡 Yellow: Enabled but not configured  
- ⚪ Gray: Disabled
- 🔴 Red: Connection error

### 2. Configure Modal

```
┌─────────────────────────────────────────────────────────────────┐
│  Configure OpenAI                                          [X]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─ Credentials ──────────────────────────────────────────────┐ │
│  │                                                             │ │
│  │  API Key *                                                  │ │
│  │  ┌─────────────────────────────────────────────────────┐   │ │
│  │  │ sk-proj-••••••••••••••••••••••••               [👁️] │   │ │
│  │  └─────────────────────────────────────────────────────┘   │ │
│  │                                                             │ │
│  │  Organization ID (optional)                                 │ │
│  │  ┌─────────────────────────────────────────────────────┐   │ │
│  │  │ org-...                                              │   │ │
│  │  └─────────────────────────────────────────────────────┘   │ │
│  │                                                             │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│  ┌─ Models ───────────────────────────────────────────────────┐ │
│  │                                                             │ │
│  │  ☑️ GPT-4o          Display: [GPT-4o          ] ⭐ Default  │ │
│  │  ☑️ GPT-4o Mini     Display: [GPT-4o Mini     ]             │ │
│  │  ☑️ GPT-4 Turbo     Display: [GPT-4 Turbo    ]             │ │
│  │  ☑️ Embeddings      Display: [Embeddings Large]             │ │
│  │                                                             │ │
│  │  [+ Add Custom Model]                                       │ │
│  │                                                             │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│  ┌─ Advanced ─────────────────────────────────────────────────┐ │
│  │                                                             │ │
│  │  Base URL                                                   │ │
│  │  ┌─────────────────────────────────────────────────────┐   │ │
│  │  │ https://api.openai.com/v1                            │   │ │
│  │  └─────────────────────────────────────────────────────┘   │ │
│  │                                                             │ │
│  │  Priority: [10    ] (lower = higher priority)               │ │
│  │                                                             │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│               [Cancel]  [Test Connection]  [Save Changes]       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 3. OAuth Provider Modal (for OAuth 2.0 providers)

```
┌─────────────────────────────────────────────────────────────────┐
│  Configure Custom OAuth Provider                           [X]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─ OAuth 2.0 Settings ───────────────────────────────────────┐ │
│  │                                                             │ │
│  │  Client ID *                                                │ │
│  │  ┌─────────────────────────────────────────────────────┐   │ │
│  │  │                                                      │   │ │
│  │  └─────────────────────────────────────────────────────┘   │ │
│  │                                                             │ │
│  │  Client Secret *                                            │ │
│  │  ┌─────────────────────────────────────────────────────┐   │ │
│  │  │ ••••••••••••••••••••••••••••••••••••••         [👁️] │   │ │
│  │  └─────────────────────────────────────────────────────┘   │ │
│  │                                                             │ │
│  │  Authorization URL *                                        │ │
│  │  ┌─────────────────────────────────────────────────────┐   │ │
│  │  │ https://provider.com/oauth/authorize                 │   │ │
│  │  └─────────────────────────────────────────────────────┘   │ │
│  │                                                             │ │
│  │  Token URL *                                                │ │
│  │  ┌─────────────────────────────────────────────────────┐   │ │
│  │  │ https://provider.com/oauth/token                     │   │ │
│  │  └─────────────────────────────────────────────────────┘   │ │
│  │                                                             │ │
│  │  Redirect URI (auto-generated)                              │ │
│  │  ┌─────────────────────────────────────────────────────┐   │ │
│  │  │ https://yoursite.com/wp-json/lm/v1/oauth/callback  📋│   │ │
│  │  └─────────────────────────────────────────────────────┘   │ │
│  │                                                             │ │
│  │  Scope                                                      │ │
│  │  ┌─────────────────────────────────────────────────────┐   │ │
│  │  │ openai.read openai.write                             │   │ │
│  │  └─────────────────────────────────────────────────────┘   │ │
│  │                                                             │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│  ┌─ Connection Status ────────────────────────────────────────┐ │
│  │                                                             │ │
│  │  Status: 🔴 Not Connected                                   │ │
│  │                                                             │ │
│  │  [🔗 Connect with OAuth]                                    │ │
│  │                                                             │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│                              [Cancel]  [Save Configuration]     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 4. Add Custom Provider Modal

```
┌─────────────────────────────────────────────────────────────────┐
│  Add Custom AI Provider                                    [X]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Display Name *                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Azure OpenAI                                             │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Base URL *                                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ https://my-resource.openai.azure.com                     │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Authentication Type *                                          │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Bearer Token                                          ▼ │   │
│  └─────────────────────────────────────────────────────────┘   │
│    ○ Bearer Token (API Key)                                     │
│    ○ OAuth 2.0 Client Credentials                               │
│    ○ OAuth 2.0 Authorization Code                               │
│    ○ API Key in Custom Header                                   │
│    ○ Custom Headers                                             │
│                                                                 │
│  ┌─ Credential Fields ────────────────────────────────────────┐ │
│  │                                                             │ │
│  │  [+ Add Field]                                              │ │
│  │                                                             │ │
│  │  ┌───────────────────────────────────────────────────────┐ │ │
│  │  │ Field 1                                          [🗑️] │ │ │
│  │  │ Key: [api_key    ] Label: [API Key        ]          │ │ │
│  │  │ Type: [Password ▼] Required: [✓]                     │ │ │
│  │  └───────────────────────────────────────────────────────┘ │ │
│  │                                                             │ │
│  │  ┌───────────────────────────────────────────────────────┐ │ │
│  │  │ Field 2                                          [🗑️] │ │ │
│  │  │ Key: [api_version] Label: [API Version    ]          │ │ │
│  │  │ Type: [Text     ▼] Required: [✓]                     │ │ │
│  │  └───────────────────────────────────────────────────────┘ │ │
│  │                                                             │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│  ┌─ Models ───────────────────────────────────────────────────┐ │
│  │                                                             │ │
│  │  [+ Add Model]                                              │ │
│  │                                                             │ │
│  │  Model ID: [gpt-4-deployment ] Name: [GPT-4 (Azure)    ]   │ │
│  │  Category: [Chat ▼] Default: [✓]                            │ │
│  │                                                             │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│                              [Cancel]  [Create Provider]        │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 5. Connection Test Result

```
┌─────────────────────────────────────────────────────────────────┐
│  Connection Test Result                                    [X]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ✅ Connection Successful                                       │
│                                                                 │
│  Provider: OpenAI                                               │
│  Model Tested: gpt-4o                                           │
│  Response Time: 245ms                                           │
│                                                                 │
│  Test prompt: "Say 'hello' in one word"                         │
│  Response: "Hello"                                              │
│                                                                 │
│                                              [Close]            │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔄 User Flows

### Configure Provider Flow
1. User clicks "Configure" on provider card
2. Modal opens with credential fields
3. User enters API key / credentials
4. User optionally customizes model names
5. User clicks "Test Connection"
6. Success → Provider card shows green status
7. User clicks "Save Changes"

### OAuth Connect Flow
1. User configures OAuth credentials
2. Clicks "Connect with OAuth"
3. Redirected to provider's authorization page
4. User grants access
5. Redirected back with authorization code
6. Plugin exchanges code for tokens
7. Status shows "Connected" with token expiry

### Add Custom Provider Flow
1. User clicks "+ Add Custom Provider"
2. Enters display name and base URL
3. Selects authentication type
4. Configures credential fields (dynamic form)
5. Adds models with custom names
6. Clicks "Create Provider"
7. New card appears in Custom Providers section

---

## 🎨 Styling

### Color Scheme
- **Connected**: `#22c55e` (green-500)
- **Warning**: `#eab308` (yellow-500)
- **Disconnected**: `#6b7280` (gray-500)
- **Error**: `#ef4444` (red-500)

### Card Styles
- Border radius: 8px
- Shadow: `0 1px 3px rgba(0,0,0,0.1)`
- Hover: Subtle border highlight
- Active provider: Left border accent

---

## 📱 Responsive Behavior

| Breakpoint | Cards Per Row | Layout |
|------------|---------------|--------|
| < 768px | 1 | Stacked |
| 768-1024px | 2 | Grid |
| > 1024px | 3 | Grid |

---

## ⌨️ Keyboard Navigation

| Key | Action |
|-----|--------|
| `Tab` | Navigate between cards/fields |
| `Enter` | Open configure modal / Submit form |
| `Escape` | Close modal |
| `Space` | Toggle enable/disable |

---

## 🌐 Internationalization

All UI text uses `__()` / `_e()` for translation:

```php
__('AI Provider Settings', 'link-manager')
__('Configure', 'link-manager')
__('Test Connection', 'link-manager')
__('Connection Successful', 'link-manager')
__('Not configured', 'link-manager')
__('Add Custom Provider', 'link-manager')
```

---

## 📋 Acceptance Criteria

- [ ] All 6 seeded providers displayed as cards
- [ ] Toggle enable/disable updates immediately
- [ ] Configure modal shows correct fields per auth type
- [ ] Password fields have show/hide toggle
- [ ] Model names editable inline
- [ ] Connection test provides clear feedback
- [ ] OAuth flow completes successfully
- [ ] Custom providers can be added/edited/deleted
- [ ] Re-seed button restores defaults (with confirmation)
- [ ] Responsive layout on all screen sizes
