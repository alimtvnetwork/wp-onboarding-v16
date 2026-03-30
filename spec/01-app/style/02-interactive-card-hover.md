# Interactive Card Hover States

> **Created:** 2026-03-30  
> **Status:** ✅ Active  
> **Applies to:** QuickActions, StatCard, SiteCard, SiteHealthCard

---

## Problem History

### What went wrong

The original implementation used aggressive hover styles that broke the muted dark theme:

1. **Full-card gradient hovers** — `site-card-hover-gradient` applied a bright gradient across the entire card on hover, creating visual noise.
2. **Force-override text colors** — `!important` rules on `.site-card-hover:hover *` forced ALL child elements (text, badges, icons) to change color, destroying visual hierarchy.
3. **Raw Tailwind colors** — Components used `text-green-400`, `bg-neutral-800`, `border-gray-700` instead of semantic tokens, making the theme inconsistent and unmaintainable.
4. **Entire-container hover** — The parent Card had `site-card-hover group` classes, causing the whole block to highlight instead of individual items.

### How it was fixed

1. **Deleted** `site-card-hover-gradient` and `site-card-text-shadow` CSS rules.
2. **Removed** `!important` overrides from `.site-card-hover:hover`.
3. **Per-item hover** — Each interactive row gets its own hover state using `group/item`.
4. **Green border accent** — Hover shows `border-primary` (emerald green) on the individual item.
5. **Subtle background tint** — Hover adds `bg-primary/5` (5% opacity green) for depth.
6. **All colors use semantic tokens** — `text-foreground`, `text-muted-foreground`, `text-primary`, `bg-card`, `border-border/50`.

---

## Correct Hover Pattern

### Visual Behavior

| State | Border | Background | Text | Icons |
|-------|--------|------------|------|-------|
| **Default** | `border-border/50` (subtle gray) | `bg-card` | Title: `text-foreground`, Desc: `text-muted-foreground` | `text-primary` (green) |
| **Hover** | `border-primary` (green) | `bg-primary/5` (green tint) | No change | No change |

### Key Principles

- **Individual item hover** — each clickable row/card has its own hover zone, NOT the parent container.
- **Border is the primary signal** — the green border tells the user "this is interactive."
- **Background tint is secondary** — subtle `bg-primary/5` lifts the item without overwhelming.
- **Text does NOT change on hover** — the existing hierarchy (white title, gray description) is already readable.
- **Arrow chevron** — transitions from `text-muted-foreground` to `text-foreground` on hover for directional affordance.

---

## Code Reference

### QuickActions Item (Canonical Example)

```tsx
<div
  className="
    group/item flex items-center w-full rounded-md
    border border-border/50 bg-card
    px-3 sm:px-4 py-2.5 sm:py-3
    cursor-pointer transition-all duration-300
    hover:border-primary hover:bg-primary/5
  "
>
  {/* Green icon — always primary */}
  <Icon className="h-4 w-4 mr-3 text-primary shrink-0" />

  {/* Text content */}
  <div className="text-left min-w-0 flex-1">
    <p className="font-medium text-sm text-foreground">{title}</p>
    <p className="text-xs text-muted-foreground truncate">{description}</p>
  </div>

  {/* Arrow — muted by default, foreground on hover */}
  <ArrowRight className="
    h-4 w-4 ml-auto text-muted-foreground shrink-0
    group-hover/item:text-foreground transition-colors
  " />
</div>
```

### Parent Card Container

```tsx
{/* NO site-card-hover or group class on the parent */}
<Card className="shadow-sm border-border/40">
  <CardHeader>...</CardHeader>
  <CardContent className="grid gap-2">
    {/* Individual items with their own hover */}
  </CardContent>
</Card>
```

---

## Anti-Patterns (NEVER DO)

| ❌ Pattern | Why it breaks | ✅ Correct |
|-----------|---------------|-----------|
| `site-card-hover group` on parent Card | Entire block highlights at once | No hover class on parent; per-item hover |
| `hover:bg-gradient-to-r` on cards | Gradient is too loud for muted theme | `hover:bg-primary/5` |
| `!important` color overrides on hover | Destroys badge/status color hierarchy | Let children keep their natural colors |
| `group-hover:text-site-card-hover-foreground` on ALL children | Forces uniform text color, kills hierarchy | Only arrow chevron changes color |
| `hover:border-green-400` | Raw Tailwind color | `hover:border-primary` |

---

## Acceptance Criteria

1. Hovering an action item shows a green left+all border — NOT a gradient fill.
2. The parent container does NOT visually change when a child is hovered.
3. Title text remains `text-foreground` (white) on hover — does NOT change.
4. Description text remains `text-muted-foreground` (gray) on hover — does NOT change.
5. Icons remain `text-primary` (green) at all times.
6. No raw Tailwind color classes (`text-green-400`, `bg-neutral-800`) appear in the component.
7. Transition duration is `300ms`.

---

## Failure Detection

If any of these appear in a component diff, flag it:

- `site-card-hover` class on a parent `<Card>`
- `group-hover:text-` applied to ALL children of a card
- `!important` in `.site-card-hover` CSS rules
- Raw color classes: `green-400`, `neutral-800`, `gray-700`, `white`, `black`
- `bg-gradient` on hover states
- Shadow blur > 2px

---

## Related Specs

- [01-dark-theme-tokens.md](./01-dark-theme-tokens.md) — master color token reference
- [spec/03-ui-design/01-site-card-hover-contrast.md](../../03-ui-design/01-site-card-hover-contrast.md) — original site card hover spec (now superseded for interactive list items)
