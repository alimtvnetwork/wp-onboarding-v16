# 22. UI Design System

## Overview
Complete design token system including colors, typography, spacing, shadows, animations, and component states for consistent UI implementation.

---

## 22.1 Color Palette

### Primary Colors

| Token | Light Mode | Dark Mode | Usage |
|-------|------------|-----------|-------|
| `--primary-50` | `#f0f9ff` | `#0c1929` | Subtle backgrounds |
| `--primary-100` | `#e0f2fe` | `#172a3a` | Hover backgrounds |
| `--primary-200` | `#bae6fd` | `#1e3a4c` | Active backgrounds |
| `--primary-300` | `#7dd3fc` | `#2563eb` | Borders |
| `--primary-400` | `#38bdf8` | `#3b82f6` | Icons |
| `--primary-500` | `#0ea5e9` | `#60a5fa` | **Primary actions** |
| `--primary-600` | `#0284c7` | `#93c5fd` | Primary hover |
| `--primary-700` | `#0369a1` | `#bfdbfe` | Primary active |
| `--primary-800` | `#075985` | `#dbeafe` | Text on light |
| `--primary-900` | `#0c4a6e` | `#eff6ff` | Text emphasis |
| `--primary-950` | `#082f49` | `#f8fafc` | Headings |

### Semantic Colors

| Purpose | Token | Light | Dark | Hex (Light) |
|---------|-------|-------|------|-------------|
| Success | `--success` | 500 | 400 | `#22c55e` |
| Warning | `--warning` | 500 | 400 | `#f59e0b` |
| Error | `--error` | 500 | 400 | `#ef4444` |
| Info | `--info` | 500 | 400 | `#3b82f6` |

### Success Scale
| Token | Hex | Usage |
|-------|-----|-------|
| `--success-50` | `#f0fdf4` | Success background |
| `--success-100` | `#dcfce7` | Success hover |
| `--success-500` | `#22c55e` | Success primary |
| `--success-600` | `#16a34a` | Success hover |
| `--success-700` | `#15803d` | Success active |

### Warning Scale
| Token | Hex | Usage |
|-------|-----|-------|
| `--warning-50` | `#fffbeb` | Warning background |
| `--warning-100` | `#fef3c7` | Warning hover |
| `--warning-500` | `#f59e0b` | Warning primary |
| `--warning-600` | `#d97706` | Warning hover |
| `--warning-700` | `#b45309` | Warning active |

### Error Scale
| Token | Hex | Usage |
|-------|-----|-------|
| `--error-50` | `#fef2f2` | Error background |
| `--error-100` | `#fee2e2` | Error hover |
| `--error-500` | `#ef4444` | Error primary |
| `--error-600` | `#dc2626` | Error hover |
| `--error-700` | `#b91c1c` | Error active |

### Neutral Scale
| Token | Hex | Usage |
|-------|-----|-------|
| `--neutral-50` | `#fafafa` | Page background |
| `--neutral-100` | `#f4f4f5` | Card background |
| `--neutral-200` | `#e4e4e7` | Borders |
| `--neutral-300` | `#d4d4d8` | Disabled borders |
| `--neutral-400` | `#a1a1aa` | Placeholder text |
| `--neutral-500` | `#71717a` | Secondary text |
| `--neutral-600` | `#52525b` | Body text |
| `--neutral-700` | `#3f3f46` | Headings |
| `--neutral-800` | `#27272a` | Dark backgrounds |
| `--neutral-900` | `#18181b` | Darkest |
| `--neutral-950` | `#09090b` | Pure black alt |

### Deadline Colors (from SHARED-CONSTANTS.md)

> **IMPORTANT**: These colors MUST match SHARED-CONSTANTS.md exactly.

| Status | Token | Hex |
|--------|-------|-----|
| Safe (>7 days) | `--deadline-safe` | `#22c55e` |
| Warning (3-7 days) | `--deadline-warning` | `#eab308` |
| Urgent (1-3 days) | `--deadline-urgent` | `#f97316` |
| Critical Soft (<24h) | `--deadline-critical` | `#f87171` |
| Critical Hard (<24h) | `--deadline-critical-hard` | `#dc2626` |
| Overdue | `--deadline-overdue` | `#000000` |

