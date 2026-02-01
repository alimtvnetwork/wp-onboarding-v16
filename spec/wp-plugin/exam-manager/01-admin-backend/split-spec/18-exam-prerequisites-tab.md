# 16 - Exam Prerequisites Tab

## Overview

React component for managing exam prerequisites within the Exam Editor. Prerequisites define which exams, checklists, or conditions must be completed before a participant can access the current exam.

---

## Dependencies

- `12-exam-editor-ui.md` (parent tab container)
- `10-exam-service.md` (exam data access)
- `06-entity-models.md` (Prerequisite entity)

---

## Functional Requirements

### 16.1 Prerequisite Types

Prerequisite types are defined in `Spec/04-enums-constants.md` (PrerequisiteType enum).

**Content-Based Types** (display items for participants to consume):
- `VIDEO` - Video content to watch
- `LINK` - External link to visit
- `DOCUMENT` - Document to read

**Access Control Types** (logic conditions for exam access):
- `EXAM_COMPLETION` - Another exam must be 100% complete
- `CHECKLIST_ITEM` - Specific checklist item must be done
- `ROLE_ASSIGNMENT` - User must have specific role
- `DATE_RANGE` - Current date must be within range
- `MANUAL_APPROVAL` - Admin must manually approve access

### 16.2 Prerequisite Configuration

| Field | Type | Description |
|-------|------|-------------|
| `prerequisiteType` | Enum | Type from above list |
| `targetId` | Integer/null | Referenced exam/checklist ID |
| `targetSlug` | String/null | Referenced exam slug (display) |
| `isRequired` | Boolean | Hard requirement vs recommendation |
| `errorMessage` | String | Custom message when unmet |
| `sortOrder` | Integer | Display/evaluation order |

### 16.3 UI Components

**Prerequisite List**
- Sortable list of current prerequisites
- Type icon + description for each
- Required/Optional badge indicator
- Quick remove button
- Drag handle for reordering

**Add Prerequisite Modal**
- Type selector dropdown
- Dynamic fields based on type:
  - EXAM_COMPLETION: Exam search/select
  - CHECKLIST_ITEM: Exam select → Checklist select
  - ROLE_ASSIGNMENT: Role dropdown
  - DATE_RANGE: Date range picker
  - MANUAL_APPROVAL: Justification text field
- Required toggle
- Custom error message input

**Prerequisite Validation Preview**
- "Test as User" dropdown (select participant)
- Shows pass/fail status for each prerequisite
- Highlights blocking items

---

## Business Rules

### 16.4 Circular Dependency Prevention

- [ ] Cannot add current exam as its own prerequisite
- [ ] Cannot create circular chains (A→B→C→A)
- [ ] Validate chain depth ≤ 10 levels
- [ ] Show warning for deep chains (>5 levels)

### 16.5 Prerequisite Inheritance

- [ ] Child exams can inherit parent prerequisites
- [ ] Override option: "Add to parent" vs "Replace parent"
- [ ] Visual indicator for inherited vs local prerequisites

### 16.6 Soft vs Hard Prerequisites

- [ ] Required (`isRequired: true`): Blocks access completely
- [ ] Recommended (`isRequired: false`): Shows warning, allows bypass
- [ ] Admin can always bypass all prerequisites

---

## Acceptance Criteria

### Prerequisite Management
- [ ] Add prerequisites of all 5 types
- [ ] Remove prerequisites with confirmation
- [ ] Reorder via drag-and-drop
- [ ] Edit existing prerequisite configuration
- [ ] Bulk delete selected prerequisites

### Validation
- [ ] Circular dependency detection works
- [ ] Chain depth validation enforced
- [ ] Real-time validation on add/edit
- [ ] Clear error messages for invalid configurations

### Preview & Testing
- [ ] Test prerequisite chain as specific user
- [ ] Visual pass/fail indicators accurate
- [ ] Inherited prerequisites displayed correctly
- [ ] Override behavior works as expected

### Performance
- [ ] Exam search is debounced (300ms)
- [ ] Large prerequisite lists render efficiently
- [ ] Chain validation completes < 500ms

---

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Circular dependency detected | Block save, highlight cycle |
| Referenced exam deleted | Show "Missing Exam" warning |
| Chain depth exceeded | Block save, show depth count |
| Invalid date range | Inline validation error |

---

## Notes

- Prerequisites are evaluated in `sortOrder` sequence
- First failing required prerequisite blocks access
- All prerequisites logged for audit trail
