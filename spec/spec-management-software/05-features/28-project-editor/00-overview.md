# Feature: Project Editor

**Version:** 2.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Summary

Project editor infrastructure including input state persistence, draft recovery, cross-device sync, and editor state management. Ensures users never lose work due to navigation, tab switches, or browser refreshes.

---

## User Stories

- As a user, I want my chat input to persist when I switch tabs
- As a user, I want my editor drafts saved automatically
- As a user, I want to recover unsaved work after a browser crash
- As a user, I want my input state synced across devices (premium)
- As a user, I want my cursor position restored when returning to a file
- As a user, I want undo/redo to work across sessions

---

## Components

| # | Component | Type | Description |
|---|-----------|------|-------------|
| 01 | [Draft Recovery UI](./01-draft-recovery-ui.md) | Frontend | Banner and dialog for recovering unsaved drafts |
| 02 | [Sync API](./02-sync-api.md) | Backend | Cross-device state synchronization API |
| 03 | [Editor State Hooks](./03-editor-state-hooks.md) | Frontend | React hooks for cursor, scroll, undo/redo |
| 04 | [Integration Tests](./04-integration-tests.md) | Testing | E2E and integration test suite for draft recovery |
| 05 | [Error Codes](./05-error-codes.md) | Reference | Error codes (13xxx range) |
| 06 | [Input State Persistence](./06-input-state-persistence.md) | Frontend | localStorage/IndexedDB persistence |

---

## Key Features

- **Debounced Save:** 100ms default, 500ms for editors
- **Tiered Storage:** localStorage (<1KB) and IndexedDB (>1KB)
- **Draft Recovery:** Banner and dialog to restore unsaved changes
- **Cross-Project:** Global vs project-specific persistence
- **Cross-Device Sync:** Premium feature for multi-device workflows
- **Editor State:** Cursor, scroll, selection, and undo/redo persistence
- **Version Migration:** Automatic cleanup of old keys

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        PROJECT EDITOR INFRASTRUCTURE                         │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────────┐   ┌──────────────────┐   ┌──────────────────┐         │
│  │   Input Monitor  │──▶│   State Manager  │──▶│     Storage      │         │
│  │                  │   │                  │   │                  │         │
│  │  • onChange      │   │  • Debounce      │   │  • localStorage  │         │
│  │  • onBlur        │   │  • Serialize     │   │  • IndexedDB     │         │
│  │  • beforeUnload  │   │  • Encrypt       │   │  • Backend API   │         │
│  └──────────────────┘   └────────┬─────────┘   └────────┬─────────┘         │
│                                  │                      │                    │
│         ┌────────────────────────┴──────────────────────┘                   │
│         │                                                                    │
│         ▼                                                                    │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                         DRAFT RECOVERY                                │   │
│  │  ┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐     │   │
│  │  │  Recovery       │   │  Multi-Draft    │   │  Recovery       │     │   │
│  │  │  Banner         │   │  Dialog         │   │  Service        │     │   │
│  │  └─────────────────┘   └─────────────────┘   └─────────────────┘     │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                         EDITOR STATE                                  │   │
│  │  ┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐     │   │
│  │  │  Cursor         │   │  Scroll         │   │  Undo/Redo      │     │   │
│  │  │  Position       │   │  Position       │   │  History        │     │   │
│  │  └─────────────────┘   └─────────────────┘   └─────────────────┘     │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                      SYNC API (Premium)                               │   │
│  │  ┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐     │   │
│  │  │  Sync Manager   │   │  Conflict       │   │  Device         │     │   │
│  │  │  (Client)       │   │  Resolution     │   │  Management     │     │   │
│  │  └─────────────────┘   └─────────────────┘   └─────────────────┘     │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Dependencies

- [State Management](../16-state-management/00-overview.md)
- [AI Integration](../06-ai-integration/00-overview.md) - Chat input
- [Spec Editor](../04-spec-editor/00-overview.md) - Editor component
- [Authentication](../01-authentication/00-overview.md) - Premium tier validation

---

## Related Specs

- [Input State Persistence](./06-input-state-persistence.md) - Core storage implementation
- [Draft Recovery UI](./01-draft-recovery-ui.md) - Recovery interface components
- [Sync API](./02-sync-api.md) - Cross-device synchronization
- [Editor State Hooks](./03-editor-state-hooks.md) - React hooks for editor state
