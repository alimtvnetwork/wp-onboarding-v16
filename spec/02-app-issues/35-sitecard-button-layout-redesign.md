# SiteCard Button Layout Redesign

> **Created:** 2026-03-22  
> **Status:** 🔴 Open

---

## Problem

The SiteCard has too many action buttons in a flat flex-wrap grid at the bottom, making it look cluttered and poorly padded — especially Edit, Users, and Delete buttons that look odd among feature-level actions.

---

## Desired Layout

### Header Area (top-right corner)

- **Edit button** — small icon button, top-right of the card header
- **Overflow menu (⋯)** — three-dot dropdown next to Edit, containing:
  - Delete (destructive)
  - API Explorer
  - Activity
  - Snapshots
  - Users

### Bottom Action Bar (primary actions)

Clean row of primary feature buttons:

| Button | Icon | Action |
|--------|------|--------|
| Plugins | Eye | Open RemotePluginsPanel |
| Health | HeartPulse | Open Health Summary |
| Logs | FileText | Open Remote Logs |
| Settings | Settings | Open Site Settings |
| Deploy | Upload | Deploy Riseup Asia Uploader |

### Click on Card

Clicking the card body (not buttons) should open the edit dialog.

---

## Component Changes

- `src/components/sites/SiteCard.tsx` — full restructure
- Use `DropdownMenu` from Radix for the overflow menu
- Edit and Delete move out of the bottom bar into header

---

## References

- User screenshot showing current cluttered layout
- Radix `@radix-ui/react-dropdown-menu` already installed