> **Note on Error vs Deadline colors**: 
> - `--error-500` (#ef4444) is for form validation errors and error states
> - `--deadline-critical` (#f87171) is for soft deadline warnings (lighter red)
> - `--deadline-critical-hard` (#dc2626) is for hard deadline critical state
> These are intentionally different colors for different purposes.

---

## 22.2 Typography

### Font Stack
```css
--font-sans: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 
             "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
--font-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
```

### Type Scale

| Token | Size | Line Height | Weight | Usage |
|-------|------|-------------|--------|-------|
| `--text-xs` | 0.75rem (12px) | 1rem | 400 | Badges, labels |
| `--text-sm` | 0.875rem (14px) | 1.25rem | 400 | Secondary text |
| `--text-base` | 1rem (16px) | 1.5rem | 400 | Body text |
| `--text-lg` | 1.125rem (18px) | 1.75rem | 500 | Lead paragraphs |
| `--text-xl` | 1.25rem (20px) | 1.75rem | 600 | Card titles |
| `--text-2xl` | 1.5rem (24px) | 2rem | 600 | Section headings |
| `--text-3xl` | 1.875rem (30px) | 2.25rem | 700 | Page titles |
| `--text-4xl` | 2.25rem (36px) | 2.5rem | 700 | Hero headings |

### Font Weights
| Token | Value | Usage |
|-------|-------|-------|
| `--font-light` | 300 | Large display text |
| `--font-normal` | 400 | Body text |
| `--font-medium` | 500 | Emphasis |
| `--font-semibold` | 600 | Buttons, labels |
| `--font-bold` | 700 | Headings |

---

## 22.3 Spacing Scale

Base unit: 4px (0.25rem)

| Token | Value | Pixels | Usage |
|-------|-------|--------|-------|
| `--space-0` | 0 | 0px | Reset |
| `--space-0.5` | 0.125rem | 2px | Micro spacing |
| `--space-1` | 0.25rem | 4px | Tight inline |
| `--space-2` | 0.5rem | 8px | Inline elements |
| `--space-3` | 0.75rem | 12px | Tight padding |
| `--space-4` | 1rem | 16px | **Standard padding** |
| `--space-5` | 1.25rem | 20px | Medium padding |
| `--space-6` | 1.5rem | 24px | Card padding |
| `--space-8` | 2rem | 32px | Section spacing |
| `--space-10` | 2.5rem | 40px | Large gaps |
| `--space-12` | 3rem | 48px | Section margins |
| `--space-16` | 4rem | 64px | Page sections |
| `--space-20` | 5rem | 80px | Hero spacing |
| `--space-24` | 6rem | 96px | Large separators |

---

## 22.4 Shadows

| Token | Value | Usage |
|-------|-------|-------|
| `--shadow-sm` | `0 1px 2px 0 rgb(0 0 0 / 0.05)` | Subtle elevation |
| `--shadow-md` | `0 4px 6px -1px rgb(0 0 0 / 0.1)` | Cards, dropdowns |
| `--shadow-lg` | `0 10px 15px -3px rgb(0 0 0 / 0.1)` | Modals, popovers |
| `--shadow-xl` | `0 20px 25px -5px rgb(0 0 0 / 0.1)` | Dialogs |
| `--shadow-2xl` | `0 25px 50px -12px rgb(0 0 0 / 0.25)` | Overlays |
| `--shadow-inner` | `inset 0 2px 4px 0 rgb(0 0 0 / 0.05)` | Input focus |
| `--shadow-none` | `none` | Reset |

### Focus Ring
```css
--ring-offset: 2px;
--ring-width: 2px;
--ring-color: var(--primary-500);
--focus-ring: 0 0 0 var(--ring-offset) white, 
              0 0 0 calc(var(--ring-offset) + var(--ring-width)) var(--ring-color);
```

---

## 22.5 Border Radius

| Token | Value | Usage |
|-------|-------|-------|
| `--radius-none` | 0 | Sharp corners |
| `--radius-sm` | 0.125rem (2px) | Subtle rounding |
| `--radius-md` | 0.375rem (6px) | Buttons, inputs |
| `--radius-lg` | 0.5rem (8px) | Cards |
| `--radius-xl` | 0.75rem (12px) | Modals |
| `--radius-2xl` | 1rem (16px) | Large cards |
| `--radius-3xl` | 1.5rem (24px) | Feature cards |
| `--radius-full` | 9999px | Pills, avatars |

---

## 22.6 Animation & Transitions

### Durations
| Token | Value | Usage |
|-------|-------|-------|
| `--duration-fast` | 100ms | Micro-interactions |
| `--duration-normal` | 200ms | **Standard transitions** |
| `--duration-slow` | 300ms | Complex animations |
| `--duration-slower` | 500ms | Page transitions |

### Easing Functions
| Token | Value | Usage |
|-------|-------|-------|
| `--ease-linear` | `linear` | Continuous animations |
| `--ease-in` | `cubic-bezier(0.4, 0, 1, 1)` | Exit animations |
| `--ease-out` | `cubic-bezier(0, 0, 0.2, 1)` | Enter animations |
| `--ease-in-out` | `cubic-bezier(0.4, 0, 0.2, 1)` | **Default** |

### Standard Transitions
```css
--transition-colors: color, background-color, border-color, 
                     text-decoration-color, fill, stroke;
--transition-opacity: opacity;
--transition-shadow: box-shadow;
--transition-transform: transform;
--transition-all: all;
```

---

## 22.7 Component States

### Button States

| State | Background | Border | Text | Shadow |
|-------|------------|--------|------|--------|
| Default | `--primary-500` | transparent | white | `--shadow-sm` |
| Hover | `--primary-600` | transparent | white | `--shadow-md` |
| Focus | `--primary-500` | transparent | white | `--focus-ring` |
| Active | `--primary-700` | transparent | white | `--shadow-sm` |
| Disabled | `--neutral-300` | transparent | `--neutral-500` | none |
| Loading | `--primary-500` @ 70% | transparent | hidden | none |

### Input States

| State | Background | Border | Text | Ring |
|-------|------------|--------|------|------|
| Default | white | `--neutral-300` | `--neutral-900` | none |
| Hover | white | `--neutral-400` | `--neutral-900` | none |
| Focus | white | `--primary-500` | `--neutral-900` | `--focus-ring` |
| Error | `--error-50` | `--error-500` | `--neutral-900` | error ring |
| Disabled | `--neutral-100` | `--neutral-200` | `--neutral-400` | none |
| Read-only | `--neutral-50` | `--neutral-200` | `--neutral-600` | none |

### Card States

| State | Background | Border | Shadow |
|-------|------------|--------|--------|
| Default | white | `--neutral-200` | `--shadow-sm` |
| Hover | white | `--neutral-300` | `--shadow-md` |
| Selected | `--primary-50` | `--primary-500` | `--shadow-md` |
| Disabled | `--neutral-50` | `--neutral-200` | none |

---

## 22.8 Dark Mode

### Implementation Strategy
```css
/* Light mode (default) */
:root {
  --background: var(--neutral-50);
  --foreground: var(--neutral-900);
  --card: white;
  --card-foreground: var(--neutral-900);
  --border: var(--neutral-200);
  --input: var(--neutral-200);
  --ring: var(--primary-500);
}

/* Dark mode */
.dark {
  --background: var(--neutral-950);
  --foreground: var(--neutral-50);
  --card: var(--neutral-900);
  --card-foreground: var(--neutral-50);
  --border: var(--neutral-800);
  --input: var(--neutral-800);
  --ring: var(--primary-400);
}
```

### Color Mapping Rules
1. Swap light/dark ends of neutral scale
2. Use 400 instead of 500 for primary in dark mode
3. Reduce shadow opacity by 50% in dark mode
4. Increase text contrast for accessibility

---

## 22.9 Z-Index Scale

| Token | Value | Usage |
|-------|-------|-------|
| `--z-below` | -1 | Background elements |
| `--z-base` | 0 | Default stacking |
| `--z-dropdown` | 10 | Dropdowns, tooltips |
| `--z-sticky` | 20 | Sticky headers |
| `--z-fixed` | 30 | Fixed elements |
| `--z-overlay` | 40 | Overlay backgrounds |
| `--z-modal` | 50 | Modal dialogs |
| `--z-popover` | 60 | Popovers on modals |
| `--z-toast` | 70 | Toast notifications |
| `--z-max` | 9999 | Emergency override |

---

## 22.10 Accessibility Guidelines

### Color Contrast Requirements

| Element | Minimum Ratio | WCAG Level |
|---------|---------------|------------|
| Normal text | 4.5:1 | AA |
| Large text (18px+) | 3:1 | AA |
| UI components | 3:1 | AA |
| Enhanced normal | 7:1 | AAA |
| Enhanced large | 4.5:1 | AAA |

### Verified Contrast Ratios
| Combination | Ratio | Status |
|-------------|-------|--------|
| `--neutral-900` on white | 17.4:1 | ✅ AAA |
| `--neutral-600` on white | 5.7:1 | ✅ AA |
| `--primary-500` on white | 4.5:1 | ✅ AA |
| White on `--primary-600` | 4.8:1 | ✅ AA |
| `--error-600` on white | 4.5:1 | ✅ AA |

### Focus Indicators
- All interactive elements MUST have visible focus state
- Focus ring: 2px offset, 2px width, primary color
- Never use `outline: none` without alternative focus indicator
- Tab order follows visual layout (logical flow)

### Touch Targets
- Minimum size: 44x44px (mobile)
- Minimum size: 24x24px (desktop)
- Adequate spacing between targets (8px minimum)

---

## 22.11 Keyboard Navigation

### Global Shortcuts

| Key | Action | Context |
|-----|--------|---------|
| `Tab` | Move to next focusable | Global |
| `Shift+Tab` | Move to previous focusable | Global |
| `Enter` | Activate button/link | Focused element |
| `Space` | Toggle checkbox, activate button | Focused element |
| `Escape` | Close modal/dropdown | Open overlay |
| `Arrow keys` | Navigate within component | Dropdowns, tabs |

### Component-Specific Navigation

| Component | Keys | Behavior |
|-----------|------|----------|
| Dropdown | `↓/↑` | Move between options |
| Dropdown | `Enter` | Select option |
| Dropdown | `Escape` | Close dropdown |
| Tabs | `←/→` | Switch tab |
| Modal | `Tab` | Cycle within modal (focus trap) |
| Modal | `Escape` | Close modal |
| Accordion | `Enter/Space` | Toggle section |

### Focus Management Rules

1. **Focus Trap in Modals**: Tab cycles only within modal
2. **Focus Restoration**: Return focus to trigger after modal closes
3. **Skip Links**: Provide "Skip to main content" link
4. **Logical Order**: Tab order matches visual reading order

---

## 22.12 Screen Reader Support

### ARIA Patterns

| Component | Required ARIA | Example |
|-----------|---------------|---------|
| Button | `aria-label` if icon-only | `aria-label="Close"` |
| Modal | `role="dialog"`, `aria-modal="true"` | Required |
| Alert | `role="alert"`, `aria-live="polite"` | Required |
| Loading | `aria-busy="true"`, `aria-live="polite"` | Required |
| Progress | `role="progressbar"`, `aria-valuenow` | Required |
| Tabs | `role="tablist"`, `role="tab"`, `role="tabpanel"` | Required |

### Live Regions

```html
<!-- For dynamic content updates -->
<div aria-live="polite" aria-atomic="true">
  <!-- Screen reader announces changes here -->
</div>

<!-- For urgent notifications -->
<div role="alert" aria-live="assertive">
  <!-- Immediately announced -->
</div>
```

### Form Error Announcements

```html
<input 
  id="email" 
  aria-invalid="true" 
  aria-describedby="email-error"
/>
<span id="email-error" role="alert">
  Please enter a valid email address
</span>
```

---

## 22.13 Reduced Motion Support

```css
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
}
```

### Alternative Implementations
- Replace slide animations with fade
- Disable parallax effects
- Use instant state changes
- Maintain functionality without motion

---

## Acceptance Criteria

### Design Tokens
- [ ] All colors use CSS custom properties
- [ ] No hardcoded color values in components
- [ ] Dark mode fully implemented
- [ ] Spacing uses scale tokens

### Accessibility
- [ ] All text passes WCAG AA contrast
- [ ] Focus indicators on all interactive elements
- [ ] Keyboard navigation works throughout
- [ ] Screen reader tested with NVDA/VoiceOver
- [ ] Reduced motion preferences respected

---

## Related Specifications

| Topic | Spec |
|-------|------|
| Responsive Design | [23-responsive-design](23-responsive-design.md) |
| Deadline Colors | [66-shared-constants](../../66-shared-constants.md) |
| Loading States | [20-loading-states](20-loading-states.md) |
| Form Validation | [19-form-validation](19-form-validation.md) |

---

*Next: `23-responsive-design.md`*
