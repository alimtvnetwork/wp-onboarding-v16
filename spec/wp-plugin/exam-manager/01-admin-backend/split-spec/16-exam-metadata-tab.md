# 14. Exam Metadata Tab

## Overview
Second tab in exam editor for managing exam title, description, and configuration settings. Supports **preset linking** for consistent exam configurations with override indicators and reset-to-preset functionality.

---

## 14.1 Preset Selection

### Preset Dropdown

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Configuration Preset                                                    │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ 🎓 Self-Paced Training                                           ▼ │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│ Using preset: Flexible deadlines, extensions allowed, progress visible │
│                                                          [Unlink Preset]│
└─────────────────────────────────────────────────────────────────────────┘
```

### Dropdown Options

| Option | Description |
|--------|-------------|
| No Preset | All settings manually configured |
| Standard Exam | Default 7/14 day deadlines |
| Certification Strict | Invite-only, no extensions |
| Self-Paced Training | 30/60 day deadlines, flexible |
| Quick Assessment | 1/2 day deadlines, timed |
| Custom presets... | User-created presets |

### Preset Info Display

When a preset is selected, show:
- Preset name with category icon
- One-line summary of key settings
- "Unlink Preset" button
- Link to "Manage Presets" in settings

### Acceptance Criteria:
- [ ] Dropdown shows all available presets
- [ ] Preset selection updates all linked fields
- [ ] Category icons distinguish preset types
- [ ] Summary updates on selection
- [ ] Unlink removes preset reference

---

## 14.2 Override Indicators

### Visual Indicator System

Fields that can be overridden from preset show an indicator when the value differs:

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Soft Deadline (days)                          ⚡ Overridden from preset │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ 21                                                    [↺ Reset]     │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│ Preset value: 30 days                                                   │
└─────────────────────────────────────────────────────────────────────────┘
```

### Override States

| State | Visual | Behavior |
|-------|--------|----------|
| Using Preset | No indicator | Value comes from linked preset |
| Overridden | ⚡ badge + muted preset value | Local value differs from preset |
| No Preset | No indicator | All values are local |

### Override Badge Component

```tsx
interface OverrideIndicatorProps {
  fieldName: string;
  currentValue: any;
  presetValue: any;
  onReset: () => void;
}

// Visual: Yellow badge with lightning icon
<div className="flex items-center gap-2">
  <Badge variant="warning" className="text-xs">
    ⚡ Overridden
  </Badge>
  <Button variant="ghost" size="sm" onClick={onReset}>
    ↺ Reset
  </Button>
</div>
<span className="text-xs text-muted-foreground">
  Preset value: {formatValue(presetValue)}
</span>
```

### Reset to Preset Button

Per-field reset button that:
1. Clears the local override
2. Restores the preset value
3. Shows confirmation toast
4. Updates UI immediately

### Bulk Reset

```
┌─────────────────────────────────────────────────────────────────────────┐
│ 3 fields overridden from preset                                        │
│                                           [Reset All to Preset Values] │
└─────────────────────────────────────────────────────────────────────────┘
```

### Acceptance Criteria:
- [ ] Override indicator appears when value differs
- [ ] Preset value shown below overridden field
- [ ] Per-field reset button works
- [ ] Bulk reset resets all overrides
- [ ] Visual distinction is clear and accessible

---

## 14.3 Basic Information

### Fields
- **Title** (required): Max 255 characters
- **Slug** (auto-generated): URL-safe identifier
- **Description**: Rich text, max 1000 characters
- **Cover Image**: Upload or select from library

### Slug Behavior
- Auto-generated from title
- Editable after generation
- Uniqueness enforced
- URL preview shown

### Acceptance Criteria:
- [ ] Title required with character counter
- [ ] Slug updates on title change (if not manually edited)
- [ ] Slug uniqueness checked on save
- [ ] Description supports basic formatting
- [ ] Cover image preview shown

---

## 14.4 Status Management

### Status Options
- **DRAFT**: Not visible to participants
- **PUBLISHED**: Active and accessible
- **ARCHIVED**: Read-only, historical

### Status Display
- Current status badge
- Status change dropdown
- Last status change timestamp
- Status change history

### Acceptance Criteria:
- [ ] Status badge color-coded
- [ ] Change requires confirmation
- [ ] Invalid transitions prevented
- [ ] History shows who changed status

---

## 14.5 Deadline Defaults

