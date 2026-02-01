# 12. Exam Editor UI

## Overview
Tabbed interface for comprehensive exam editing with 6 specialized tabs.

---

## 12.1 Editor Layout

### Structure
- Header: Exam title, status badge, save/publish buttons
- Tab bar: 6 tabs for different aspects
- Content area: Tab-specific interface
- Footer: Last saved timestamp, action buttons

### Tab List
1. Content - Markdown editor
2. Metadata - Title, description, settings
3. Sub-Exams - Hierarchy management
4. Prerequisites - Required items
5. Checklists - Progress tracking items
6. Secret Keys - Access key management

### Acceptance Criteria:
- [ ] Tabs switch without page reload
- [ ] Unsaved changes warning on tab switch
- [ ] Current tab indicated visually
- [ ] Tab state persisted in URL hash
- [ ] Responsive layout for all screens

---

## 12.2 Auto-Save Functionality

### Behavior
- Auto-save every 30 seconds if changes
- Manual save button always available
- Save indicator shows status
- Conflict detection for concurrent edits

### Save States
- "Saved" - No pending changes
- "Saving..." - Save in progress
- "Unsaved changes" - Changes pending
- "Save failed" - Error occurred (retry button)

### Acceptance Criteria:
- [ ] Auto-save triggers on pause in editing
- [ ] Manual save works immediately
- [ ] Network errors handled gracefully
- [ ] Conflict shows diff and options
- [ ] Offline changes queued

---

## 12.3 Publish Workflow

### Pre-Publish Validation
- Content exists and not empty
- Required metadata fields set
- At least one checklist item
- No validation errors

### Publish Actions
- "Save as Draft" - Keep DRAFT status
- "Publish" - Set to PUBLISHED
- "Schedule" - Set future publish date

### Acceptance Criteria:
- [ ] Validation runs before publish
- [ ] Errors shown with links to fix
- [ ] Publish confirmation dialog
- [ ] Published exams show differently
- [ ] Unpublish option available

---

## 12.4 Version History

### History Features
- View previous versions
- Compare two versions (diff)
- Restore previous version
- Version notes/comments

### Version Creation
- Created on each save
- Maximum 50 versions kept
- Older versions archived

### Acceptance Criteria:
- [ ] History panel accessible
- [ ] Visual diff highlighting
- [ ] Restore creates new version
- [ ] Version timestamps shown
- [ ] Storage limits enforced

---

## 12.5 Editor Permissions

### Permission Levels
- **View**: Read-only access to all tabs
- **Edit**: Modify content and settings
- **Publish**: Change exam status
- **Delete**: Remove exam

### Role Mappings
- Admin: All permissions
- Editor: View, Edit, Publish
- Examinee: None (no editor access)

### Acceptance Criteria:
- [ ] Tabs show/hide based on permission
- [ ] Edit controls disabled if view-only
- [ ] Permission check on all API calls
- [ ] Clear indication of access level

---

## 12.6 Editor Navigation

### Quick Actions
- Search within exam content
- Jump to section (from TOC)
- Keyboard shortcuts
- Breadcrumb navigation

### Keyboard Shortcuts
- Ctrl+S: Save
- Ctrl+Shift+P: Publish
- Tab/Shift+Tab: Navigate tabs
- Escape: Close modals

### Acceptance Criteria:
- [ ] Shortcuts documented in help
- [ ] Shortcuts work in all tabs
- [ ] No conflicts with browser shortcuts
- [ ] Focus management correct
