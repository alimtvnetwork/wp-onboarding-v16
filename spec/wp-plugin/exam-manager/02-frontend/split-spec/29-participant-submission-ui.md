# 29 - Participant Submission UI

## Overview

Frontend specification for how participants submit responses to checklist items during exams. Covers all 9 submission types, real-time validation feedback, file uploads, submission status display, and the review notification system.

---

## Dependencies

- `06-dashboard-page.md` (parent container)
- `07-section-view.md` (section context)
- `19-exam-checklists-tab.md` (submission type definitions)
- `06-enums-constants.md` (SubmissionType, SubmissionValidationMode, SubmissionReviewStatus)
- `SHARED-CONSTANTS.md` (file limits, validation rules)

---

## 29.1 Submission Type Components

Each submission type has a dedicated component with consistent patterns:

### Component Architecture

```
/components/submissions/
├── SubmissionWrapper.tsx         # Common wrapper with status badge
├── CheckboxSubmission.tsx        # Simple toggle
├── TextShortSubmission.tsx       # Single line input
├── TextLongSubmission.tsx        # Multi-line textarea
├── UrlSubmission.tsx             # URL with preview
├── VideoLinkSubmission.tsx       # Video embed preview
├── FileUploadSubmission.tsx      # Drag-drop file upload
├── SelectSubmission.tsx          # Dropdown select
├── RadioSubmission.tsx           # Radio button group
├── MultiselectSubmission.tsx     # Checkbox group
└── SubmissionStatusBadge.tsx     # Review status indicator
```

---

## 29.2 Checkbox Submission

**Use Case**: Simple completion toggle (e.g., "I have read the instructions")

### UI Layout

```
┌─────────────────────────────────────────────────────┐
│ ☐ I have read and understood the exam guidelines    │
│                                                     │
│   Optional: Description text in muted color         │
└─────────────────────────────────────────────────────┘
```

### Behavior

- Single click toggles state
- Immediate save (debounced 300ms)
- Subtle animation on toggle
- No validation required

### States

| State | Visual |
|-------|--------|
| Uncompleted | Empty checkbox, normal text |
| Completed | Checked checkbox, success border |
| Saving | Spinner inside checkbox |
| Error | Red border, retry button |

---

## 29.3 Text Short Submission

**Use Case**: Single-line text input (max 255 chars)

### UI Layout

```
┌─────────────────────────────────────────────────────┐
│ Your GitHub repository URL                          │
│ ┌─────────────────────────────────────────────────┐ │
│ │ https://github.com/username/repo              ↵ │ │
│ └─────────────────────────────────────────────────┘ │
│ 45/255 characters                    [Submit]       │
│                                                     │
│ ⚠️ Must contain "github.com" (validation hint)      │
└─────────────────────────────────────────────────────┘
```

### Props from `validationConfig`

| Prop | Type | Description |
|------|------|-------------|
| `maxLength` | number | Maximum characters (default 255) |
| `regexPattern` | string | Optional regex for validation |
| `requiredWords` | string[] | Words that must appear |
| `requiredWordsMode` | 'ANY' \| 'ALL' | Match any or all words |
| `placeholder` | string | Input placeholder text |

### Behavior

- Character counter updates in real-time
- Validation runs on blur and submit
- Shows validation hint if `regexPattern` or `requiredWords` set
- Submit button disabled until valid (if `ALLOW_RESUBMIT` mode)
- Auto-submit on Enter key

### Validation Feedback

```tsx
// Validation states
type ValidationState = 'idle' | 'validating' | 'valid' | 'invalid';

// Visual feedback
idle: gray border, no icon
validating: blue border, spinner
valid: green border, checkmark
invalid: red border, X icon, error message
```

---

## 29.4 Text Long Submission

**Use Case**: Multi-paragraph text (max 10,000 chars)

### UI Layout

```
┌─────────────────────────────────────────────────────┐
│ Describe your project implementation                │
│ ┌─────────────────────────────────────────────────┐ │
│ │                                                 │ │
│ │ I implemented the project using React and      │ │
│ │ TypeScript. The main challenges were...        │ │
│ │                                                 │ │
│ │                                                 │ │
│ └─────────────────────────────────────────────────┘ │
│ Min: 50 | Current: 234 | Max: 10,000               │
│                                                     │
│ ✓ Contains: "implemented", "challenges"  [Submit]   │
└─────────────────────────────────────────────────────┘
```

