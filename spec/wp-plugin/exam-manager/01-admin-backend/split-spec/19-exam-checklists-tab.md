# 17 - Exam Checklists Tab

## Overview

React component for managing exam checklists within the Exam Editor. Checklists are categorized into three phases: PRE (before exam), IN_EXAM (during exam), and POST (after completion). Each checklist item can require different types of submissions with validation rules.

---

## Dependencies

- `12-exam-editor-ui.md` (parent tab container)
- `10-exam-service.md` (exam data access)
- `06-entity-models.md` (Checklist entity)
- `04-enums-constants.md` (ChecklistPhase, SubmissionType enums)

---

## Functional Requirements

### 17.1 Checklist Phases

Checklist phases are defined in `Spec/04-enums-constants.md` (ChecklistPhase enum):

| Phase | Description |
|-------|-------------|
| `PRE` | Must complete before starting exam |
| `IN_EXAM` | Tasks during exam (can be timed) |
| `POST` | Actions after exam completion |

### 17.2 Checklist Item Schema

| Field | Type | Description |
|-------|------|-------------|
| `id` | Integer | Auto-increment PK |
| `examId` | Integer | FK to parent exam |
| `phase` | Enum | PRE, IN_EXAM, POST |
| `title` | String(255) | Short item description |
| `description` | Text/null | Extended instructions (Markdown) |
| `isRequired` | Boolean | Blocks progression if incomplete |
| `submissionType` | Enum | Type of user input required |
| `validationMode` | Enum | How to handle validation failures |
| `validationConfig` | JSON/null | Validation rules configuration |
| `requiresEvidence` | Boolean | Must upload file/screenshot |
| `evidenceType` | Enum/null | FILE, IMAGE, URL, TEXT |
| `timeLimit` | Integer/null | Minutes allowed (IN_EXAM only) |
| `sortOrder` | Integer | Display order within phase |
| `createdAt` | DateTime | Creation timestamp |
| `updatedAt` | DateTime | Last modification |

### 17.3 Submission Types

Users can submit different types of responses for checklist items:

| Type | Description | Configuration |
|------|-------------|---------------|
| `CHECKBOX` | Simple completion toggle | No additional config |
| `TEXT_SHORT` | Single line text (max 255 chars) | Optional regex pattern, required words |
| `TEXT_LONG` | Multi-paragraph text (max 10,000 chars) | Optional min/max length, required words |
| `URL` | Any URL with validation | Optional regex pattern for specific domains |
| `VIDEO_LINK` | YouTube, Vimeo, or custom video URL | Platform whitelist (youtube.com, vimeo.com, etc.) |
| `FILE_UPLOAD` | Document/file attachment | Allowed extensions, max size |
| `SELECT` | Dropdown single selection | Options array with correct answer(s) |
| `RADIO` | Radio button single selection | Options array with correct answer |
| `MULTISELECT` | Checkbox multiple selection | Options array with correct answers |

### 17.4 Validation Configuration

Each submission type can have validation rules stored in `validationConfig` JSON:

```json
// TEXT_SHORT / TEXT_LONG
{
  "minLength": 50,
  "maxLength": 1000,
  "regexPattern": "^https://github\\.com/.*$",
  "requiredWords": ["completed", "submitted"],
  "requiredWordsMode": "ANY" // ANY or ALL
}

// URL / VIDEO_LINK
{
  "regexPattern": "^https://(www\\.)?(youtube\\.com|vimeo\\.com)/.*$",
  "allowedDomains": ["youtube.com", "vimeo.com", "loom.com"]
}

// FILE_UPLOAD
{
  "allowedExtensions": ["pdf", "doc", "docx"],
  "maxFileSize": 5242880,
  "maxFiles": 3
}

// SELECT / RADIO / MULTISELECT
{
  "options": [
    {"value": "option1", "label": "Option 1", "isCorrect": false},
    {"value": "option2", "label": "Option 2", "isCorrect": true},
    {"value": "option3", "label": "Option 3", "isCorrect": false}
  ],
  "shuffleOptions": true
}
```

### 17.5 Validation Mode

Defines behavior when submission fails validation (see SubmissionValidationMode enum):

| Mode | Description |
|------|-------------|
| `FLAG_FOR_REVIEW` | Accept submission but flag for admin review |
| `ALLOW_RESUBMIT` | Show error and allow user to correct |
| `AUTO_ACCEPT` | No validation, accept any input |

**Note**: `FLAG_FOR_REVIEW` is the default - participants can proceed but flagged items appear in admin review queue.

### 17.6 UI Layout

**Phase Tabs/Sections**
- Three collapsible sections or horizontal tabs
- Item count badge per phase
- Progress indicator (X of Y required)
- Flagged items indicator (review queue count)

