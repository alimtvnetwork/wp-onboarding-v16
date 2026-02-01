# 11. Exam Hierarchy

## Overview
Parent-child relationship system for organizing exams into hierarchical structures.

---

## 11.1 Hierarchy Model

### Relationship Structure
- One exam can have one parent
- One exam can have unlimited children
- Unlimited nesting depth allowed
- Root exams have NULL parentId

### Database Fields
- `parentId` - Foreign key to parent exam
- `sortOrder` - Integer for sibling ordering
- `inheritDeadlines` - Boolean flag

### Acceptance Criteria:
- [ ] Self-referential relationship works
- [ ] Circular references prevented
- [ ] Orphan detection available
- [ ] Tree traversal methods work

---

## 11.2 HierarchyService Class

### Methods Required
- `getChildren(examId)` - Direct children only
- `getDescendants(examId)` - All nested children
- `getAncestors(examId)` - Path to root
- `getRootExams()` - All top-level exams
- `moveExam(examId, newParentId)` - Reparent
- `reorder(examId, newPosition)` - Change sort order

### Acceptance Criteria:
- [ ] All methods return exam entities
- [ ] Efficient queries (avoid N+1)
- [ ] Caching for frequently accessed trees
- [ ] Circular reference check on move

---

## 11.3 Create Child Exam

### Process
1. Validate parent exam exists
2. Create new exam with parentId set
3. Set sortOrder to end of siblings
4. Optionally inherit parent settings

### Inheritable Settings
- Deadline defaults
- Prerequisite requirements
- Visibility settings
- Email notification preferences

### Acceptance Criteria:
- [ ] Child linked to parent correctly
- [ ] Sort order calculated automatically
- [ ] Inheritance flag respected
- [ ] Child appears in parent's children list

---

## 11.4 Move Exam in Hierarchy

### Move Operations
- To different parent
- To root (remove parent)
- Reorder within siblings

### Validation Rules
- Cannot move to own descendant
- Cannot create depth > 10 levels
- Must have permission on both locations

### Acceptance Criteria:
- [ ] Circular reference prevented
- [ ] Depth limit enforced
- [ ] All descendants move with parent
- [ ] Sort orders recalculated
- [ ] Audit log records move

---

## 11.5 Independent vs Inherited Deadlines

### Independent Mode
- Child has own soft/hard deadlines
- No relationship to parent deadlines
- Participant progress tracked separately

### Inherited Mode
- Child deadline relative to parent
- Format: "Parent deadline + X days"
- Cascading updates when parent changes

### Acceptance Criteria:
- [ ] Mode selectable per child exam
- [ ] Inherited deadlines calculate correctly
- [ ] Parent deadline change cascades
- [ ] Visual indicator for inheritance
- [ ] Override available per participant

---

## 11.6 Tree Display Component

### Visual Requirements
- Collapsible/expandable nodes
- Drag-and-drop reordering
- Indent levels for depth
- Icons for exam status

### Interaction
- Click to select/edit
- Drag to reorder or reparent
- Right-click context menu
- Keyboard navigation

### Acceptance Criteria:
- [ ] Tree renders efficiently (virtualized)
- [ ] Drag preview shows destination
- [ ] Invalid drop targets indicated
- [ ] Collapse state persisted
- [ ] Accessible keyboard controls

---

## 11.7 Bulk Hierarchy Operations

### Bulk Actions
- Move multiple exams to parent
- Delete branch (with confirmation)
- Duplicate branch structure
- Export branch as template

### Acceptance Criteria:
- [ ] Multi-select available in tree
- [ ] Confirmation for destructive actions
- [ ] Progress indicator for large operations
- [ ] Rollback on partial failure
