# 05. Accessibility Audit Checklist

## Overview
Comprehensive WCAG 2.1 AA compliance checklist covering keyboard navigation, screen reader support, focus management, color contrast, and semantic HTML for all UI components.

---

## 05.1 WCAG 2.1 AA Principles (POUR)

| Principle | Description |
|-----------|-------------|
| **Perceivable** | Information must be presentable in ways all users can perceive |
| **Operable** | UI components must be operable by all users |
| **Understandable** | Information and UI operation must be understandable |
| **Robust** | Content must be robust enough for diverse user agents |

---

## 05.2 Component-Level Checklist

### Buttons

| Requirement | Check | WCAG |
|-------------|-------|------|
| Visible focus indicator | ☐ | 2.4.7 |
| Min touch target 44×44px | ☐ | 2.5.5 |
| Descriptive accessible name | ☐ | 4.1.2 |
| Icon-only has aria-label | ☐ | 1.1.1 |
| Disabled state announced | ☐ | 4.1.2 |
| Loading state announced | ☐ | 4.1.3 |
| Color contrast ≥4.5:1 | ☐ | 1.4.3 |
| Not color-only indication | ☐ | 1.4.1 |

```tsx
// ✅ Correct button implementation
<Button 
  aria-label="Save document"
  aria-busy={isLoading}
  disabled={isDisabled}
>
  {isLoading ? <Spinner aria-hidden /> : <Save aria-hidden />}
  <span className="sr-only">Save document</span>
</Button>

// ✅ Icon button
<Button variant="ghost" size="icon" aria-label="Close dialog">
  <X className="h-4 w-4" aria-hidden="true" />
</Button>
```

### Form Inputs

| Requirement | Check | WCAG |
|-------------|-------|------|
| Associated label (visible or sr-only) | ☐ | 1.3.1 |
| Error linked via aria-describedby | ☐ | 3.3.1 |
| Required indicated visually + aria | ☐ | 3.3.2 |
| Autocomplete attributes | ☐ | 1.3.5 |
| Group related inputs (fieldset) | ☐ | 1.3.1 |
| Visible focus ring | ☐ | 2.4.7 |
| Sufficient contrast | ☐ | 1.4.3 |

```tsx
// ✅ Correct input implementation
<div>
  <Label htmlFor="email">
    Email <span aria-hidden="true">*</span>
    <span className="sr-only">(required)</span>
  </Label>
  <Input
    id="email"
    type="email"
    autoComplete="email"
    aria-required="true"
    aria-invalid={!!error}
    aria-describedby={error ? "email-error" : undefined}
  />
  {error && (
    <p id="email-error" role="alert" className="text-destructive text-sm">
      {error}
    </p>
  )}
</div>
```

### Modals/Dialogs

| Requirement | Check | WCAG |
|-------------|-------|------|
| Focus trapped within modal | ☐ | 2.4.3 |
| Focus moves to modal on open | ☐ | 2.4.3 |
| Escape closes modal | ☐ | 2.1.1 |
| Focus returns on close | ☐ | 2.4.3 |
| role="dialog" or role="alertdialog" | ☐ | 4.1.2 |
| aria-modal="true" | ☐ | 4.1.2 |
| aria-labelledby points to title | ☐ | 4.1.2 |
| Background content inert | ☐ | 2.4.3 |

```tsx
// ✅ Correct dialog implementation
<Dialog>
  <DialogContent
    role="dialog"
    aria-modal="true"
    aria-labelledby="dialog-title"
    aria-describedby="dialog-description"
  >
    <DialogHeader>
      <DialogTitle id="dialog-title">Confirm Delete</DialogTitle>
      <DialogDescription id="dialog-description">
        This action cannot be undone.
      </DialogDescription>
    </DialogHeader>
    {/* Content */}
    <DialogFooter>
      <Button variant="outline" onClick={onClose}>Cancel</Button>
      <Button variant="destructive" onClick={onConfirm}>Delete</Button>
    </DialogFooter>
  </DialogContent>
</Dialog>
```

### Navigation

| Requirement | Check | WCAG |
|-------------|-------|------|
| Skip to main content link | ☐ | 2.4.1 |
| Current page indicated | ☐ | 2.4.4 |
| Consistent navigation order | ☐ | 3.2.3 |
| nav element with aria-label | ☐ | 4.1.2 |
| Expandable menus keyboard accessible | ☐ | 2.1.1 |
| Focus visible on all items | ☐ | 2.4.7 |

