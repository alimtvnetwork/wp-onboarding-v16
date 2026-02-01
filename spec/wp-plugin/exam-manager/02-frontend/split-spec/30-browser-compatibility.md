# 30. Browser Compatibility

## Overview
Supported browsers, feature detection, graceful degradation, and polyfill strategy for cross-browser compatibility.

---

## 30.1 Browser Support Matrix

### Fully Supported (Tier 1)
| Browser | Minimum Version | Notes |
|---------|-----------------|-------|
| Chrome | 100+ | Full feature support |
| Firefox | 100+ | Full feature support |
| Safari | 15+ | Full feature support |
| Edge | 100+ | Chromium-based |

### Supported with Graceful Degradation (Tier 2)
| Browser | Minimum Version | Degradation |
|---------|-----------------|-------------|
| Chrome | 90-99 | Minor visual differences |
| Firefox | 90-99 | Minor visual differences |
| Safari | 14 | Some CSS effects missing |
| Samsung Internet | 18+ | Full support |

### Not Supported
| Browser | Reason |
|---------|--------|
| Internet Explorer | End of life |
| Safari < 14 | Missing ES2020 features |
| Opera Mini | Limited JavaScript support |

---

## 30.2 Feature Requirements

### JavaScript Features Used
| Feature | Chrome | Firefox | Safari | Fallback |
|---------|--------|---------|--------|----------|
| `fetch()` | ✓ | ✓ | ✓ | Polyfill available |
| `async/await` | ✓ | ✓ | ✓ | Transpiled |
| `Optional chaining (?.)` | 80+ | 74+ | 13.1+ | Transpiled |
| `Nullish coalescing (??)` | 80+ | 72+ | 13.1+ | Transpiled |
| `Promise.allSettled` | 76+ | 71+ | 13+ | Polyfill |
| `Array.prototype.flat` | 69+ | 62+ | 12+ | Polyfill |
| `Intl.DateTimeFormat` | ✓ | ✓ | ✓ | None needed |
| `Intl.RelativeTimeFormat` | 71+ | 65+ | 14+ | Polyfill |

### CSS Features Used
| Feature | Chrome | Firefox | Safari | Fallback |
|---------|--------|---------|--------|----------|
| CSS Grid | ✓ | ✓ | ✓ | None (required) |
| Flexbox | ✓ | ✓ | ✓ | None (required) |
| CSS Variables | ✓ | ✓ | ✓ | None (required) |
| `gap` in Flexbox | 84+ | 63+ | 14.1+ | Margin fallback |
| `aspect-ratio` | 88+ | 89+ | 15+ | Padding trick |
| `backdrop-filter` | 76+ | 103+ | 9+ | Solid background |
| `scroll-behavior: smooth` | 61+ | 36+ | 15.4+ | JavaScript fallback |

---

## 30.3 Feature Detection Pattern

### JavaScript Feature Detection
```javascript
// Feature detection before use
const supportsOptionalChaining = (() => {
  try {
    eval('const obj = {}; obj?.prop;');
    return true;
  } catch (e) {
    return false;
  }
})();

// CSS feature detection
const supportsBackdropFilter = CSS.supports('backdrop-filter', 'blur(10px)');

// Apply class based on support
if (!supportsBackdropFilter) {
  document.documentElement.classList.add('no-backdrop-filter');
}
```

### CSS Fallback Pattern
```css
/* Fallback first, then modern */
.modal-overlay {
  background: rgba(0, 0, 0, 0.8); /* Fallback */
}

@supports (backdrop-filter: blur(10px)) {
  .modal-overlay {
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(10px);
  }
}
```

---

## 30.4 Polyfill Strategy

### Core Polyfills (Always Load)
```html
<!-- Only for unsupported browsers -->
<script nomodule src="polyfills.js"></script>
```

### Polyfills Included
| Polyfill | Purpose | Size |
|----------|---------|------|
| `core-js/stable` | ES2015-2022 features | ~10KB (selective) |
| `whatwg-fetch` | Fetch API | ~3KB |
| `intersection-observer` | Lazy loading | ~2KB |

### Conditional Loading
```javascript
// Load polyfills only if needed
if (!('IntersectionObserver' in window)) {
  import('intersection-observer');
}
```

---

## 30.5 Mobile Browser Considerations

### Touch Targets
- Minimum touch target: 48×48 pixels
- Touch spacing: 8px minimum between targets
- No hover-dependent interactions

### Viewport Issues
```html
<!-- Prevent zoom on input focus (iOS) -->
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

<!-- Allow pinch zoom -->
<meta name="viewport" content="width=device-width, initial-scale=1">
```