### Default Settings (Preset-Linkable ⚡)
- Default soft deadline (days from start) ⚡
- Default hard deadline (days from start) ⚡
- Apply defaults to new participants toggle
- Override global defaults toggle

### Deadline Preview
- Calendar visualization
- Example dates based on today
- Warning if deadlines too close

### Preset Integration

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Deadline Settings                                                       │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ Soft Deadline (days)                                                    │
│ ┌───────────────────────────────────────────┐                           │
│ │ 30                                        │  ← From preset            │
│ └───────────────────────────────────────────┘                           │
│                                                                         │
│ Hard Deadline (days)                          ⚡ Overridden from preset │
│ ┌───────────────────────────────────────────┐                           │
│ │ 45                                        │  [↺ Reset]                │
│ └───────────────────────────────────────────┘                           │
│ Preset value: 60 days                                                   │
│                                                                         │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ 📅 Preview: If started today                                        │ │
│ │    Soft deadline: Feb 24, 2026                                      │ │
│ │    Hard deadline: Mar 11, 2026                                      │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
```

### Acceptance Criteria:
- [ ] Defaults editable per exam
- [ ] Validation: hard > soft deadline
- [ ] Preview updates on change
- [ ] Defaults apply to new participants
- [ ] Existing participants unaffected
- [ ] Override indicator when differs from preset

---

## 14.6 Visibility Settings

### Options (Preset-Linkable ⚡)
- **Public**: Anyone with link can view
- **Secret Key Only**: Requires valid key ⚡
- **Invite Only**: Must be on invite list ⚡
- **Registered Only**: Logged-in users only
- **Assigned Only**: Specific participants

### Access Configuration
- Combine multiple visibility rules
- Set start/end visibility dates
- IP whitelist option (optional)

### Preset Integration

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Access Control                                                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ ☑ Invite Only                                 ⚡ Overridden from preset │
│   Preset value: ☐ (off)                                    [↺ Reset]   │
│                                                                         │
│ ☐ Secret Key Required                          ← From preset           │
│                                                                         │
│ ☐ Enable Secret Key Access                                              │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Acceptance Criteria:
- [ ] Multiple rules can combine
- [ ] Date range restricts access
- [ ] Clear explanation of current setting
- [ ] Preview as participant option
- [ ] Override indicators for preset-linked settings

---

## 14.7 Notification Settings

### Per-Exam Overrides (Preset-Linkable ⚡)
- Enable/disable email notifications ⚡
- Custom reminder intervals ⚡
- Custom email templates (select)
- CC additional recipients

### Notification Types Configurable
- Welcome email
- Deadline reminders
- Extension responses
- Completion notification

### Acceptance Criteria:
- [ ] Override global settings
- [ ] Preview email before enabling
- [ ] Test send to admin
- [ ] Disable individual notification types
- [ ] Override indicators for preset-linked settings

---

## 14.8 Advanced Settings

### Settings (Preset-Linkable ⚡)
- **Allow Extensions**: Enable/disable ⚡
- **Max Extension Days**: Limit on requests ⚡
- **Max Extension Requests**: Number limit ⚡
- **Require Prerequisites**: Block until complete
- **Show Progress**: Visible to participant ⚡
- **Show Deadline Countdown**: Display countdown ⚡
- **Allow Retake**: Reset progress option

### Extension Settings with Preset

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Extension Settings                                                      │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ ☑ Allow Extensions                             ← From preset           │
│                                                                         │
│ Max Extension Days                             ⚡ Overridden from preset │
│ ┌───────────────────────────────────────────┐                           │
│ │ 14                                        │  [↺ Reset]                │
│ └───────────────────────────────────────────┘                           │
│ Preset value: 30 days                                                   │
│                                                                         │
│ Max Extension Requests                         ← From preset           │
│ ┌───────────────────────────────────────────┐                           │
│ │ 3                                         │                           │
│ └───────────────────────────────────────────┘                           │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### JSON Settings Field
- Custom key-value pairs
- Used by plugins/extensions
- Schema validation

### Acceptance Criteria:
- [ ] Toggles save immediately
- [ ] JSON editor with validation
- [ ] Unknown keys preserved
- [ ] Settings documented in help
- [ ] Override indicators for all preset-linked fields

---

## 14.9 SEO & Sharing

### SEO Fields
- Meta title (defaults to exam title)
- Meta description (defaults to description)
- OG image (defaults to cover)
- Canonical URL

### Social Sharing Preview
- Facebook card preview
- Twitter card preview
- LinkedIn preview

### Acceptance Criteria:
- [ ] Character limits enforced
- [ ] Previews update in real-time
- [ ] Defaults are sensible
- [ ] Custom values override defaults

---

## 14.10 Preset Summary Panel

### Collapsible Panel at Bottom

When a preset is linked, show a summary of all values:

```
┌─────────────────────────────────────────────────────────────────────────┐
│ ▼ Preset Summary: Self-Paced Training                    [Manage Preset]│
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ Inherited Settings (8)              │ Overridden Settings (2)          │
│ ─────────────────────               │ ─────────────────────────         │
│ • Soft Deadline: 30 days            │ • Hard Deadline: 45 days ⚡       │
│ • Allow Extensions: Yes             │ • Invite Only: Yes ⚡             │
│ • Max Extension Days: 30            │                                   │
│ • Max Extension Requests: 5         │                                   │
│ • Show Progress: Yes                │                                   │
│ • Show Countdown: No                │                                   │
│ • Enable Notifications: Yes         │                                   │
│ • Reminder Days: 14, 7, 3           │                                   │
│                                                                         │
│                                           [Reset All Overrides (2)]    │
└─────────────────────────────────────────────────────────────────────────┘
```

### Acceptance Criteria:
- [ ] Shows all preset values
- [ ] Highlights overridden values
- [ ] Collapsible to save space
- [ ] Link to manage presets
- [ ] Bulk reset option

---

## 14.11 Preset Change Confirmation

### When Changing Preset

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Change Preset?                                                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ Changing from "Self-Paced Training" to "Certification Strict"          │
│                                                                         │
│ This will update the following settings:                                │
│                                                                         │
│ Setting              Current Value     New Value                        │
│ ─────────────────────────────────────────────────                       │
│ Soft Deadline        30 days           14 days                          │
│ Hard Deadline        60 days           21 days                          │
│ Allow Extensions     Yes               No                               │
│ Invite Only          No                Yes                              │
│                                                                         │
│ ⚠️ 2 overridden values will be preserved:                               │
│ • Hard Deadline: 45 days (your override)                                │
│ • Invite Only: Yes (your override)                                      │
│                                                                         │
│ ☐ Also reset my overrides to new preset values                          │
│                                                                         │
│                              [Cancel]    [Change Preset]                │
└─────────────────────────────────────────────────────────────────────────┘
```