### Props from `validationConfig`

| Prop | Type | Description |
|------|------|-------------|
| `minLength` | number | Minimum characters (default 50) |
| `maxLength` | number | Maximum characters (default 10,000) |
| `requiredWords` | string[] | Words that must appear |
| `requiredWordsMode` | 'ANY' \| 'ALL' | Match logic |

### Behavior

- Auto-resize textarea to content
- Progress bar for min length requirement
- Word highlights for required words found
- Auto-save draft every 30 seconds
- Confirm before leaving with unsaved changes

### Word Detection Display

```
Required words: [implemented ✓] [challenges ✓] [solution ✗]
```

---

## 29.5 URL Submission

**Use Case**: Any URL with optional domain validation

### UI Layout

```
┌─────────────────────────────────────────────────────┐
│ Submit your portfolio website                       │
│ ┌─────────────────────────────────────────────────┐ │
│ │ 🔗 https://myportfolio.com                      │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ ┌─────────────────────────────────────────────────┐ │
│ │ ╔═══════════════════════════════════════════╗   │ │
│ │ ║  Link Preview                             ║   │ │
│ │ ║  My Portfolio - Web Developer             ║   │ │
│ │ ║  A showcase of my projects...             ║   │ │
│ │ ╚═══════════════════════════════════════════╝   │ │
│ └─────────────────────────────────────────────────┘ │
│                                        [Submit]     │
└─────────────────────────────────────────────────────┘
```

### Props from `validationConfig`

| Prop | Type | Description |
|------|------|-------------|
| `regexPattern` | string | Custom URL pattern |
| `allowedDomains` | string[] | Whitelist of domains |
| `requireHttps` | boolean | Require HTTPS (default true) |

### Behavior

- URL validation on blur
- Link preview fetched via API (debounced 1s after typing stops)
- Open in new tab button for verification
- Domain validation with clear error message
- Auto-prepend https:// if missing

### Link Preview Component

```tsx
interface LinkPreview {
  title: string;
  description: string;
  image?: string;
  favicon?: string;
  domain: string;
}
```

---

## 29.6 Video Link Submission

**Use Case**: YouTube, Vimeo, Loom, or custom video URLs

### UI Layout

```
┌─────────────────────────────────────────────────────┐
│ Submit your presentation video                      │
│ ┌─────────────────────────────────────────────────┐ │
│ │ 🎬 https://youtube.com/watch?v=abc123           │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ Supported: YouTube, Vimeo, Loom                     │
│                                                     │
│ ┌─────────────────────────────────────────────────┐ │
│ │ ┌───────────────────────────────────────────┐   │ │
│ │ │                                           │   │ │
│ │ │          ▶  Embedded Video Player         │   │ │
│ │ │                                           │   │ │
│ │ └───────────────────────────────────────────┘   │ │
│ │ "My Project Presentation" - 5:32                │ │
│ └─────────────────────────────────────────────────┘ │
│                                        [Submit]     │
└─────────────────────────────────────────────────────┘
```

### Props from `validationConfig`

| Prop | Type | Description |
|------|------|-------------|
| `allowedDomains` | string[] | Supported platforms |
| `minDuration` | number | Minimum video length (seconds) |
| `maxDuration` | number | Maximum video length (seconds) |

### Supported Platforms

| Platform | URL Patterns | Embed Method |
|----------|--------------|--------------|
| YouTube | youtube.com/watch, youtu.be | iframe oEmbed |
| Vimeo | vimeo.com/### | iframe oEmbed |
| Loom | loom.com/share | iframe |
| Custom | Regex pattern | Link only |

### Behavior

- Platform auto-detection from URL
- Video embed preview with play button
- Duration display (if available via API)
- Thumbnail extraction for preview
- Error state if video not accessible

---

## 29.7 File Upload Submission

**Use Case**: Document, image, or other file attachments

### UI Layout

