# 07. Section View

## Overview
Individual exam section page displaying markdown content, navigation, and completion actions.

---

## 07.1 Route & Protection

| Route | Protection |
|-------|------------|
| `/{slug}/section/{sectionNumber}` | Requires session + exam not locked |

### Access Rules
- Valid session required
- If exam locked → Show locked message, disable Mark as Done
- If section already completed → Show completed state

---

## 07.2 Page Layout

```
┌─────────────────────────────────────────┐
│  Header: Exam Title                     │
│  Breadcrumb: Dashboard > Section 3      │
│  Navigation: [←] Previous | [→] Next    │
└─────────────────────────────────────────┘
│
├─ Section Header
│  ├─ Progress: "Section 3 of 8"
│  ├─ Title: (H2 from markdown)
│  ├─ Deadline warning (if applicable)
│  └─ Completion badge (if done)
│
├─ Content Area
│  ├─ Full markdown content
│  ├─ Code blocks with syntax highlighting
│  └─ Links open in new tabs
│
├─ Sidebar (optional)
│  ├─ In-Exam Checklist
│  └─ Timeline view
│
├─ Action Buttons
│  ├─ [Mark as Done] (if not completed)
│  ├─ [✓ Completed] badge (if done)
│  └─ [Undo] button (optional)
│
├─ Navigation
│  ├─ [← Previous Section]
│  ├─ Progress: "3 of 8 completed"
│  └─ [Next Section →]
│
└─ Footer
```

---

## 07.3 Section Header

### Progress Indicator
- Format: "Section 3 of 8"
- Visual indicator showing position in sequence

### Title
- Displays H2 header from markdown
- Styled as prominent heading

### Deadline Warnings

| Condition | Message | Style |
|-----------|---------|-------|
| Past soft deadline | "⚠ You're past soft deadline" | Yellow warning |
| Past hard deadline | "🔒 Exam locked. Cannot mark as done." | Red alert |
| Extension approaching | "⚠ Extension deadline: X days" | Orange warning |

### Completion Badge
- Shows "✓ Completed" if section marked done
- Green badge styling

---

## 07.4 Content Rendering

### Markdown Support
- Headings (H1-H6)
- Paragraphs
- Lists (ordered, unordered, nested)
- Code blocks (inline and fenced with syntax highlighting)
- Links (open in new tab with `target="_blank"`)
- Emphasis (bold, italic, strikethrough)
- Blockquotes
- Tables (GFM support)

### Code Syntax Highlighting
- Use `highlight.js` or similar
- Support common languages: JavaScript, Python, PHP, HTML, CSS

### Link Behavior
- All links open in new tab
- External link icon indicator

---

## 07.5 Mark as Done Button

### States

| State | Button Text | Enabled |
|-------|-------------|---------|
| Not completed | "Mark as Done" | ✓ Yes |
| Completed | "✓ Completed" | No (badge only) |
| Locked | "Mark as Done (exam locked)" | No |
| Loading | "Marking..." + spinner | No |

### Click Behavior
1. Validate session and deadline
2. POST to `/api/participants/me/sections/{sectionNumber}/complete`
3. Show loading state
4. On success:
   - Show success message
   - Update UI to completed state
   - Update progress count
   - Optionally prompt for next section
5. On failure:
   - Show error message
   - Re-enable button

---

## 07.6 Navigation

### Previous/Next Buttons
- "← Previous Section" (hidden on first section)
- "Next Section →" (hidden on last section)
- Shows section title in tooltip

### Breadcrumb
- "Dashboard > Section 3: [Title]"
- Dashboard link navigates back
- Current section shown but not clickable

### Progress Footer
- "3 of 8 sections completed"
- Updates after marking section done

---

## 07.7 Timeline Sidebar (Optional)

```
┌─────────────────┐
│  Progress       │
├─────────────────┤
│  ✓ Section 1    │
│  ✓ Section 2    │
│  → Section 3    │  ← Current
│  ○ Section 4    │
│  ○ Section 5    │
└─────────────────┘
```

- Visual list of all sections
- Current section highlighted
- Completed sections have checkmarks
- Click to jump to section

---

## 07.8 API Dependencies

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `GET /api/exams/{id}/sections/{n}` | GET | Load section content |
| `POST /api/participants/me/sections/{n}/complete` | POST | Mark section done |
| `DELETE /api/participants/me/sections/{n}/complete` | DELETE | Undo completion |

---

## 07.9 Logged Events

| Event | Trigger | Details |
|-------|---------|---------|
| `sectionViewed` | Page load | `{sectionNumber, sectionTitle}` |
| `sectionMarkedDone` | Mark as Done clicked | `{sectionNumber, duration}` |
| `sectionUndone` | Undo clicked | `{sectionNumber}` |
| `navigationClicked` | Previous/Next clicked | `{from, to, direction}` |

---

## 07.10 Acceptance Criteria

### Content
- [ ] Markdown renders correctly (all supported elements)
- [ ] Code blocks have syntax highlighting
- [ ] Links open in new tabs

### Navigation
- [ ] Breadcrumb navigates to dashboard
- [ ] Previous/Next navigate correctly
- [ ] Timeline jumps to correct section

### Completion
- [ ] Mark as Done updates progress
- [ ] Completed state shows badge
- [ ] Locked state disables button
- [ ] Loading state shown during request

### Edge Cases
- [ ] Session expiry handled gracefully
- [ ] Hard deadline during session handled

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Markdown Rendering** | [08-markdown-rendering](08-markdown-rendering.md) | Content display |
| **Dashboard** | [06-dashboard-page](06-dashboard-page.md) | Breadcrumb target |
| **Locked State** | [15-locked-state](15-locked-state.md) | Deadline blocking |
| **Completion Flow** | [14-exam-completion-flow](14-exam-completion-flow.md) | Mark as done logic |

---

*Next: `08-markdown-rendering.md`*
