# Accessibility Standards (a11y)

> Version: 1.0.0 | Last Updated: 2025-01-26

## Overview

This document defines accessibility standards based on WCAG 2.1 Level AA compliance. All user interfaces must be perceivable, operable, understandable, and robust for users with disabilities.

---

## 1. WCAG Principles

### 1.1 POUR Framework

| Principle | Description | Key Requirements |
|-----------|-------------|------------------|
| **Perceivable** | Users can perceive content | Alt text, captions, color contrast |
| **Operable** | Users can operate UI | Keyboard nav, focus management |
| **Understandable** | Users understand content | Clear labels, error messages |
| **Robust** | Works with assistive tech | Semantic HTML, ARIA |

### 1.2 Compliance Levels

```
Level A   - Minimum accessibility (must have)
Level AA  - Standard compliance (target)
Level AAA - Enhanced accessibility (ideal)
```

---

## 2. Semantic HTML

### 2.1 Document Structure

```html
<!-- ✓ CORRECT: Semantic structure -->
<header>
  <nav aria-label="Main navigation">
    <ul>
      <li><a href="/">Home</a></li>
      <li><a href="/products">Products</a></li>
    </ul>
  </nav>
</header>

<main>
  <article>
    <h1>Page Title</h1>
    <section aria-labelledby="intro-heading">
      <h2 id="intro-heading">Introduction</h2>
      <p>Content...</p>
    </section>
  </article>
  
  <aside aria-label="Related content">
    <!-- Sidebar content -->
  </aside>
</main>

<footer>
  <nav aria-label="Footer navigation">
    <!-- Footer links -->
  </nav>
</footer>

<!-- ✗ WRONG: Div soup -->
<div class="header">
  <div class="nav">
    <div class="nav-item">Home</div>
  </div>
</div>
```

### 2.2 Heading Hierarchy

```html
<!-- ✓ CORRECT: Proper heading order -->
<h1>Main Page Title</h1>
  <h2>Section One</h2>
    <h3>Subsection</h3>
  <h2>Section Two</h2>
    <h3>Subsection</h3>
    <h3>Another Subsection</h3>

<!-- ✗ WRONG: Skipped heading levels -->
<h1>Title</h1>
<h3>Skipped h2!</h3>
<h5>Skipped h4!</h5>
```

### 2.3 Landmark Regions

```html
<header role="banner">         <!-- Page header -->
<nav role="navigation">        <!-- Navigation -->
<main role="main">             <!-- Main content (one per page) -->
<aside role="complementary">   <!-- Sidebar -->
<footer role="contentinfo">    <!-- Page footer -->
<section role="region">        <!-- Generic section (needs aria-label) -->
<form role="form">             <!-- Form (needs accessible name) -->
<search role="search">         <!-- Search functionality -->
```

---

## 3. Interactive Elements

### 3.1 Buttons

**TypeScript/React**
```tsx
// ✓ CORRECT: Accessible button
function ActionButton({ 
  onClick, 
  children,
  isLoading,
  disabled 
}: ButtonProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled || isLoading}
      aria-busy={isLoading}
      aria-disabled={disabled}
    >
      {isLoading ? (
        <>
          <Spinner aria-hidden="true" />
          <span className="sr-only">Loading...</span>
        </>
      ) : (
        children
      )}
    </button>
  );
}

// ✓ CORRECT: Icon button with label
function IconButton({ icon: Icon, label, onClick }: IconButtonProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={label}
    >
      <Icon aria-hidden="true" />
    </button>
  );
}

// ✗ WRONG: Div as button
function BadButton({ onClick, children }) {
  return (
    <div onClick={onClick} className="button">
      {children}
    </div>
  );
}
```

### 3.2 Links

```tsx
// ✓ CORRECT: Descriptive link text
<a href="/settings">Account settings</a>
<a href="/report.pdf" download>
  Download annual report (PDF, 2.5MB)
</a>

// External link with warning
function ExternalLink({ href, children }: ExternalLinkProps) {
  return (
    <a 
      href={href} 
      target="_blank" 
      rel="noopener noreferrer"
    >
      {children}
      <span className="sr-only"> (opens in new tab)</span>
      <ExternalLinkIcon aria-hidden="true" />
    </a>
  );
}

// ✗ WRONG: Non-descriptive link text
<a href="/settings">Click here</a>
<a href="/report.pdf">Link</a>
```