**Checklist Item Card**
- Drag handle for reordering
- Title (inline editable)
- Submission type selector dropdown
- Required/Optional toggle
- Validation config editor (type-specific)
- Evidence requirement toggle
- Time limit input (IN_EXAM only)
- Expand for description editor
- Delete button with confirmation

**Submission Type Config UI**
- **Text fields**: Min/max length sliders, regex input, required words chips
- **URL fields**: Domain whitelist, regex pattern
- **File upload**: Extension checkboxes, size slider
- **Select/Radio/Checkbox**: Options table with add/remove, correct answer toggle

**Add Item Form**
- Phase selector (defaults to current section)
- Title input (required)
- Submission type dropdown
- Description textarea (Markdown)
- Configuration toggles
- "Add Another" option for batch creation

---

## Business Rules

### 17.7 Phase-Specific Rules

**PRE Phase**
- [ ] All required items must be complete to start exam
- [ ] Evidence uploads stored before exam begins
- [ ] Admin can mark items complete on behalf of user
- [ ] Flagged items visible in pre-exam review

**IN_EXAM Phase**
- [ ] Timer starts when exam starts (if `timeLimit` set)
- [ ] Incomplete required items prevent submission
- [ ] Progress auto-saved every 30 seconds
- [ ] Flagged submissions don't block progress

**POST Phase**
- [ ] Triggered after exam marked complete
- [ ] Certificate generation waits for POST completion
- [ ] Reminder emails for incomplete POST items

### 17.8 Evidence Requirements

- [ ] FILE: Any file type, max 10MB
- [ ] IMAGE: JPG, PNG, GIF, WebP, max 5MB
- [ ] URL: Valid URL with optional preview
- [ ] TEXT: Textarea response, min 50 characters

### 17.9 Checklist Templates

- [ ] Save current checklist as reusable template
- [ ] Apply template to new exams
- [ ] Template library accessible from UI
- [ ] Import/export templates as JSON

### 17.10 Submission Review Queue

- [ ] Admin dashboard shows flagged submissions
- [ ] Filter by exam, participant, submission type
- [ ] Bulk approve/reject actions
- [ ] Add review notes per submission

---

## Acceptance Criteria

### Item Management
- [ ] Create items in all three phases
- [ ] Inline edit title without modal
- [ ] Full edit via expandable panel
- [ ] Drag-and-drop reorder within phase
- [ ] Move items between phases
- [ ] Bulk delete with multi-select

### Submission Type Configuration
- [ ] Configure all 9 submission types
- [ ] Type-specific validation UI
- [ ] Preview submission form as participant
- [ ] Test validation rules before save

### Configuration
- [ ] Toggle required/optional status
- [ ] Configure evidence requirements
- [ ] Set time limits for IN_EXAM items
- [ ] Markdown preview for descriptions
- [ ] Validation mode selector

### Templates
- [ ] Save checklist as template
- [ ] Apply template to exam
- [ ] Edit template in library
- [ ] Delete unused templates

### Validation
- [ ] Title required, max 255 chars
- [ ] Time limit must be positive integer
- [ ] At least one item per phase warning (optional)
- [ ] Evidence type required if evidence enabled
- [ ] Valid JSON for validation config

### Performance
- [ ] Smooth drag-and-drop with 50+ items
- [ ] Debounced auto-save (500ms)
- [ ] Optimistic UI updates

---

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Duplicate titles in phase | Warning, allow save |
| Delete required item | Confirmation modal |
| Template name conflict | Append number suffix |
| Evidence upload fails | Retry option, error message |
| Invalid regex pattern | Show error, prevent save |
| Invalid validation config | Schema validation error |

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/exams/{id}/checklists` | List all items by phase |
| POST | `/exams/{id}/checklists` | Create new item |
| PUT | `/checklists/{id}` | Update item |
| DELETE | `/checklists/{id}` | Remove item |
| POST | `/checklists/reorder` | Batch update sortOrder |
| GET | `/checklist-templates` | List templates |
| POST | `/checklist-templates` | Save as template |
| POST | `/checklists/{id}/test-validation` | Test validation config |
| GET | `/admin/flagged-submissions` | Review queue for flagged items |
| PUT | `/admin/submissions/{id}/review` | Approve/reject flagged submission |

---

## Notes

- Checklist completion status stored per-participant
- Completion timestamps used for analytics
- Evidence files stored in `EQM_UPLOADS_DIR/evidence/{examId}/{participantId}/`
- Flagged submissions stored in `participantChecklist` with `reviewStatus` field
- Validation runs on both client and server side
