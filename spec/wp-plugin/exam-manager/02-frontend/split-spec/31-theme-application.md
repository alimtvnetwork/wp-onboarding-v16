# 31. Frontend Theme Application

## Overview

This document specifies how themes are applied to the frontend participant-facing interface. Themes are resolved server-side and injected as CSS variables, ensuring consistent styling across all components.

---

## 1. Theme Delivery Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                   THEME DELIVERY PIPELINE                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Page Request                                                 │
│     └── Check session for cached theme                          │
│                                                                  │
│  2. Theme Resolution                                             │
│     └── Session hit → Use cached CSS variables                  │
│     └── Session miss → Fetch from ThemeService                  │
│                                                                  │
│  3. CSS Variable Injection                                       │
│     └── Inject <style id="eqm-theme"> in <head>                 │
│                                                                  │
│  4. Component Rendering                                          │
│     └── All components use CSS variables                        │
│                                                                  │
│  5. Theme Caching                                                │
│     └── Store in session for next request                       │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. CSS Variable Injection

### 2.1 Theme Style Block

```html
<head>
  <!-- Theme CSS Variables - Injected by PHP -->
  <style id="eqm-theme-variables">
    :root {
      /* Primary Colors */
      --primary: 222.2 47.4% 11.2%;
      --primary-foreground: 210 40% 98%;
      --primary-hover: 222.2 47.4% 15%;
      
      /* Background Colors */
      --background: 0 0% 100%;
      --card: 0 0% 100%;
      --muted: 210 40% 96.1%;
      --muted-foreground: 215.4 16.3% 46.9%;
      
      /* Typography */
      --font-sans: Inter, system-ui, sans-serif;
      --font-mono: JetBrains Mono, monospace;
      --text-base: 1rem;
      
      /* Spacing */
      --spacing-unit: 0.25rem;
      --container-padding: 1.5rem;
      
      /* Borders */
      --radius: 0.5rem;
      --border: 214.3 31.8% 91.4%;
      
      /* Forms */
      --input-height: 2.5rem;
      --input-radius: 0.5rem;
      
      /* Markdown */
      --prose-max-width: 65ch;
      --prose-line-height: 1.75;
      
      /* Deadline Colors */
      --deadline-on-track: 142 76% 36%;
      --deadline-soft-passed: 48 96% 53%;
      --deadline-approaching: 25 95% 53%;
      --deadline-passed: 0 84% 60%;
      --deadline-locked: 0 72% 51%;
      --deadline-expired: 0 0% 9%;
    }
    
    /* Dark Mode */
    .dark {
      --background: 222.2 84% 4.9%;
      --foreground: 210 40% 98%;
      --card: 222.2 84% 4.9%;
      --muted: 217.2 32.6% 17.5%;
      /* ... additional dark mode overrides */
    }
  </style>
</head>
```

### 2.2 PHP Injection Template

```php
<?php
// In theme injection helper
function eqm_inject_theme_styles(): void {
    $themeService = EQM\Services\ThemeService::getInstance();
    $css = $themeService->getCssVariables(ThemeScope::FRONTEND);
    
    echo "<style id=\"eqm-theme-variables\">{$css}</style>";
}

// Hook into wp_head
add_action('wp_head', 'eqm_inject_theme_styles', 5);
```

---

## 3. Component Styling Standards

### 3.1 Color Usage

```css
/* ✅ CORRECT - Use CSS variables */
.button-primary {
  background-color: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
}

.button-primary:hover {
  background-color: hsl(var(--primary-hover));
}

/* ❌ WRONG - Hardcoded colors */
.button-primary {
  background-color: #1e3a5f;
  color: white;
}
```

### 3.2 Typography

```css
/* ✅ CORRECT - Theme typography */
.prose {
  font-family: var(--font-sans);
  font-size: var(--text-base);
  line-height: var(--prose-line-height);
  max-width: var(--prose-max-width);
}

.code-block {
  font-family: var(--font-mono);
}
```

### 3.3 Spacing

```css
/* ✅ CORRECT - Theme spacing */
.section {
  padding: var(--container-padding);
  margin-bottom: calc(var(--spacing-unit) * 8);
}
```

### 3.4 Borders & Radius

```css
/* ✅ CORRECT - Theme borders */
.card {
  border-radius: var(--radius);
  border: 1px solid hsl(var(--border));
}
```

---

## 4. Form Element Styling

### 4.1 Input Fields