### 3.3 Form Controls

```tsx
// ✓ CORRECT: Accessible form field
function TextField({
  id,
  label,
  error,
  required,
  helpText,
  ...props
}: TextFieldProps) {
  const errorId = `${id}-error`;
  const helpId = `${id}-help`;
  
  return (
    <div className="field">
      <label htmlFor={id}>
        {label}
        {required && <span aria-hidden="true"> *</span>}
        {required && <span className="sr-only"> (required)</span>}
      </label>
      
      {helpText && (
        <p id={helpId} className="help-text">
          {helpText}
        </p>
      )}
      
      <input
        id={id}
        aria-required={required}
        aria-invalid={!!error}
        aria-describedby={[
          helpText ? helpId : null,
          error ? errorId : null,
        ].filter(Boolean).join(' ') || undefined}
        {...props}
      />
      
      {error && (
        <p id={errorId} className="error" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}

// ✓ CORRECT: Accessible checkbox group
function CheckboxGroup({ 
  legend, 
  options, 
  selected, 
  onChange 
}: CheckboxGroupProps) {
  return (
    <fieldset>
      <legend>{legend}</legend>
      {options.map((option) => (
        <label key={option.value}>
          <input
            type="checkbox"
            value={option.value}
            checked={selected.includes(option.value)}
            onChange={() => onChange(option.value)}
          />
          {option.label}
        </label>
      ))}
    </fieldset>
  );
}
```

---

## 4. Keyboard Navigation

### 4.1 Focus Management

```tsx
// Focus visible styles (CSS)
// Never remove focus outline without replacement
:focus-visible {
  outline: 2px solid var(--focus-ring);
  outline-offset: 2px;
}

// Skip to main content link
function SkipLink() {
  return (
    <a 
      href="#main-content" 
      className="skip-link"
    >
      Skip to main content
    </a>
  );
}

// CSS for skip link
.skip-link {
  position: absolute;
  top: -40px;
  left: 0;
  padding: 8px 16px;
  background: var(--background);
  z-index: 100;
}

.skip-link:focus {
  top: 0;
}
```

### 4.2 Focus Trapping (Modals)

```tsx
import { useEffect, useRef } from 'react';

function Modal({ isOpen, onClose, children }: ModalProps) {
  const modalRef = useRef<HTMLDivElement>(null);
  const previousFocusRef = useRef<HTMLElement | null>(null);
  
  useEffect(() => {
    if (isOpen) {
      // Store previous focus
      previousFocusRef.current = document.activeElement as HTMLElement;
      
      // Focus first focusable element
      const focusable = modalRef.current?.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      (focusable?.[0] as HTMLElement)?.focus();
      
      // Trap focus
      const handleKeyDown = (e: KeyboardEvent) => {
        if (e.key === 'Escape') {
          onClose();
          return;
        }
        
        if (e.key !== 'Tab' || !focusable?.length) return;
        
        const first = focusable[0] as HTMLElement;
        const last = focusable[focusable.length - 1] as HTMLElement;
        
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      };
      
      document.addEventListener('keydown', handleKeyDown);
      return () => document.removeEventListener('keydown', handleKeyDown);
    } else {
      // Restore focus
      previousFocusRef.current?.focus();
    }
  }, [isOpen, onClose]);
  
  if (!isOpen) return null;
  
  return (
    <div 
      role="dialog"
      aria-modal="true"
      aria-labelledby="modal-title"
      ref={modalRef}
    >
      <h2 id="modal-title">Modal Title</h2>
      {children}
      <button onClick={onClose}>Close</button>
    </div>
  );
}
```

### 4.3 Keyboard Shortcuts

```tsx
// Document keyboard shortcuts
function KeyboardShortcuts() {
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Only handle when not in input
      if (['INPUT', 'TEXTAREA', 'SELECT'].includes(
        (e.target as HTMLElement).tagName
      )) {
        return;
      }
      
      // Ctrl/Cmd + K for search
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        openSearch();
      }
      
      // ? for help
      if (e.key === '?') {
        e.preventDefault();
        openHelp();
      }
    };
    
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, []);
  
  return null;
}
```