```tsx
// ✅ Skip link
<a 
  href="#main-content" 
  className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:p-2 focus:bg-background focus:ring-2"
>
  Skip to main content
</a>

// ✅ Navigation with current indicator
<nav aria-label="Main navigation">
  <ul role="list">
    <li>
      <NavLink 
        to="/dashboard"
        aria-current={isActive ? "page" : undefined}
      >
        Dashboard
      </NavLink>
    </li>
  </ul>
</nav>

// ✅ Main content landmark
<main id="main-content" tabIndex={-1}>
  {/* Page content */}
</main>
```

### Tables

| Requirement | Check | WCAG |
|-------------|-------|------|
| Caption or aria-label | ☐ | 1.3.1 |
| th elements for headers | ☐ | 1.3.1 |
| scope attribute on th | ☐ | 1.3.1 |
| Sortable columns announced | ☐ | 4.1.2 |
| Responsive alternative for mobile | ☐ | 1.3.2 |

```tsx
// ✅ Accessible table
<table aria-label="Project list">
  <thead>
    <tr>
      <th scope="col" aria-sort={sortDirection}>
        Name
        <button aria-label="Sort by name">
          <ChevronUp aria-hidden="true" />
        </button>
      </th>
      <th scope="col">Status</th>
    </tr>
  </thead>
  <tbody>
    {projects.map(project => (
      <tr key={project.id}>
        <td>{project.name}</td>
        <td>
          <Badge aria-label={`Status: ${project.status}`}>
            {project.status}
          </Badge>
        </td>
      </tr>
    ))}
  </tbody>
</table>
```

### Tabs

| Requirement | Check | WCAG |
|-------------|-------|------|
| role="tablist" on container | ☐ | 4.1.2 |
| role="tab" on each tab | ☐ | 4.1.2 |
| role="tabpanel" on panels | ☐ | 4.1.2 |
| aria-selected on active tab | ☐ | 4.1.2 |
| aria-controls linking tab to panel | ☐ | 4.1.2 |
| Arrow keys navigate tabs | ☐ | 2.1.1 |
| Tab key moves to panel content | ☐ | 2.1.1 |

```tsx
// ✅ Accessible tabs (using Radix)
<Tabs defaultValue="content">
  <TabsList aria-label="Editor sections">
    <TabsTrigger value="content">Content</TabsTrigger>
    <TabsTrigger value="metadata">Metadata</TabsTrigger>
  </TabsList>
  <TabsContent value="content">
    {/* Content panel */}
  </TabsContent>
  <TabsContent value="metadata">
    {/* Metadata panel */}
  </TabsContent>
</Tabs>
```

### Accordions

| Requirement | Check | WCAG |
|-------------|-------|------|
| Button triggers (not div) | ☐ | 2.1.1 |
| aria-expanded state | ☐ | 4.1.2 |
| aria-controls links to content | ☐ | 4.1.2 |
| Content hidden with display/visibility | ☐ | 4.1.2 |
| Enter/Space toggles | ☐ | 2.1.1 |

### Toasts/Notifications

| Requirement | Check | WCAG |
|-------------|-------|------|
| role="alert" for urgent | ☐ | 4.1.3 |
| role="status" for info | ☐ | 4.1.3 |
| aria-live="polite" or "assertive" | ☐ | 4.1.3 |
| Sufficient display time | ☐ | 2.2.1 |
| Dismissible with keyboard | ☐ | 2.1.1 |
| Not relying on color alone | ☐ | 1.4.1 |

```tsx
// ✅ Accessible toast
<div
  role="alert"
  aria-live="assertive"
  className="flex items-center gap-2"
>
  <AlertCircle className="text-destructive" aria-hidden="true" />
  <span>Error: Failed to save file</span>
  <button aria-label="Dismiss notification">
    <X aria-hidden="true" />
  </button>
</div>
```

---

## 05.3 Keyboard Navigation Checklist

### Global Requirements

| Requirement | Check | WCAG |
|-------------|-------|------|
| All interactive elements focusable | ☐ | 2.1.1 |
| Logical focus order (DOM order) | ☐ | 2.4.3 |
| No keyboard traps (except modals) | ☐ | 2.1.2 |
| Focus visible on all elements | ☐ | 2.4.7 |
| Custom focus styles high contrast | ☐ | 2.4.7 |
| Skip links for repetitive content | ☐ | 2.4.1 |

### Focus Ring Styling

```css
/* ✅ High-contrast focus ring */
:focus-visible {
  outline: 2px solid hsl(var(--ring));
  outline-offset: 2px;
}

/* ✅ Focus ring with sufficient contrast */
.focus-ring {
  @apply focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2;
}
```