```css
.eqm-input {
  height: var(--input-height);
  border-radius: var(--input-radius);
  border: 1px solid hsl(var(--border));
  padding: var(--input-padding, 0.75rem 1rem);
  font-family: var(--font-sans);
  font-size: var(--text-base);
  background: hsl(var(--background));
  color: hsl(var(--foreground));
  transition: border-color 150ms, box-shadow 150ms;
}

.eqm-input:focus {
  outline: none;
  border-color: hsl(var(--ring));
  box-shadow: 0 0 0 2px hsl(var(--ring) / 0.2);
}

.eqm-input::placeholder {
  color: hsl(var(--muted-foreground));
}

.eqm-input:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
```

### 4.2 Textarea

```css
.eqm-textarea {
  min-height: var(--textarea-min-height, 6rem);
  max-height: var(--textarea-max-height, 20rem);
  resize: vertical;
  line-height: var(--prose-line-height);
  /* Inherit input styles */
}
```

### 4.3 Select Dropdown

```css
.eqm-select {
  height: var(--input-height);
  border-radius: var(--input-radius);
  border: 1px solid hsl(var(--border));
  padding: 0 2.5rem 0 1rem;
  background: hsl(var(--background)) url('data:image/svg+xml,...') no-repeat right 0.75rem center;
  background-size: 1rem;
  appearance: none;
}
```

### 4.4 Checkbox & Radio

```css
.eqm-checkbox {
  width: var(--checkbox-size, 1.25rem);
  height: var(--checkbox-size, 1.25rem);
  border-radius: var(--checkbox-radius, 0.25rem);
  border: 2px solid hsl(var(--border));
  appearance: none;
  cursor: pointer;
}

.eqm-checkbox:checked {
  background-color: hsl(var(--primary));
  border-color: hsl(var(--primary));
  background-image: url('data:image/svg+xml,...'); /* checkmark */
}

.eqm-radio {
  border-radius: 50%;
}
```

### 4.5 Buttons

```css
.eqm-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: var(--button-height-md, 2.5rem);
  padding: 0 var(--button-padding-md, 1rem);
  border-radius: var(--radius);
  font-family: var(--font-sans);
  font-weight: 500;
  transition: background-color 150ms, transform 100ms;
}

.eqm-button:active {
  transform: scale(0.98);
}

/* Variants */
.eqm-button--primary {
  background-color: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
}

.eqm-button--secondary {
  background-color: hsl(var(--secondary));
  color: hsl(var(--secondary-foreground));
}

.eqm-button--destructive {
  background-color: hsl(var(--destructive));
  color: hsl(var(--destructive-foreground));
}

/* Sizes */
.eqm-button--sm {
  height: var(--button-height-sm, 2rem);
  padding: 0 var(--button-padding-sm, 0.75rem);
  font-size: 0.875rem;
}

.eqm-button--lg {
  height: var(--button-height-lg, 3rem);
  padding: 0 var(--button-padding-lg, 1.5rem);
  font-size: 1.125rem;
}
```

---

## 5. Markdown Rendering

### 5.1 Prose Container

```css
.eqm-prose {
  max-width: var(--prose-max-width);
  line-height: var(--prose-line-height);
  color: hsl(var(--foreground));
}

.eqm-prose > * + * {
  margin-top: var(--prose-paragraph-spacing, 1.5rem);
}
```

### 5.2 Headings

```css
.eqm-prose h1 {
  font-size: var(--heading-h1-size, 2.25rem);
  font-weight: var(--heading-h1-weight, 700);
  margin-top: var(--heading-h1-margin, 2rem);
  line-height: 1.25;
}

.eqm-prose h2 {
  font-size: var(--heading-h2-size, 1.875rem);
  font-weight: var(--heading-h2-weight, 600);
  margin-top: var(--heading-h2-margin, 1.75rem);
  line-height: 1.3;
  /* Section marker for progress tracking */
  scroll-margin-top: 80px;
}

.eqm-prose h3 {
  font-size: var(--heading-h3-size, 1.5rem);
  font-weight: var(--heading-h3-weight, 600);
  margin-top: var(--heading-h3-margin, 1.5rem);
}

.eqm-prose h4 {
  font-size: var(--heading-h4-size, 1.25rem);
  font-weight: var(--heading-h4-weight, 600);
  margin-top: var(--heading-h4-margin, 1.25rem);
}
```

### 5.3 Code Blocks