---

## 5. ARIA Patterns

### 5.1 ARIA Roles

```tsx
// Tab panel
function Tabs({ tabs, activeTab, onChange }: TabsProps) {
  return (
    <div>
      <div role="tablist" aria-label="Content tabs">
        {tabs.map((tab, index) => (
          <button
            key={tab.id}
            role="tab"
            id={`tab-${tab.id}`}
            aria-selected={activeTab === tab.id}
            aria-controls={`panel-${tab.id}`}
            tabIndex={activeTab === tab.id ? 0 : -1}
            onClick={() => onChange(tab.id)}
          >
            {tab.label}
          </button>
        ))}
      </div>
      
      {tabs.map((tab) => (
        <div
          key={tab.id}
          role="tabpanel"
          id={`panel-${tab.id}`}
          aria-labelledby={`tab-${tab.id}`}
          hidden={activeTab !== tab.id}
          tabIndex={0}
        >
          {tab.content}
        </div>
      ))}
    </div>
  );
}

// Accordion
function Accordion({ items }: AccordionProps) {
  const [expanded, setExpanded] = useState<string | null>(null);
  
  return (
    <div>
      {items.map((item) => (
        <div key={item.id}>
          <h3>
            <button
              aria-expanded={expanded === item.id}
              aria-controls={`content-${item.id}`}
              onClick={() => setExpanded(
                expanded === item.id ? null : item.id
              )}
            >
              {item.title}
            </button>
          </h3>
          <div
            id={`content-${item.id}`}
            role="region"
            aria-labelledby={`heading-${item.id}`}
            hidden={expanded !== item.id}
          >
            {item.content}
          </div>
        </div>
      ))}
    </div>
  );
}
```

### 5.2 Live Regions

```tsx
// Announce dynamic content changes
function StatusMessage({ message, type }: StatusProps) {
  return (
    <div
      role="status"
      aria-live="polite"
      aria-atomic="true"
      className={`status status-${type}`}
    >
      {message}
    </div>
  );
}

// Urgent announcements
function ErrorAlert({ error }: ErrorAlertProps) {
  return (
    <div
      role="alert"
      aria-live="assertive"
      className="error-alert"
    >
      {error}
    </div>
  );
}

// Progress announcements
function ProgressAnnouncer({ progress }: ProgressProps) {
  const [announced, setAnnounced] = useState(0);
  
  useEffect(() => {
    // Announce at 25%, 50%, 75%, 100%
    const milestones = [25, 50, 75, 100];
    const milestone = milestones.find(
      m => progress >= m && announced < m
    );
    
    if (milestone) {
      setAnnounced(milestone);
    }
  }, [progress, announced]);
  
  return (
    <div 
      role="status" 
      aria-live="polite" 
      className="sr-only"
    >
      {announced > 0 && `${announced}% complete`}
    </div>
  );
}
```

### 5.3 ARIA Best Practices

```
DO:
✓ Use native HTML elements first
✓ Add ARIA only when HTML is insufficient
✓ Test with screen readers
✓ Keep aria-label concise and descriptive

DON'T:
✗ Use ARIA to fix broken HTML
✗ Override native semantics unnecessarily
✗ Add redundant ARIA (e.g., role="button" on <button>)
✗ Use aria-label on non-interactive elements
```

---

## 6. Color and Contrast

### 6.1 Contrast Requirements

```
WCAG AA Requirements:
- Normal text: 4.5:1 contrast ratio
- Large text (18pt+ or 14pt+ bold): 3:1 contrast ratio
- UI components and graphics: 3:1 contrast ratio

WCAG AAA (enhanced):
- Normal text: 7:1 contrast ratio
- Large text: 4.5:1 contrast ratio
```

### 6.2 Color-Independent Information