### iOS Safari Specific
| Issue | Solution |
|-------|----------|
| 100vh includes address bar | Use `100dvh` or JavaScript fix |
| Overscroll bounce | `overscroll-behavior: contain` |
| Date input formatting | Use text input with date picker |
| Position fixed in modals | Use `transform: translate3d(0,0,0)` |

### Android Chrome Specific
| Issue | Solution |
|-------|----------|
| Pull-to-refresh interference | `overscroll-behavior-y: contain` |
| Address bar resize | Use CSS `dvh` units |

---

## 30.6 Testing Requirements

### Browser Testing Matrix
| Browser | Platforms | Priority |
|---------|-----------|----------|
| Chrome | Windows, Mac, Android | High |
| Safari | Mac, iOS | High |
| Firefox | Windows, Mac | Medium |
| Edge | Windows | Medium |
| Samsung Internet | Android | Low |

### Testing Checklist Per Browser
- [ ] Login/signup flow completes
- [ ] Exam content renders correctly
- [ ] Markdown code blocks display
- [ ] File uploads work
- [ ] Countdown timer updates
- [ ] Forms validate properly
- [ ] Mobile navigation works
- [ ] No console errors

### Tools
| Tool | Purpose |
|------|---------|
| BrowserStack | Cross-browser testing |
| Chrome DevTools | Device emulation |
| Firefox Responsive Mode | Mobile testing |
| Safari Web Inspector | iOS debugging |

---

## 30.7 Accessibility in Browsers

### Screen Reader Testing
| Browser | Screen Reader | Priority |
|---------|---------------|----------|
| Chrome | ChromeVox, NVDA | High |
| Firefox | NVDA, JAWS | High |
| Safari | VoiceOver | High |
| Edge | Narrator | Medium |

### Focus Management
```css
/* Visible focus for all browsers */
:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: 2px;
}

/* Fallback for browsers without :focus-visible */
:focus {
  outline: 2px solid var(--primary);
  outline-offset: 2px;
}
:focus:not(:focus-visible) {
  outline: none;
}
```

---

## 30.8 Common Pitfalls

### ❌ Anti-Patterns
- Using `-webkit-` prefixes without standard version
- Testing only in Chrome
- Ignoring iOS Safari quirks
- Assuming consistent date handling
- Using `window.event` (not standard)
- Relying on browser-specific scrollbar styling

### ✅ Best Practices
- Test on real devices, not just emulators
- Use Autoprefixer for CSS vendor prefixes
- Test date/time formatting with different locales
- Check keyboard navigation in all browsers
- Validate form behavior on mobile
- Test with slow network throttling

---

## 30.9 Graceful Degradation Examples

### Modern CSS with Fallback
```css
/* Container queries with fallback */
.card {
  /* Fallback: viewport-based */
  @media (min-width: 640px) {
    display: grid;
    grid-template-columns: 1fr 2fr;
  }
}

/* Enhancement: container query */
@supports (container-type: inline-size) {
  .card-container {
    container-type: inline-size;
  }
  
  .card {
    @container (min-width: 400px) {
      display: grid;
      grid-template-columns: 1fr 2fr;
    }
  }
}
```

### JavaScript API Fallback
```javascript
// Copy to clipboard with fallback
async function copyToClipboard(text) {
  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(text);
  } else {
    // Fallback: execCommand
    const textArea = document.createElement('textarea');
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);
  }
}
```

---

## 30.10 Acceptance Criteria

### Browser Support
- [ ] Tier 1 browsers: full functionality
- [ ] Tier 2 browsers: core functionality
- [ ] Mobile browsers: responsive layout
- [ ] No JavaScript errors in supported browsers

### Feature Detection
- [ ] Missing features don't break app
- [ ] Polyfills loaded only when needed
- [ ] Fallback styles applied correctly
- [ ] User informed if browser unsupported

### Testing
- [ ] Manual testing on all Tier 1 browsers
- [ ] Automated tests run in Chrome + Firefox
- [ ] Mobile testing on iOS + Android
- [ ] Accessibility testing with screen readers

---

## Related Specifications

| Topic | Spec |
|-------|------|
| Responsive Design | [23-responsive-design.md](23-responsive-design.md) |
| UI Design System | [22-ui-design-system.md](22-ui-design-system.md) |
| Performance | [27-performance-targets.md](27-performance-targets.md) |
| Tech Stack | [24-tech-stack.md](24-tech-stack.md) |