```css
/* Inline code */
.eqm-prose code:not(pre code) {
  background: hsl(var(--muted));
  padding: var(--code-inline-padding, 0.125rem 0.375rem);
  border-radius: var(--code-inline-radius, 0.25rem);
  font-family: var(--font-mono);
  font-size: 0.875em;
}

/* Code blocks */
.eqm-prose pre {
  background: hsl(var(--code-block-bg, 222.2 84% 4.9%));
  color: hsl(var(--code-block-fg, 210 40% 98%));
  padding: var(--code-block-padding, 1rem);
  border-radius: var(--code-block-radius, 0.5rem);
  overflow-x: auto;
  font-family: var(--font-mono);
  font-size: 0.875rem;
  line-height: 1.6;
}

.eqm-prose pre code {
  background: transparent;
  padding: 0;
  border-radius: 0;
}

/* Line numbers */
.eqm-prose pre.line-numbers {
  padding-left: 3.5rem;
  position: relative;
}

.eqm-prose pre.line-numbers::before {
  content: attr(data-line-numbers);
  position: absolute;
  left: 0;
  top: 1rem;
  width: 2.5rem;
  text-align: right;
  color: hsl(var(--muted-foreground));
  font-size: 0.75rem;
  user-select: none;
}
```

### 5.4 Blockquotes

```css
.eqm-prose blockquote {
  border-left: var(--blockquote-border-width, 4px) solid hsl(var(--primary));
  background: hsl(var(--muted));
  padding: var(--blockquote-padding, 1rem 1.5rem);
  margin: 1.5rem 0;
  font-style: italic;
}

.eqm-prose blockquote p {
  margin: 0;
}
```

### 5.5 Tables

```css
.eqm-prose table {
  width: 100%;
  border-collapse: collapse;
  margin: 1.5rem 0;
}

.eqm-prose th {
  background: hsl(var(--muted));
  font-weight: 600;
  text-align: left;
  padding: var(--table-cell-padding, 0.75rem 1rem);
  border: 1px solid hsl(var(--border));
}

.eqm-prose td {
  padding: var(--table-cell-padding, 0.75rem 1rem);
  border: 1px solid hsl(var(--border));
}

/* Striped rows */
.eqm-prose tr:nth-child(even) {
  background: hsl(var(--muted) / 0.5);
}
```

### 5.6 Lists

```css
.eqm-prose ul,
.eqm-prose ol {
  padding-left: var(--list-indent, 1.5rem);
}

.eqm-prose li {
  margin: var(--list-item-spacing, 0.5rem) 0;
}

.eqm-prose ul > li::marker {
  color: hsl(var(--primary));
}

.eqm-prose ol > li::marker {
  color: hsl(var(--primary));
  font-weight: 600;
}

/* Nested lists */
.eqm-prose li > ul,
.eqm-prose li > ol {
  margin-top: 0.5rem;
}
```

### 5.7 Links

```css
.eqm-prose a {
  color: hsl(var(--primary));
  text-decoration: underline;
  text-underline-offset: 2px;
  transition: color 150ms;
}

.eqm-prose a:hover {
  color: hsl(var(--primary-hover));
}

/* Wiki links */
.eqm-prose a[data-wiki-link] {
  border-bottom: 1px dashed hsl(var(--primary));
  text-decoration: none;
}
```

---

## 6. Deadline Color Indicators

### 6.1 Status Classes

```css
/* Deadline countdown colors */
.deadline-on-track {
  color: hsl(var(--deadline-on-track));
}

.deadline-soft-passed {
  color: hsl(var(--deadline-soft-passed));
}

.deadline-approaching {
  color: hsl(var(--deadline-approaching));
}

.deadline-passed {
  color: hsl(var(--deadline-passed));
}

.deadline-locked {
  color: hsl(var(--deadline-locked));
}

.deadline-expired {
  color: hsl(var(--deadline-expired));
}

/* Background variants */
.deadline-badge {
  padding: 0.25rem 0.75rem;
  border-radius: var(--radius);
  font-size: 0.875rem;
  font-weight: 500;
}

.deadline-badge--on-track {
  background: hsl(var(--deadline-on-track) / 0.1);
  color: hsl(var(--deadline-on-track));
}

.deadline-badge--soft-passed {
  background: hsl(var(--deadline-soft-passed) / 0.1);
  color: hsl(var(--deadline-soft-passed));
}

/* ... etc for other states */
```

---

## 7. Dark Mode Toggle