```tsx
// ✓ CORRECT: Status with icon + text + color
function StatusBadge({ status }: StatusProps) {
  const config = {
    success: { icon: CheckIcon, label: 'Success', className: 'bg-green-100 text-green-800' },
    warning: { icon: AlertIcon, label: 'Warning', className: 'bg-yellow-100 text-yellow-800' },
    error: { icon: XIcon, label: 'Error', className: 'bg-red-100 text-red-800' },
  };
  
  const { icon: Icon, label, className } = config[status];
  
  return (
    <span className={`badge ${className}`}>
      <Icon aria-hidden="true" />
      {label}
    </span>
  );
}

// ✓ CORRECT: Form error with icon
function FieldError({ message }: { message: string }) {
  return (
    <p className="error" role="alert">
      <ErrorIcon aria-hidden="true" />
      {message}
    </p>
  );
}

// ✗ WRONG: Color-only indication
<span className="text-red-500">{error}</span>
```

### 6.3 Focus Indicators

```css
/* Minimum focus indicator */
:focus-visible {
  outline: 2px solid var(--focus-ring);
  outline-offset: 2px;
}

/* Enhanced focus for better visibility */
.button:focus-visible {
  outline: 2px solid var(--focus-ring);
  outline-offset: 2px;
  box-shadow: 0 0 0 4px var(--focus-ring-alpha);
}

/* Custom focus for dark backgrounds */
.dark-section :focus-visible {
  outline-color: white;
}
```

---

## 7. Images and Media

### 7.1 Alternative Text

```tsx
// Informative image - describe content
<img 
  src="/chart.png" 
  alt="Sales increased 25% from Q1 to Q2 2024"
/>

// Decorative image - empty alt
<img src="/decorative-border.png" alt="" />

// Complex image - detailed description
<figure>
  <img 
    src="/org-chart.png" 
    alt="Company organization chart"
    aria-describedby="org-desc"
  />
  <figcaption id="org-desc">
    The CEO reports to the Board. Three VPs (Engineering, 
    Sales, Operations) report to the CEO. Each VP has 
    3-4 direct reports...
  </figcaption>
</figure>

// Image with text overlay
<div className="hero">
  <img src="/hero.jpg" alt="" /> {/* Decorative */}
  <h1>Welcome to Our Store</h1> {/* Text is readable */}
</div>
```

### 7.2 Video and Audio

```tsx
function VideoPlayer({ src, title, captions }: VideoProps) {
  return (
    <div>
      <video 
        controls
        aria-label={title}
      >
        <source src={src} type="video/mp4" />
        <track 
          kind="captions" 
          src={captions.src}
          srcLang={captions.lang}
          label={captions.label}
          default
        />
        Your browser does not support video.
      </video>
      
      {/* Transcript for deaf-blind users */}
      <details>
        <summary>View transcript</summary>
        <div className="transcript">
          {/* Full transcript */}
        </div>
      </details>
    </div>
  );
}
```

---

## 8. Motion and Animation

### 8.1 Reduced Motion

```css
/* Respect user preference */
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

/* Safe animations (opacity, color) */
.fade-in {
  animation: fadeIn 0.3s ease-out;
}

@media (prefers-reduced-motion: reduce) {
  .fade-in {
    animation: none;
    opacity: 1;
  }
}
```

### 8.2 Animation Controls

```tsx
function AnimatedComponent() {
  const prefersReducedMotion = useMediaQuery(
    '(prefers-reduced-motion: reduce)'
  );
  
  return (
    <motion.div
      initial={{ opacity: 0, y: prefersReducedMotion ? 0 : 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ 
        duration: prefersReducedMotion ? 0 : 0.3 
      }}
    >
      Content
    </motion.div>
  );
}

// Hook for reduced motion
function useReducedMotion(): boolean {
  const [prefersReduced, setPrefersReduced] = useState(false);
  
  useEffect(() => {
    const mediaQuery = window.matchMedia(
      '(prefers-reduced-motion: reduce)'
    );
    setPrefersReduced(mediaQuery.matches);
    
    const handler = (e: MediaQueryListEvent) => {
      setPrefersReduced(e.matches);
    };
    
    mediaQuery.addEventListener('change', handler);
    return () => mediaQuery.removeEventListener('change', handler);
  }, []);
  
  return prefersReduced;
}
```

---

## 9. Tables

### 9.1 Data Tables

```tsx
function DataTable({ data, columns }: TableProps) {
  return (
    <table>
      <caption>Monthly Sales Report</caption>
      <thead>
        <tr>
          {columns.map((col) => (
            <th key={col.key} scope="col">
              {col.label}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {data.map((row, index) => (
          <tr key={row.id}>
            <th scope="row">{row.name}</th>
            {columns.slice(1).map((col) => (
              <td key={col.key}>{row[col.key]}</td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  );
}
```