### Tab Order Verification

```typescript
// ✅ Verify logical tab order
const verifyTabOrder = () => {
  const focusableElements = document.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]), ' +
    'select:not([disabled]), textarea:not([disabled]), ' +
    '[tabindex]:not([tabindex="-1"])'
  );
  
  // Log focus order for manual verification
  focusableElements.forEach((el, i) => {
    console.log(`${i + 1}: ${el.tagName}`, el);
  });
};
```

---

## 05.4 Screen Reader Testing Checklist

### Announcements

| Scenario | Expected Announcement | Check |
|----------|----------------------|-------|
| Page load | Page title | ☐ |
| Form error | Error message | ☐ |
| Toast notification | Notification content | ☐ |
| Loading state | "Loading" announcement | ☐ |
| Content update | Updated content | ☐ |
| Modal open | Dialog title | ☐ |
| Menu expand | Expanded state | ☐ |

### Live Regions

```tsx
// ✅ Announce dynamic content changes
const [status, setStatus] = useState('');

<div aria-live="polite" aria-atomic="true" className="sr-only">
  {status}
</div>

// On action complete:
setStatus('File saved successfully');
```

### Testing Commands (VoiceOver/NVDA)

| Screen Reader | Navigate | Read All | Forms Mode |
|---------------|----------|----------|------------|
| VoiceOver (Mac) | VO + ←→ | VO + A | Auto |
| NVDA (Windows) | ↑↓ | NVDA + ↓ | E |
| JAWS (Windows) | ↑↓ | Insert + ↓ | F |

---

## 05.5 Color & Contrast Checklist

| Requirement | Ratio | Check | WCAG |
|-------------|-------|-------|------|
| Normal text | ≥4.5:1 | ☐ | 1.4.3 |
| Large text (18px+ or 14px bold) | ≥3:1 | ☐ | 1.4.3 |
| UI components & graphics | ≥3:1 | ☐ | 1.4.11 |
| Focus indicators | ≥3:1 | ☐ | 1.4.11 |
| Not color alone for info | — | ☐ | 1.4.1 |
| Link distinguishable | — | ☐ | 1.4.1 |

### Color Testing Tools

```typescript
// Calculate contrast ratio
const getContrastRatio = (color1: string, color2: string): number => {
  const lum1 = getLuminance(color1);
  const lum2 = getLuminance(color2);
  const lighter = Math.max(lum1, lum2);
  const darker = Math.min(lum1, lum2);
  return (lighter + 0.05) / (darker + 0.05);
};

// Check if contrast passes WCAG AA
const passesAA = (ratio: number, isLargeText: boolean): boolean => {
  return isLargeText ? ratio >= 3 : ratio >= 4.5;
};
```

### Error State Example

```tsx
// ✅ Error indication without color alone
<div className="flex items-center gap-2 text-destructive">
  <AlertCircle className="h-4 w-4" aria-hidden="true" />
  <span>Error: Invalid email address</span>
</div>

// ❌ Wrong: Color only
<span className="text-red-500">Error: Invalid email</span>
```

---

## 05.6 Focus Management Checklist

### Focus Restoration

| Scenario | Focus Target | Check |
|----------|--------------|-------|
| Modal close | Trigger button | ☐ |
| Dropdown close | Trigger button | ☐ |
| Delete item in list | Next item or heading | ☐ |
| Tab close | Adjacent tab | ☐ |
| Form submit | Success message or first error | ☐ |

### Focus Management Hook

```typescript
const useFocusReturn = () => {
  const triggerRef = useRef<HTMLElement | null>(null);

  const saveFocus = () => {
    triggerRef.current = document.activeElement as HTMLElement;
  };

  const restoreFocus = () => {
    triggerRef.current?.focus();
    triggerRef.current = null;
  };

  return { saveFocus, restoreFocus };
};

// Usage in modal
const Modal = ({ open, onClose }) => {
  const { saveFocus, restoreFocus } = useFocusReturn();

  useEffect(() => {
    if (open) {
      saveFocus();
    } else {
      restoreFocus();
    }
  }, [open]);

  // ...
};
```

### Focus Trap

```typescript
// Focus trap for modals
const useFocusTrap = (containerRef: RefObject<HTMLElement>, active: boolean) => {
  useEffect(() => {
    if (!active || !containerRef.current) return;

    const container = containerRef.current;
    const focusable = container.querySelectorAll<HTMLElement>(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    const handleTab = (e: KeyboardEvent) => {
      if (e.key !== 'Tab') return;

      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    };

    // Focus first element
    first?.focus();

    container.addEventListener('keydown', handleTab);
    return () => container.removeEventListener('keydown', handleTab);
  }, [active, containerRef]);
};
```

