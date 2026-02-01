# 36. Exam List View

## Overview
Admin interface for viewing, searching, filtering, and managing all exams.

---

## 36.1 List Table Structure

### Columns
| Column | Sortable | Description |
|--------|----------|-------------|
| Checkbox | No | Bulk selection |
| Title | Yes | Exam title (linked to edit) |
| Status | Yes | Current status badge |
| Participants | Yes | Count of enrolled participants |
| Completion Rate | Yes | Percentage completed |
| Created | Yes | Creation date |
| Actions | No | Quick action buttons |

### Acceptance Criteria:
- [ ] Table displays all exams with pagination
- [ ] Column headers clickable for sorting
- [ ] Sort direction indicator shown
- [ ] Default sort: Created date descending

---

## 36.2 Search and Filters

### Search
- Full-text search on title and description
- Debounced input (300ms delay)
- Clear button when text present

### Filters
- **Status**: Dropdown with all status options + "All"
- **Date Range**: Created date from/to pickers
- **Has Participants**: Yes / No / All

### Acceptance Criteria:
- [ ] Search updates results in real-time
- [ ] Multiple filters can be combined
- [ ] Active filters shown as removable chips
- [ ] "Clear all filters" button when filters active
- [ ] Filter state persisted in URL query params

---

## 36.3 Bulk Actions

### Available Actions
- **Delete Selected**: Soft delete with confirmation
- **Change Status**: Dropdown to set new status
- **Export Selected**: Download as JSON/CSV

### Acceptance Criteria:
- [ ] Actions only enabled when items selected
- [ ] Selection count shown in toolbar
- [ ] "Select all" selects current page only
- [ ] "Select all X items" option for full dataset
- [ ] Confirmation dialog for destructive actions

---

## 36.4 Quick Actions Per Row

### Action Buttons
- **Edit**: Navigate to exam editor
- **Duplicate**: Clone exam with new title
- **View Participants**: Navigate to filtered list
- **Delete**: Soft delete with confirmation

### Acceptance Criteria:
- [ ] Actions shown on hover or in dropdown menu
- [ ] Duplicate creates copy with "(Copy)" suffix
- [ ] Delete moves to trash, not permanent
- [ ] Actions respect RBAC permissions

---

## 36.5 Empty State

### When No Exams Exist
- Illustration or icon
- "No exams yet" message
- "Create your first exam" button

### When Filters Return No Results
- "No exams match your filters" message
- "Clear filters" button

### Acceptance Criteria:
- [ ] Appropriate empty state for each scenario
- [ ] CTA button navigates to create flow
- [ ] Empty state visually distinct but not intrusive

---

## 36.6 Pagination

### Controls
- Page size selector (10, 20, 50, 100)
- Page number navigation
- "Showing X-Y of Z" indicator

### Acceptance Criteria:
- [ ] Page size preference saved to user settings
- [ ] URL updates with page number
- [ ] Keyboard navigation (arrow keys)
- [ ] Jump to first/last page buttons
