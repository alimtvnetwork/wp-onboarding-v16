# Memory: features/escalation-notifications

**Updated:** 2026-01-30  
**Spec Location:** `spec/spec-management-software/05-features/06-ai-integration/13-escalation-notifications.md`

---

## Overview

Multi-channel notification system for AI escalations with priority-based routing.

---

## Channels

| Channel | Use Case |
|---------|----------|
| In-App | Real-time toast + notification center |
| Email | Batched digests via Resend |
| Push | High/critical priority only |
| Webhook | External integrations |

---

## Priority Routing

| Priority | SLA | Channels |
|----------|-----|----------|
| Critical | Immediate | All enabled |
| High | < 5 min | In-App + Email |
| Medium | < 30 min | In-App + batched email |
| Low | < 2 hours | In-App only |

---

## Key Features

- **Batching**: Combine non-critical emails into digest
- **Quiet Hours**: Defer non-critical during set times
- **User Preferences**: Per-channel, per-priority thresholds
- **Webhook Signing**: HMAC-SHA256 signature verification

---

## Email Templates

- `escalation.html` — Single escalation with options
- `batch.html` — Multiple pending escalations digest
- Base template with priority badges and action buttons

---

## React Components

- `NotificationCenter` — Bell icon popover with list
- `EscalationToast` — Priority-based toast notifications
- `NotificationSettings` — User preference form
- `useNotifications` — Real-time WebSocket hook