```
┌─────────────────────────────────────────────────────┐
│ Upload your project documentation                   │
│                                                     │
│ ┌ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ┐ │
│ │                                                 │ │
│ │     📄 Drag and drop files here                 │ │
│ │        or click to browse                       │ │
│ │                                                 │ │
│ │     Accepted: PDF, DOC, DOCX (max 5MB)          │ │
│ │     Maximum 3 files                             │ │
│ │                                                 │ │
│ └ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ┘ │
│                                                     │
│ Uploaded files:                                     │
│ ┌─────────────────────────────────────────────────┐ │
│ │ 📄 project-report.pdf  (2.3 MB)        [✓] [🗑] │ │
│ │ 📄 appendix.docx       (1.1 MB)        [✓] [🗑] │ │
│ └─────────────────────────────────────────────────┘ │
│                                        [Submit]     │
└─────────────────────────────────────────────────────┘
```

### Props from `validationConfig`

| Prop | Type | Description |
|------|------|-------------|
| `allowedExtensions` | string[] | Allowed file types |
| `maxFileSize` | number | Max size per file (bytes) |
| `maxFiles` | number | Maximum number of files |
| `requireAll` | boolean | All slots must be filled |

### File States

| State | Visual |
|-------|--------|
| Idle | Dashed border, upload icon |
| Drag Over | Solid primary border, highlight |
| Uploading | Progress bar, percentage |
| Uploaded | Green check, file info |
| Error | Red border, error message |

### Upload Progress

```tsx
interface UploadProgress {
  fileId: string;
  fileName: string;
  progress: number;    // 0-100
  status: 'pending' | 'uploading' | 'complete' | 'error';
  error?: string;
}
```

### Behavior

- Drag-and-drop zone with visual feedback
- Click to open file browser
- Multiple file selection support
- Individual file progress bars
- Delete uploaded files before submit
- File type validation before upload
- Size validation with clear error

### Error Messages

| Error | Message |
|-------|---------|
| Wrong type | "Only PDF, DOC, DOCX files are allowed" |
| Too large | "File exceeds maximum size of 5MB" |
| Too many | "Maximum 3 files allowed" |
| Upload failed | "Upload failed. Click to retry" |

---

## 29.8 Select Submission

**Use Case**: Dropdown single selection

### UI Layout

```
┌─────────────────────────────────────────────────────┐
│ Which framework did you use?                        │
│ ┌─────────────────────────────────────────────────┐ │
│ │ React                                         ▼ │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ (Dropdown expanded)                                 │
│ ┌─────────────────────────────────────────────────┐ │
│ │ React                                        ✓  │ │
│ │ Vue                                             │ │
│ │ Angular                                         │ │
│ │ Svelte                                          │ │
│ └─────────────────────────────────────────────────┘ │
│                                        [Submit]     │
└─────────────────────────────────────────────────────┘
```

### Props from `validationConfig`

| Prop | Type | Description |
|------|------|-------------|
| `options` | Option[] | Available choices |
| `shuffleOptions` | boolean | Randomize order |
| `placeholder` | string | Default placeholder |

### Option Interface

```tsx
interface Option {
  value: string;
  label: string;
  isCorrect?: boolean;  // Hidden from participant
}
```

### Behavior

- Options shuffled on mount if `shuffleOptions` true
- Shuffle seed based on participantId (consistent per user)
- Search/filter for long option lists (>10 options)
- Keyboard navigation support
- Clear selection option

---

## 29.9 Radio Submission

**Use Case**: Radio button single selection

### UI Layout

```
┌─────────────────────────────────────────────────────┐
│ What best describes your experience level?         │
│                                                     │
│ ○ Beginner (0-1 years)                              │
│ ◉ Intermediate (1-3 years)  ← Selected             │
│ ○ Advanced (3-5 years)                              │
│ ○ Expert (5+ years)                                 │
│                                                     │
│                                        [Submit]     │
└─────────────────────────────────────────────────────┘
```

### Props from `validationConfig`

| Prop | Type | Description |
|------|------|-------------|
| `options` | Option[] | Available choices |
| `shuffleOptions` | boolean | Randomize order |
| `layout` | 'vertical' \| 'horizontal' | Display direction |

### Behavior

- Immediate visual feedback on selection
- Only one option selectable
- Keyboard arrow key navigation
- Focus ring on current option
- Submit enabled after selection

---

## 29.10 Multiselect Submission

**Use Case**: Checkbox multiple selection

### UI Layout

```
┌─────────────────────────────────────────────────────┐
│ Select all technologies you used (select 2-4):     │
│                                                     │
│ ☑ React                                             │
│ ☑ TypeScript                                        │
│ ☐ Node.js                                           │
│ ☑ PostgreSQL                                        │
│ ☐ Docker                                            │
│ ☐ AWS                                               │
│                                                     │
│ Selected: 3 of 6  (min: 2, max: 4)    [Submit]     │
└─────────────────────────────────────────────────────┘
```

