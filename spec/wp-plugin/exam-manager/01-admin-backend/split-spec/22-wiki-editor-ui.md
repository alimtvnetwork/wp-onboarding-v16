# 20 - Wiki Editor UI

## Overview

React-based Markdown editor for creating and editing Wiki pages. Features split-view preview, toolbar shortcuts, media management, and internal wiki linking with autocomplete.

---

## Dependencies

- `18-wiki-service.md` (wiki data operations)
- `19-wiki-categories.md` (category assignment)
- `06-entity-models.md` (Wiki entity)

---

## Functional Requirements

### 20.1 Editor Layout

```
LAYOUT STRUCTURE:
  ┌─────────────────────────────────────────────────┐
  │ [Toolbar]                              [Actions]│
  ├─────────────────────┬───────────────────────────┤
  │                     │                           │
  │   Markdown Editor   │    Live Preview           │
  │   (Monaco/CodeMirror)    (Rendered HTML)        │
  │                     │                           │
  ├─────────────────────┴───────────────────────────┤
  │ [Metadata Panel - collapsible]                  │
  └─────────────────────────────────────────────────┘
```

### 20.2 Editor Features

**Toolbar Actions**
| Action | Shortcut | Description |
|--------|----------|-------------|
| Bold | Ctrl+B | Wrap selection in `**` |
| Italic | Ctrl+I | Wrap selection in `*` |
| Heading | Ctrl+1-6 | Insert heading level |
| Link | Ctrl+K | Insert `[text](url)` |
| Wiki Link | Ctrl+W | Insert `[[slug]]` with picker |
| Image | Ctrl+Shift+I | Open media picker |
| Code | Ctrl+` | Inline code or block |
| Table | Ctrl+T | Insert table template |
| List | Ctrl+L | Toggle bullet list |
| Numbered | Ctrl+Shift+L | Toggle numbered list |
| Quote | Ctrl+Q | Insert blockquote |
| Divider | Ctrl+- | Insert horizontal rule |

**Editor Capabilities**
- Syntax highlighting for Markdown
- Line numbers (toggleable)
- Word wrap (toggleable)
- Find and replace (Ctrl+F, Ctrl+H)
- Undo/redo with history
- Auto-save indicator
- Word/character count

### 20.3 Live Preview

- Real-time rendering as user types
- Synchronized scroll with editor
- Toggle between split/preview-only/editor-only
- Mobile-responsive layout (stacked)
- CSS matches public wiki theme

### 20.4 Wiki Link Autocomplete

**Trigger**
- Typing `[[` opens suggestion dropdown
- Continues filtering as user types

**Dropdown Content**
- Wiki title and slug
- Category badge
- Visibility indicator
- Recently used wikis prioritized

**Completion**
- Select inserts `[[slug]]` or `[[slug|Title]]`
- Tab to add custom display text
- Escape to cancel

### 20.5 Media Management

**Image Upload**
- Drag-and-drop into editor
- Paste from clipboard
- Upload button in toolbar
- Automatic thumbnail generation
- Alt text prompt on insert

**Media Library**
- Grid view of uploaded images
- Search by filename
- Filter by date/type
- Delete with orphan check
- Copy markdown to clipboard

---

## Business Rules

### 20.6 Auto-Save Behavior

- [ ] Save draft every 30 seconds if changed
- [ ] Save on blur (tab switch, window close)
- [ ] Visual indicator: "Saved", "Saving...", "Unsaved"
- [ ] Conflict detection if edited elsewhere
- [ ] Manual save always available (Ctrl+S)

### 20.7 Content Validation

- [ ] Title required, max 255 characters
- [ ] Slug required, unique, auto-generated
- [ ] Content max 100,000 characters
- [ ] Warn on broken wiki links
- [ ] Warn on missing image alt text

### 20.8 Version Comparison

- [ ] "View History" button opens revision panel
- [ ] Side-by-side diff view
- [ ] Restore previous version
- [ ] Compare any two revisions
- [ ] Revision author and timestamp shown

---

## UI Components

### 20.9 Metadata Panel

**Collapsible Section**
- Title input
- Slug input (with validation)
- Category dropdown (with search)
- Visibility selector
- Role picker (if ROLE visibility)
- Publish/Draft toggle
- Tags input (if implemented)

### 20.10 Action Buttons

**Primary Actions**
- Save Draft (Ctrl+S)
- Publish (Ctrl+Shift+P)
- Preview Full Page

**Secondary Actions**
- View History
- Delete Wiki
- Export Markdown
- Copy Public Link

---

## Acceptance Criteria

### Editor Functionality
- [ ] All toolbar actions work correctly
- [ ] Keyboard shortcuts functional
- [ ] Syntax highlighting renders
- [ ] Find/replace works
- [ ] Undo/redo stack maintained

### Live Preview
- [ ] Updates within 200ms of typing
- [ ] Scroll sync works bidirectionally
- [ ] Layout modes toggle correctly
- [ ] Styling matches public view

### Wiki Linking
- [ ] `[[` triggers autocomplete
- [ ] Results filter as typing
- [ ] Selection inserts correct syntax
- [ ] Broken links highlighted
- [ ] Aliased links work `[[slug|text]]`

### Media Handling
- [ ] Drag-drop upload works
- [ ] Clipboard paste works
- [ ] Media library browsable
- [ ] Markdown inserted correctly
- [ ] Large files rejected with message

### Auto-Save
- [ ] Draft saves automatically
- [ ] Status indicator accurate
- [ ] Conflict detection works
- [ ] Manual save overrides auto-save

### Revisions
- [ ] History panel shows revisions
- [ ] Diff view renders correctly
- [ ] Restore creates new revision
- [ ] Author/date displayed

### Accessibility
- [ ] Keyboard navigation complete
- [ ] Screen reader announcements
- [ ] Focus management correct
- [ ] High contrast mode supported

---

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Network error on save | Retry with exponential backoff, show error |
| Slug conflict | Suggest alternative slug |
| Image upload fails | Show error, offer retry |
| Concurrent edit conflict | Show diff, offer merge |
| Invalid wiki link | Highlight in editor, warn on save |

---

## Performance Requirements

- [ ] Editor initializes < 500ms
- [ ] Preview updates < 200ms
- [ ] Autocomplete results < 100ms
- [ ] Image uploads show progress
- [ ] Large documents (10k words) remain responsive

---

## Notes

- Consider Monaco Editor or CodeMirror 6 for editor core
- Markdown parser should match backend (consistency)
- Preview CSS identical to public wiki styles
- Mobile editor simplified (no split view)