### Acceptance Criteria:
- [ ] Show diff of value changes
- [ ] Indicate which overrides will be preserved
- [ ] Option to reset overrides
- [ ] Clear warning about impact

---

## 14.12 API Integration

### Endpoint for Saving with Preset

```json
// PUT /api/exams/{id}/metadata
{
  "title": "React Basics Course",
  "slug": "react-basics",
  "presetId": 3,
  "overrides": {
    "hardDeadlineDays": 45,
    "isInviteOnly": true
  }
}
```

### Override Detection Logic

```tsx
// Check if field is overridden
const isOverridden = (fieldName: string): boolean => {
  if (!exam.presetId) return false;
  const localValue = exam[fieldName];
  const presetValue = preset[fieldName];
  return localValue !== null && localValue !== presetValue;
};

// Get effective value (local override or preset)
const getEffectiveValue = (fieldName: string): any => {
  if (exam[fieldName] !== null) {
    return exam[fieldName];  // Local override
  }
  if (exam.presetId && preset) {
    return preset[fieldName];  // From preset
  }
  return defaults[fieldName];  // Global default
};
```

---

## 14.13 Acceptance Criteria Summary

### Preset Selection
- [ ] Preset dropdown with all available options
- [ ] Preset info summary displayed
- [ ] Unlink preset functionality
- [ ] Link to manage presets

### Override Indicators
- [ ] Visual indicator on overridden fields
- [ ] Preset value shown below
- [ ] Per-field reset buttons
- [ ] Bulk reset option

### Value Resolution
- [ ] Override takes precedence over preset
- [ ] Preset takes precedence over defaults
- [ ] Values update when preset changes
- [ ] Overrides preserved on preset change (by default)

### UX
- [ ] Clear visual hierarchy
- [ ] Tooltips explain preset behavior
- [ ] Confirmation on destructive changes
- [ ] Keyboard accessible