### Props from `validationConfig`

| Prop | Type | Description |
|------|------|-------------|
| `options` | Option[] | Available choices |
| `shuffleOptions` | boolean | Randomize order |
| `minSelections` | number | Minimum required |
| `maxSelections` | number | Maximum allowed |

### Behavior

- Multiple selections allowed
- Visual counter for selected items
- Disable additional selections at max
- Warning if below minimum on submit
- "Select All" / "Clear All" for long lists

---

## 29.11 Submission Status Badge

Shows the current status of a submission, especially for `FLAG_FOR_REVIEW` mode.

### Status States

| Status | Badge | Color | Description |
|--------|-------|-------|-------------|
| Not Started | — | Gray | No submission yet |
| Draft | 💾 | Yellow | Auto-saved but not submitted |
| Submitted | ✓ | Blue | Awaiting review |
| Under Review | 👁 | Purple | Admin is reviewing |
| Approved | ✓✓ | Green | Accepted by admin |
| Rejected | ✗ | Red | Needs resubmission |
| Needs Resubmit | ⟳ | Orange | Correction requested |

### Badge Component

```tsx
interface SubmissionStatusBadgeProps {
  status: SubmissionReviewStatus | null;
  submittedAt?: Date;
  reviewedAt?: Date;
  reviewNote?: string;
}

// Display logic
<Badge variant={getVariant(status)}>
  {getIcon(status)} {getLabel(status)}
</Badge>

// With tooltip for review note
{reviewNote && (
  <Tooltip content={reviewNote}>
    <InfoIcon />
  </Tooltip>
)}
```

---

## 29.12 Submission Wrapper Component

Common wrapper providing consistent layout for all submission types:

```tsx
interface SubmissionWrapperProps {
  checklistItem: ChecklistItem;
  submission?: ParticipantSubmission;
  onSubmit: (value: SubmissionValue) => Promise<void>;
  onSaveDraft?: (value: SubmissionValue) => void;
  children: React.ReactNode;
}

// Layout
<div className="submission-wrapper">
  <div className="submission-header">
    <h4>{item.title}</h4>
    <SubmissionStatusBadge status={submission?.reviewStatus} />
    {item.isRequired && <RequiredBadge />}
  </div>
  
  {item.description && (
    <MarkdownRenderer content={item.description} />
  )}
  
  <div className="submission-content">
    {children}
  </div>
  
  <div className="submission-footer">
    {item.timeLimit && <TimeRemaining seconds={remaining} />}
    <SubmitButton 
      loading={submitting}
      disabled={!isValid}
      onClick={handleSubmit}
    />
  </div>
  
  {submission?.reviewNote && (
    <ReviewFeedback note={submission.reviewNote} />
  )}
</div>
```

---

## 29.13 Review Feedback Display

When a submission is flagged or requires resubmission:

### UI Layout

```
┌─────────────────────────────────────────────────────┐
│ ⚠️ Submission Needs Revision                        │
│                                                     │
│ Admin feedback:                                     │
│ ┌─────────────────────────────────────────────────┐ │
│ │ "Your GitHub link points to a private repo.    │ │
│ │  Please make it public or provide access."     │ │
│ │                                                 │ │
│ │ — Reviewed by Admin on Jan 25, 2026            │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ Previous submission: https://github.com/user/repo  │
│                                                     │
│ ┌─────────────────────────────────────────────────┐ │
│ │ New submission:                                 │ │
│ │ https://github.com/user/repo-public           │ │
│ └─────────────────────────────────────────────────┘ │
│                                    [Resubmit]       │
└─────────────────────────────────────────────────────┘
```

### Behavior

- Show previous submission value (read-only)
- Highlight review feedback prominently
- Allow new submission in same field
- Track submission history (optional)

---

## 29.14 Validation Flow

### Client-Side Validation