### 9.2 Sortable Tables

```tsx
function SortableTable({ data, columns }: SortableTableProps) {
  const [sortConfig, setSortConfig] = useState<SortConfig | null>(null);
  
  const handleSort = (key: string) => {
    setSortConfig({
      key,
      direction: sortConfig?.key === key && 
                 sortConfig.direction === 'asc' ? 'desc' : 'asc'
    });
  };
  
  return (
    <table aria-describedby="table-instructions">
      <caption>
        User Data
        <span id="table-instructions" className="sr-only">
          Click column headers to sort. Currently sorted by{' '}
          {sortConfig ? `${sortConfig.key} ${sortConfig.direction}` : 'default order'}.
        </span>
      </caption>
      <thead>
        <tr>
          {columns.map((col) => (
            <th key={col.key} scope="col">
              <button
                onClick={() => handleSort(col.key)}
                aria-sort={
                  sortConfig?.key === col.key
                    ? sortConfig.direction === 'asc' ? 'ascending' : 'descending'
                    : 'none'
                }
              >
                {col.label}
                <SortIcon direction={
                  sortConfig?.key === col.key ? sortConfig.direction : undefined
                } />
              </button>
            </th>
          ))}
        </tr>
      </thead>
      {/* ... */}
    </table>
  );
}
```

---

## 10. Testing Accessibility

### 10.1 Automated Testing

```typescript
// Jest + Testing Library
import { render, screen } from '@testing-library/react';
import { axe, toHaveNoViolations } from 'jest-axe';

expect.extend(toHaveNoViolations);

describe('Button accessibility', () => {
  it('should have no accessibility violations', async () => {
    const { container } = render(
      <Button onClick={() => {}}>Click me</Button>
    );
    
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });
  
  it('should be keyboard accessible', () => {
    render(<Button onClick={() => {}}>Click me</Button>);
    
    const button = screen.getByRole('button', { name: /click me/i });
    button.focus();
    expect(button).toHaveFocus();
  });
  
  it('should have accessible name', () => {
    render(<IconButton icon={SearchIcon} label="Search" />);
    
    expect(screen.getByRole('button', { name: /search/i })).toBeInTheDocument();
  });
});
```

### 10.2 Manual Testing Checklist

```
Keyboard Navigation:
□ All interactive elements reachable with Tab
□ Logical focus order
□ Visible focus indicator
□ No keyboard traps
□ Skip link works

Screen Reader:
□ All content announced
□ Form labels read correctly
□ Errors announced
□ Live regions work
□ Images have appropriate alt text

Visual:
□ 4.5:1 contrast for text
□ 3:1 contrast for UI elements
□ Works at 200% zoom
□ Works without color
□ Reduced motion respected
```

---

## 11. Screen Reader Only Text

```css
/* Visually hidden but accessible */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

/* Show on focus (for skip links) */
.sr-only-focusable:focus,
.sr-only-focusable:active {
  position: static;
  width: auto;
  height: auto;
  overflow: visible;
  clip: auto;
  white-space: normal;
}
```

---

## Accessibility Checklist

| Category | Requirement | Priority |
|----------|-------------|----------|
| Semantic HTML | Use appropriate elements | Required |
| Headings | Proper hierarchy (h1-h6) | Required |
| Landmarks | Header, nav, main, footer | Required |
| Forms | Labels, errors, grouping | Required |
| Images | Alt text for all images | Required |
| Keyboard | Full keyboard navigation | Required |
| Focus | Visible focus indicators | Required |
| Contrast | 4.5:1 text, 3:1 UI | Required |
| Motion | Respect prefers-reduced-motion | Required |
| ARIA | Use correctly when needed | Required |
| Testing | Automated + manual testing | Required |

---

## Cross-References

- [01-coding-standards-foundation.md](../01-foundation/01-coding-standards-foundation.md) - Component structure
- [01-internationalization-ux.md](./01-internationalization-ux.md) - Language and RTL support
- [03-performance-optimization-ux.md](./03-performance-optimization-ux.md) - Accessible loading states