---

## 05.7 Semantic HTML Checklist

| Element | Use Case | Check |
|---------|----------|-------|
| `<header>` | Page/section header | ☐ |
| `<nav>` | Navigation blocks | ☐ |
| `<main>` | Primary content (one per page) | ☐ |
| `<article>` | Self-contained content | ☐ |
| `<section>` | Thematic grouping | ☐ |
| `<aside>` | Sidebar/related content | ☐ |
| `<footer>` | Page/section footer | ☐ |
| `<h1>-<h6>` | Proper heading hierarchy | ☐ |
| `<ul>/<ol>` | Lists of items | ☐ |
| `<button>` | Interactive actions | ☐ |
| `<a>` | Navigation links | ☐ |

### Heading Hierarchy

```tsx
// ✅ Correct heading hierarchy
<main>
  <h1>Project Dashboard</h1>
  
  <section aria-labelledby="active-heading">
    <h2 id="active-heading">Active Projects</h2>
    {projects.map(project => (
      <article key={project.id}>
        <h3>{project.name}</h3>
        <p>{project.description}</p>
      </article>
    ))}
  </section>
  
  <section aria-labelledby="archived-heading">
    <h2 id="archived-heading">Archived Projects</h2>
    {/* ... */}
  </section>
</main>
```

---

## 05.8 Testing Tools

### Automated Testing

| Tool | Purpose |
|------|---------|
| axe-core | Automated accessibility testing |
| eslint-plugin-jsx-a11y | Linting for React |
| Lighthouse | Chrome DevTools audit |
| WAVE | Browser extension |

### Integration with Tests

```typescript
import { axe, toHaveNoViolations } from 'jest-axe';

expect.extend(toHaveNoViolations);

describe('Button accessibility', () => {
  it('should have no accessibility violations', async () => {
    const { container } = render(<Button>Click me</Button>);
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });
});
```

### Manual Testing Checklist

| Test | Method | Check |
|------|--------|-------|
| Keyboard only | Unplug mouse, navigate with Tab/Enter/Arrows | ☐ |
| Screen reader | Test with VoiceOver/NVDA | ☐ |
| Zoom 200% | Browser zoom, verify no loss of function | ☐ |
| High contrast | Windows high contrast mode | ☐ |
| Reduced motion | prefers-reduced-motion: reduce | ☐ |

---

## 05.9 Reduced Motion

```css
/* ✅ Respect reduced motion preference */
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
```

```tsx
// ✅ Hook for reduced motion
const usePrefersReducedMotion = () => {
  const [prefersReducedMotion, setPrefersReducedMotion] = useState(false);

  useEffect(() => {
    const query = window.matchMedia('(prefers-reduced-motion: reduce)');
    setPrefersReducedMotion(query.matches);
    
    const handler = (e: MediaQueryListEvent) => {
      setPrefersReducedMotion(e.matches);
    };
    
    query.addEventListener('change', handler);
    return () => query.removeEventListener('change', handler);
  }, []);

  return prefersReducedMotion;
};
```

---

## 05.10 Audit Summary Template

```markdown
# Accessibility Audit Report

**Component/Page:** [Name]
**Date:** [Date]
**Auditor:** [Name]
**WCAG Level:** AA

## Summary
| Category | Pass | Fail | N/A |
|----------|------|------|-----|
| Perceivable | X | X | X |
| Operable | X | X | X |
| Understandable | X | X | X |
| Robust | X | X | X |

## Critical Issues
1. [Issue description - WCAG reference]

## Recommendations
1. [Recommendation]

## Tools Used
- [ ] axe-core
- [ ] VoiceOver
- [ ] NVDA
- [ ] Keyboard-only
- [ ] Lighthouse
```

---

## 05.11 Acceptance Criteria

- [ ] All pages pass axe-core automated tests
- [ ] Full keyboard navigation without mouse
- [ ] Screen reader testing completed (VoiceOver or NVDA)
- [ ] Color contrast meets WCAG AA (4.5:1 normal, 3:1 large)
- [ ] Focus visible on all interactive elements
- [ ] Skip link to main content
- [ ] Proper heading hierarchy (h1-h6)
- [ ] Form errors linked and announced
- [ ] Modals trap focus and restore on close
- [ ] Reduced motion preference respected

---

*This checklist applies to: Spec Management Software, WordPress Exam Manager, and all future projects.*