```tsx
// Runs on every change (debounced) and before submit
const validateSubmission = (
  value: SubmissionValue,
  config: ValidationConfig,
  type: SubmissionType
): ValidationResult => {
  const errors: string[] = [];
  
  // Type-specific validation
  switch (type) {
    case 'TEXT_SHORT':
    case 'TEXT_LONG':
      if (config.minLength && value.length < config.minLength) {
        errors.push(`Minimum ${config.minLength} characters required`);
      }
      if (config.maxLength && value.length > config.maxLength) {
        errors.push(`Maximum ${config.maxLength} characters allowed`);
      }
      if (config.regexPattern && !new RegExp(config.regexPattern).test(value)) {
        errors.push('Input does not match required format');
      }
      if (config.requiredWords) {
        const missing = checkRequiredWords(value, config);
        if (missing.length > 0) {
          errors.push(`Missing required words: ${missing.join(', ')}`);
        }
      }
      break;
      
    case 'URL':
    case 'VIDEO_LINK':
      if (!isValidUrl(value)) {
        errors.push('Please enter a valid URL');
      }
      if (config.allowedDomains && !matchesDomain(value, config.allowedDomains)) {
        errors.push(`URL must be from: ${config.allowedDomains.join(', ')}`);
      }
      break;
      
    case 'FILE_UPLOAD':
      // File validation happens during upload
      break;
      
    case 'SELECT':
    case 'RADIO':
      if (!value) {
        errors.push('Please select an option');
      }
      break;
      
    case 'MULTISELECT':
      const count = (value as string[]).length;
      if (config.minSelections && count < config.minSelections) {
        errors.push(`Select at least ${config.minSelections} options`);
      }
      if (config.maxSelections && count > config.maxSelections) {
        errors.push(`Select at most ${config.maxSelections} options`);
      }
      break;
  }
  
  return { isValid: errors.length === 0, errors };
};
```

### Server-Side Validation Response

```tsx
// API Response for FLAG_FOR_REVIEW mode
interface SubmissionResponse {
  success: boolean;
  submission: {
    id: string;
    status: 'SUBMITTED' | 'FLAGGED';
    validationPassed: boolean;
    flagReason?: string;   // Why it was flagged
    reviewStatus: 'PENDING' | null;
  };
  message?: string;
}

// Handle flagged submission
if (response.submission.status === 'FLAGGED') {
  toast({
    title: "Submission received",
    description: "Your submission has been flagged for review.",
    variant: "warning"
  });
}
```

---

## 29.15 API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/participants/{id}/submissions` | Get all submissions for participant |
| GET | `/api/participants/{id}/submissions/{checklistId}` | Get specific submission |
| POST | `/api/participants/{id}/submissions/{checklistId}` | Submit response |
| PUT | `/api/participants/{id}/submissions/{checklistId}` | Update/resubmit |
| POST | `/api/participants/{id}/submissions/{checklistId}/draft` | Save draft |
| POST | `/api/uploads/submission-file` | Upload file for submission |
| DELETE | `/api/uploads/{fileId}` | Delete uploaded file |

### Submit Request

```tsx
// POST /api/participants/{id}/submissions/{checklistId}
interface SubmitRequest {
  type: SubmissionType;
  value: string | string[] | null;  // Text, URL, or option values
  fileIds?: string[];                // For FILE_UPLOAD type
}
```

### Submit Response

```tsx
interface SubmitResponse {
  success: boolean;
  submission: {
    id: number;
    checklistId: number;
    isCompleted: boolean;
    submissionValue: string;
    submittedAt: string;
    reviewStatus: SubmissionReviewStatus | null;
  };
  validationResult?: {
    passed: boolean;
    errors?: string[];
    flagged?: boolean;
  };
}
```

---

## 29.16 State Management

### Submission State Hook

```tsx
interface UseSubmissionState {
  value: SubmissionValue;
  setValue: (value: SubmissionValue) => void;
  isDirty: boolean;
  isValid: boolean;
  errors: string[];
  status: SubmissionStatus;
  submit: () => Promise<void>;
  saveDraft: () => void;
  reset: () => void;
}

const useSubmission = (
  checklistItem: ChecklistItem,
  existingSubmission?: ParticipantSubmission
): UseSubmissionState => {
  // Implementation
};
```

### Local Storage Draft

