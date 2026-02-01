# 08. Markdown Rendering

## Overview
Client-side markdown parsing and rendering for exam content with support for all standard markdown features.

---

## 08.1 Supported Markdown Elements

### Text Formatting
| Element | Markdown | Rendered |
|---------|----------|----------|
| Bold | `**text**` | **text** |
| Italic | `*text*` | *text* |
| Strikethrough | `~~text~~` | ~~text~~ |
| Inline code | `` `code` `` | `code` |

### Headings
```markdown
# H1 - Exam Title (used once)
## H2 - Section Headers (progress tracking)
### H3 - Sub-sections
#### H4-H6 - Content hierarchy
```

### Lists
```markdown
- Unordered item
- Nested:
  - Sub-item
  
1. Ordered item
2. Second item
   1. Nested numbered
```

### Code Blocks
````markdown
```javascript
const example = "syntax highlighted";
console.log(example);
```
````

### Links
```markdown
[Link Text](https://example.com)
```
→ Opens in new tab with `target="_blank"`

### Blockquotes
```markdown
> Important note or quote
```

### Tables (GFM)
```markdown
| Header 1 | Header 2 |
|----------|----------|
| Cell 1   | Cell 2   |
```

---

## 08.2 Section Card Display

Section cards on dashboard show preview of markdown content:

```
┌─────────────────────────────────┐
│  Section 1: Introduction        │
│  "Learn the basics of..."       │  ← First 80 chars
│  [○ Not Started]                │
└─────────────────────────────────┘
```

### Preview Text Rules
- Extract first paragraph after H2
- Truncate to 80 characters
- Add ellipsis if truncated
- Strip markdown formatting for preview

---

## 08.3 Code Syntax Highlighting

### Supported Languages
- JavaScript / TypeScript
- Python
- PHP
- HTML / CSS
- JSON
- Bash / Shell
- SQL
- And common others

### Library Recommendation
- `highlight.js` for automatic detection
- Or `prism.js` for explicit language support

### Styling
- Dark theme for code blocks
- Line numbers (optional)
- Copy button in corner

---

## 08.4 Link Handling

### External Links
- All links open in new tab: `target="_blank"`
- Add `rel="noopener noreferrer"` for security
- Optional: External link icon (↗)

### Internal Wiki Links
```markdown
[[Page Name]]
```
→ Converted to internal wiki URL

---

## 08.5 Image Handling

```markdown
![Alt text](image-url.jpg)
```

### Behavior
- Images displayed inline
- Responsive sizing (max-width: 100%)
- Alt text for accessibility
- Click to open full size (optional lightbox)

---

## 08.6 Special Rendering

### Exam-Specific Features

| Markdown | Rendered |
|----------|----------|
| `[[wiki-page]]` | Internal wiki link |
| `:::note` | Styled note block |
| `:::warning` | Styled warning block |

### Custom Blocks (if implemented)
```markdown
:::note
This is an important note.
:::
```

---

## 08.7 Implementation

### Recommended Libraries
- **marked.js** - Fast, lightweight markdown parser
- **markdown-it** - Extensible with plugins
- **highlight.js** - Syntax highlighting

### Example Implementation
```javascript
import { marked } from 'marked';
import hljs from 'highlight.js';

// Configure marked with highlight.js
marked.setOptions({
  highlight: (code, lang) => {
    if (lang && hljs.getLanguage(lang)) {
      return hljs.highlight(code, { language: lang }).value;
    }
    return hljs.highlightAuto(code).value;
  },
  breaks: true,
  gfm: true
});

// Render markdown
const html = marked.parse(markdownContent);
```

### Link Transformation
```javascript
// Make all links open in new tab
const renderer = new marked.Renderer();
renderer.link = (href, title, text) => {
  return `<a href="${href}" target="_blank" rel="noopener noreferrer">${text}</a>`;
};
```

---

## 08.8 Styling

### Typography
- Font: System sans-serif or custom
- Line height: 1.6 for readability
- Paragraph spacing: 1em

### Code Blocks
- Background: Dark (e.g., #1e1e1e)
- Font: Monospace (Fira Code, Consolas)
- Padding: 1em
- Border radius: 4px

### Tables
- Full width
- Alternating row colors
- Header styling

---

## 08.9 Acceptance Criteria

### Core Rendering
- [ ] Headings (H1-H6) render correctly
- [ ] Paragraphs have proper spacing
- [ ] Lists (ordered/unordered/nested) render
- [ ] Bold, italic, strikethrough work

### Code
- [ ] Fenced code blocks display
- [ ] Syntax highlighting applied
- [ ] Language detection works
- [ ] Copy button functional (if implemented)

### Links & Media
- [ ] Links open in new tabs
- [ ] Images display and resize
- [ ] Tables render with styling

### Performance
- [ ] Large documents render efficiently
- [ ] No layout shift during load

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Section View** | [07-section-view](07-section-view.md) | Content container |
| **H2 Extraction** | [12-exam-service](../../01-admin-backend/split-spec/12-exam-service.md) | Backend section parsing |
| **Wiki Links** | [20-wiki-service](../../01-admin-backend/split-spec/20-wiki-service.md) | Wiki link resolution |

---

*Next: `09-prerequisites-display.md`*