### 7.1 Theme Toggle Component

```html
<button class="theme-toggle" aria-label="Toggle dark mode">
  <svg class="theme-toggle__sun" viewBox="0 0 24 24">...</svg>
  <svg class="theme-toggle__moon" viewBox="0 0 24 24">...</svg>
</button>
```

```css
.theme-toggle {
  position: relative;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  background: hsl(var(--muted));
  border: none;
  cursor: pointer;
}

.theme-toggle__sun,
.theme-toggle__moon {
  position: absolute;
  inset: 0.5rem;
  transition: opacity 200ms, transform 200ms;
}

/* Light mode - show sun */
:root:not(.dark) .theme-toggle__sun {
  opacity: 1;
  transform: rotate(0deg);
}

:root:not(.dark) .theme-toggle__moon {
  opacity: 0;
  transform: rotate(-90deg);
}

/* Dark mode - show moon */
.dark .theme-toggle__sun {
  opacity: 0;
  transform: rotate(90deg);
}

.dark .theme-toggle__moon {
  opacity: 1;
  transform: rotate(0deg);
}
```

### 7.2 Theme Preference Persistence

```javascript
// Check for saved preference or system preference
function initTheme() {
  const saved = localStorage.getItem('eqm-theme');
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  
  if (saved === 'dark' || (!saved && prefersDark)) {
    document.documentElement.classList.add('dark');
  }
}

// Toggle theme
function toggleTheme() {
  const isDark = document.documentElement.classList.toggle('dark');
  localStorage.setItem('eqm-theme', isDark ? 'dark' : 'light');
  
  // Fire-and-forget API call to save preference
  fetch('/api/user/theme-preference', {
    method: 'POST',
    body: JSON.stringify({ theme: isDark ? 'dark' : 'light' })
  }).catch(() => {}); // Ignore errors
}

// Initialize on page load
initTheme();
```

---

## 8. Layout Theming

### 8.1 Container Widths

```css
.eqm-container {
  width: 100%;
  margin: 0 auto;
  padding: 0 var(--container-padding);
}

.eqm-container--sm { max-width: var(--container-sm, 640px); }
.eqm-container--md { max-width: var(--container-md, 768px); }
.eqm-container--lg { max-width: var(--container-lg, 1024px); }
.eqm-container--xl { max-width: var(--container-xl, 1280px); }
```

### 8.2 Sidebar

```css
.eqm-sidebar {
  width: var(--sidebar-width, 280px);
  background: hsl(var(--sidebar-background));
  border-right: 1px solid hsl(var(--sidebar-border));
  transition: width 200ms;
}

.eqm-sidebar--collapsed {
  width: var(--sidebar-collapsed-width, 64px);
}
```

### 8.3 Header

```css
.eqm-header {
  height: var(--header-height, 64px);
  background: hsl(var(--background));
  border-bottom: 1px solid hsl(var(--border));
  display: flex;
  align-items: center;
  padding: 0 var(--container-padding);
}
```

---

## 9. Animation Theming

### 9.1 Transition Durations

```css
/* Use theme variables for consistent animations */
.fade-in {
  animation: fadeIn var(--animation-normal, 300ms) var(--easing-default);
}

.slide-in {
  animation: slideIn var(--animation-normal, 300ms) var(--easing-out);
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideIn {
  from { transform: translateY(10px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}
```

### 9.2 Reduced Motion

```css
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

---

## 10. Common Pitfalls

### ❌ WRONG: Hardcoded Values

```css
/* WRONG */
.card { background: #ffffff; }
.text { color: #1a1a1a; }
```

### ✅ CORRECT: CSS Variables

```css
/* CORRECT */
.card { background: hsl(var(--card)); }
.text { color: hsl(var(--foreground)); }
```

### ❌ WRONG: Ignoring Dark Mode

```css
/* WRONG - only works in light mode */
.highlight { background: rgba(0, 0, 0, 0.1); }
```

### ✅ CORRECT: Use Theme Variables

```css
/* CORRECT - adapts to theme */
.highlight { background: hsl(var(--muted)); }
```

---

## 11. Cross-References

- **Theme System**: `../../01-admin-backend/split-spec/56-theming-system.md`
- **Markdown Rendering**: `08-markdown-rendering.md`
- **UI Design System**: `22-ui-design-system.md`
- **Responsive Design**: `23-responsive-design.md`
- **Shared Constants**: `../../66-shared-constants.md` (Deadline color values)