```tsx
// Auto-save drafts to localStorage
const DRAFT_KEY = `eqm_draft_${examSlug}_${checklistId}`;

// Save draft every 30 seconds
useEffect(() => {
  const interval = setInterval(() => {
    if (isDirty && !isSubmitted) {
      localStorage.setItem(DRAFT_KEY, JSON.stringify({ value, timestamp: Date.now() }));
    }
  }, 30000);
  
  return () => clearInterval(interval);
}, [value, isDirty]);

// Restore draft on mount
useEffect(() => {
  const draft = localStorage.getItem(DRAFT_KEY);
  if (draft && !existingSubmission) {
    const { value, timestamp } = JSON.parse(draft);
    // Check if draft is less than 24 hours old
    if (Date.now() - timestamp < 86400000) {
      setValue(value);
      toast({ title: "Draft restored", description: "Your previous work has been restored." });
    }
  }
}, []);
```

---

## 29.17 Accessibility

### Keyboard Navigation

| Key | Action |
|-----|--------|
| Tab | Move between form fields |
| Enter | Submit (for text inputs) |
| Space | Toggle checkbox/radio |
| Arrow Up/Down | Navigate radio/select options |
| Escape | Close dropdowns |

### ARIA Labels

```tsx
// Required field
<label>
  {title}
  <span aria-label="required">*</span>
</label>

// Error state
<input
  aria-invalid={hasError}
  aria-describedby={`${id}-error`}
/>
<span id={`${id}-error`} role="alert">
  {errorMessage}
</span>

// File upload
<div
  role="button"
  aria-label="Upload file, drag and drop or click to browse"
  tabIndex={0}
/>
```

### Screen Reader Announcements

- Announce validation errors
- Announce successful submission
- Announce file upload progress
- Announce review status changes

---

## 29.18 Responsive Design

### Mobile Layout

| Breakpoint | Adaptation |
|------------|------------|
| < 640px | Full width inputs, stacked buttons |
| 640-1024px | Side-by-side where possible |
| > 1024px | Optimal reading width (max 720px) |

### Touch Optimization

- Larger touch targets (min 44px)
- Swipe to delete uploaded files
- Touch-friendly file picker
- Haptic feedback on submit (if supported)

---

## 29.19 Error States

### Network Errors

```
┌─────────────────────────────────────────────────────┐
│ ⚠️ Connection lost                                  │
│                                                     │
│ Your submission couldn't be saved.                  │
│ We'll retry automatically when you're back online. │
│                                                     │
│ [Retry Now]                   [Save Offline]        │
└─────────────────────────────────────────────────────┘
```

### Validation Errors

```
┌─────────────────────────────────────────────────────┐
│ ┌─────────────────────────────────────────────────┐ │
│ │ https://example.com                             │ │
│ └─────────────────────────────────────────────────┘ │
│ ❌ URL must be from: github.com, gitlab.com         │
└─────────────────────────────────────────────────────┘
```

### Server Errors

```
┌─────────────────────────────────────────────────────┐
│ ❌ Submission failed                                │
│                                                     │
│ Something went wrong on our end.                    │
│ Error code: ERR_SUB_5001                            │
│                                                     │
│ [Try Again]              [Contact Support]          │
└─────────────────────────────────────────────────────┘
```

---

## 29.20 Acceptance Criteria

### Submission Types
- [ ] All 9 submission types render correctly
- [ ] Type-specific validation works
- [ ] Options shuffle consistently per user
- [ ] File upload with drag-drop works

### Validation
- [ ] Client-side validation before submit
- [ ] Server-side validation response handled
- [ ] FLAG_FOR_REVIEW shows pending status
- [ ] ALLOW_RESUBMIT blocks with errors

### Status Display
- [ ] Status badge reflects current state
- [ ] Review feedback displayed prominently
- [ ] Resubmission flow works smoothly
- [ ] Submission history accessible

### Drafts
- [ ] Auto-save to localStorage
- [ ] Draft restoration on page load
- [ ] Clear draft after successful submit
- [ ] Warning before leaving with unsaved changes

### Accessibility
- [ ] Full keyboard navigation
- [ ] Screen reader announcements
- [ ] ARIA labels on all interactive elements
- [ ] Focus management in modals

### Performance
- [ ] Debounced validation (300ms)
- [ ] Optimistic UI updates
- [ ] File upload progress indicator
- [ ] Lazy load video embeds

---

## Notes

- File uploads go to blob storage, only URL stored in database
- Draft auto-save uses localStorage with 24-hour expiry
- Video embeds are lazy-loaded to improve performance
- Option shuffling uses participantId as seed for consistency
- Review notes support Markdown formatting
