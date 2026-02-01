# 15. Exam Sub-Exams Tab

## Overview
Third tab in exam editor for managing hierarchical parent-child exam relationships.

---

## 15.1 Sub-Exam List

### Display
- List of direct child exams
- Nested tree for deeper hierarchy
- Status badge per child
- Participant count per child

### List Columns
| Column | Description |
|--------|-------------|
| Order | Drag handle for sorting |
| Title | Child exam title (linked) |
| Status | DRAFT/PUBLISHED/ARCHIVED |
| Participants | Count enrolled |
| Deadline Mode | Independent/Inherited |
| Actions | Edit, Unlink, Delete |

### Acceptance Criteria:
- [ ] Children listed in sort order
- [ ] Drag-drop reordering works
- [ ] Status badges color-coded
- [ ] Click title opens child editor
- [ ] Empty state shows add prompt

---

## 15.2 Add Child Exam

### Options
- **Create New**: Fresh child exam
- **Link Existing**: Connect existing exam
- **Duplicate**: Copy another exam as child

### Create New Fields
- Title (required)
- Copy settings from parent (toggle)
- Inherit deadlines (toggle)

### Acceptance Criteria:
- [ ] Modal for add options
- [ ] Create sets parentId correctly
- [ ] Link validates no circular ref
- [ ] Duplicate copies all content
- [ ] New child opens in editor

---

## 15.3 Link Existing Exam

### Selection Interface
- Search existing exams
- Filter by status
- Preview exam before linking
- Only show orphan exams (no parent)

### Link Validation
- Cannot link to self
- Cannot create circular reference
- Cannot link already-parented exam (must unlink first)

### Acceptance Criteria:
- [ ] Search filters in real-time
- [ ] Validation errors shown clearly
- [ ] Link creates relationship
- [ ] Linked exam appears in list
- [ ] Parent field updated on child

---

## 15.4 Unlink Child

### Unlink Behavior
- Removes parent reference
- Child becomes root exam
- Preserves all child data
- Does not delete child

### Confirmation
- Dialog explains what unlinking does
- Option to also delete child
- Shows impact on participants

### Acceptance Criteria:
- [ ] Unlink requires confirmation
- [ ] Child preserved after unlink
- [ ] Participants unaffected
- [ ] Audit log records unlink
- [ ] Child removed from list

---

## 15.5 Reorder Children

### Reorder Methods
- Drag-drop in list
- Move up/down buttons
- Set specific position input

### Sort Order Behavior
- Integer sort order field
- Gaps allowed for future inserts
- Reorder affects display only

### Acceptance Criteria:
- [ ] Drag shows drop preview
- [ ] Order saves automatically
- [ ] Keyboard reorder accessible
- [ ] Order persists on reload

---

## 15.6 Deadline Inheritance

### Inheritance Mode Toggle
- **Independent**: Child has own deadlines
- **Inherited**: Relative to parent deadline

### Inherited Configuration
- Offset days from parent soft deadline
- Offset days from parent hard deadline
- Override per participant option

### Acceptance Criteria:
- [ ] Toggle clearly indicates mode
- [ ] Inherited shows calculated dates
- [ ] Parent change cascades to children
- [ ] Override available per participant
- [ ] Visual indicator for inherited

---

## 15.7 Bulk Child Actions

### Bulk Operations
- Delete selected children
- Change status of selected
- Move to different parent
- Export branch

### Selection
- Checkbox per child
- Select all option
- Selection count shown

### Acceptance Criteria:
- [ ] Bulk actions in toolbar
- [ ] Confirmation for destructive actions
- [ ] Progress for large operations
- [ ] Clear selection after action
