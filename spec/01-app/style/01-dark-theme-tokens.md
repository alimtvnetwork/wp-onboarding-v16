# Dark Theme Color Tokens

> **Created:** 2026-03-30  
> **Status:** ✅ Active  
> **Applies to:** All React/Tailwind components

---

## Design Philosophy

The project uses a **muted dark theme** with emerald green as the sole accent color. Visual hierarchy is achieved through subtle luminance shifts — not color variety. Every color MUST be defined as an HSL CSS variable in `src/index.css`.

---

## Core Color Palette (Dark Mode)

| Token | HSL Value | Hex (approx) | Usage |
|-------|-----------|---------------|-------|
| `--background` | `0 0% 12%` | `#1f1f1f` | Page background |
| `--foreground` | `0 0% 90%` | `#e5e5e5` | Primary text (white-ish) |
| `--card` | `0 0% 14%` | `#242424` | Card/panel surfaces |
| `--card-foreground` | `0 0% 90%` | `#e5e5e5` | Card text |
| `--primary` | `120 45% 39%` | `#37a237` | Accent green — icons, active borders, highlights |
| `--primary-foreground` | `0 0% 100%` | `#ffffff` | Text on primary backgrounds |
| `--secondary` | `0 0% 18%` | `#2e2e2e` | Elevated surfaces |
| `--muted` | `0 0% 18%` | `#2e2e2e` | Subtle backgrounds (hover tint base) |
| `--muted-foreground` | `0 0% 60%` | `#999999` | Secondary/description text |
| `--border` | `0 0% 20%` | `#333333` | Default borders |
| `--destructive` | `0 72% 51%` | `#dc3545` | Error/danger actions |
| `--warning` | `38 92% 50%` | `#f59e0b` | Warning states |
| `--success` | `120 45% 39%` | `#37a237` | Success indicators (same as primary) |
| `--info` | `217 91% 60%` | `#3b82f6` | Informational states |

---

## Site Card Hover Tokens (Dark Mode)

| Token | HSL Value | Usage |
|-------|-----------|-------|
| `--site-card-hover` | `0 0% 18%` | Hover background elevation |
| `--site-card-hover-fg` | `0 0% 92%` | Text color on hover |
| `--site-card-hover-accent` | `120 45% 39%` | Left-border highlight on hover |
| `--site-card-hover-shadow` | `2px 2px 1px rgba(255,255,255,0.12)` | Directional shadow (45°, NO glow) |

---

## Toast Semantic Tokens (Dark Mode)

| Token | HSL Value | Usage |
|-------|-----------|-------|
| `--toast-bg` | `220 13% 14%` | Toast container background |
| `--toast-fg` | `0 0% 98%` | Toast primary text |
| `--toast-border` | `220 13% 22%` | Toast border |
| `--toast-desc` | `215 15% 65%` | Toast description text |
| `--toast-success-bg` | `120 45% 12%` | Success toast background |
| `--toast-success-border` | `120 45% 26%` | Success toast border |
| `--toast-success-fg` | `120 45% 75%` | Success toast text |
| `--toast-error-bg` | `0 72% 15%` | Error toast background |
| `--toast-error-border` | `0 72% 30%` | Error toast border |
| `--toast-error-fg` | `0 72% 82%` | Error toast text |
| `--toast-warning-bg` | `38 92% 13%` | Warning toast background |
| `--toast-warning-border` | `38 92% 26%` | Warning toast border |
| `--toast-warning-fg` | `38 92% 78%` | Warning toast text |
| `--toast-info-bg` | `217 91% 13%` | Info toast background |
| `--toast-info-border` | `217 91% 26%` | Info toast border |
| `--toast-info-fg` | `217 91% 80%` | Info toast text |

---

## Rules

1. **All colors MUST be HSL** — no hex codes in components.
2. **All colors MUST be CSS variables** defined in `src/index.css` — never hardcode in TSX.
3. **Use Tailwind semantic classes** (`text-foreground`, `bg-card`, `border-border`, `text-primary`) — never raw color utilities (`text-white`, `bg-black`, `text-green-400`).
4. **Opacity modifiers are OK** via Tailwind syntax: `bg-primary/5`, `border-border/40`.
5. **Shadows must be directional** (45° angle, ≤1px blur) — never glow (`box-shadow` with large blur/spread).
6. **Transitions standardized at 300ms** for hover states.

---

## Anti-Patterns (NEVER DO)

| ❌ Pattern | Why it's wrong | ✅ Correct |
|-----------|----------------|-----------|
| `text-white` | Hardcoded, ignores theme | `text-foreground` |
| `bg-neutral-800` | Raw Tailwind color | `bg-card` |
| `text-green-400` | Hardcoded accent | `text-primary` |
| `border-gray-700` | Hardcoded border | `border-border` or `border-border/50` |
| `bg-[#1f1f1f]` | Arbitrary hex value | `bg-background` |
| `hover:shadow-[0_0_20px_...]` | Glow effect | `shadow-sm` or directional shadow token |
| `text-gray-400` | Hardcoded muted | `text-muted-foreground` |

---

## Reference

- **Source of truth:** `src/index.css` (`:root` and `.dark` blocks)
- **Tailwind config:** `tailwind.config.ts` (maps CSS vars to Tailwind classes)
- **Related spec:** [02-interactive-card-hover.md](./02-interactive-card-hover.md)
- **Issue history:** Colors were previously broken by using raw Tailwind color classes and gradient-heavy hovers — see the hover spec for the fix record.
