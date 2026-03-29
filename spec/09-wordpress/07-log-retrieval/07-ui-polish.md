# Remote Logs Panel — UI Polish Pass

> **Status:** In Progress
> **Created:** 2026-03-29
> **Follows:** 06-panel-redesign.md

---

## 1. Problem

The panel redesign (06) fixed structural issues (3 tabs → 1, Dialog → fixed overlay,
draggable). But the result still feels **cluttered and heavy**:

- Too many rounded borders / nested containers create visual noise
- Summary banner + toolbar + header badges = too much "stuff" before content
- Tabs don't feel inviting — generic shadcn style, no clear active state
- Buttons / controls scattered across toolbar feel busy
- Overall density doesn't match the clean, focused Error Modal style

---

## 2. Design Goals

- **Fewer visual layers** — reduce nested borders, rounded containers, badges
- **Content-first** — minimize chrome above the log viewer
- **Match Error Modal** — same dark, dense, monospace aesthetic
- **Tabs that invite clicking** — clear active state, subtle inactive, compact
- **One glance toolbar** — primary action prominent, secondary hidden

---

## 3. Tasks

| # | Task | Status |
|---|------|--------|
| 1 | **Slim down header** — remove size badge from header (redundant with summary), tighten spacing, match Error Modal header style | ✅ done |
| 2 | **Compact toolbar** — merge Load + Max Lines into a single group, reduce button heights to h-7, tighter gap | ✅ done |
| 3 | **Kill summary banner** — remove the per-plugin Info/Error/Trace summary line (redundant with tab counts), keep only pre-load file summary | ✅ done |
| 4 | **Restyle log-type tabs** — pill-style tabs with subtle background, clear active highlight using primary color, smaller text | ✅ done |
| 5 | **Restyle plugin tabs** — underline-style instead of boxed tabs to differentiate from log-type tabs | ✅ done |
| 6 | **Reduce container nesting** — remove inner rounded borders on toolbar and summary, use separator lines instead | ✅ done |
| 7 | **Final visual QA** — screenshot review, spacing consistency, dark mode contrast check | ✅ done |

---

## 4. Principles

- Every border, badge, and container must earn its place
- If info is shown in tabs, don't repeat it in a banner
- Primary action = colored button; everything else = ghost/icon
- Match `text-xs`, `bg-muted/20`, `border-border/60` tokens from Error Modal
