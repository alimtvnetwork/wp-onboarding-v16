# 09. Prerequisites Display

## Overview
Display and interaction for exam prerequisites including videos, links, and checklist items that must be completed before starting the exam.

---

## 09.1 Prerequisites Location

Prerequisites appear in two places:
1. **Landing Page** - Overview for unauthenticated users
2. **Dashboard** - Interactive for participants

---

## 09.2 Prerequisites Layout

```
┌─────────────────────────────────────────────┐
│  Prerequisites                              │
│  "Complete these before starting"           │
│  Progress: "3 of 5 completed"               │
│  [+] Show / [-] Hide                        │
├─────────────────────────────────────────────┤
│  🎬 Video: JavaScript Basics    [Required]  │
│     Watch this 15-minute intro              │
│     [▶ Watch on YouTube]    [✓ Watched]     │
├─────────────────────────────────────────────┤
│  🔗 Link: MDN JavaScript Guide  [Required]  │
│     Read the fundamentals section           │
│     [↗ Open Article]    [✓ Read]            │
├─────────────────────────────────────────────┤
│  ☐ Checklist: Install Node.js   [Required]  │
│     Make sure you have Node 18+ installed   │
│     [✓ Mark as Done]                        │
└─────────────────────────────────────────────┘
```

---

## 09.3 Prerequisite Types

### VIDEO Type
- **Display**: Title, description, embedded video or link
- **Sources**: YouTube, Vimeo
- **Embed**: `<iframe src="https://www.youtube.com/embed/{videoId}"></iframe>`
- **Action**: "Watch on YouTube" link + "Mark as Watched" checkbox

### LINK Type
- **Display**: Title, description, clickable link
- **Action**: Opens in new tab, "Mark as Read" checkbox
- **Icon**: External link indicator (↗)

### TEXT/CHECKLIST Type
- **Display**: Title, description
- **Action**: Checkbox to mark as done
- **No external resource

---

## 09.4 Video Embedding

### YouTube
```html
<iframe 
  src="https://www.youtube.com/embed/{videoId}"
  width="560"
  height="315"
  frameborder="0"
  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
  allowfullscreen>
</iframe>
```

### Vimeo
```html
<iframe 
  src="https://player.vimeo.com/video/{videoId}"
  width="560"
  height="315"
  frameborder="0"
  allow="autoplay; fullscreen; picture-in-picture"
  allowfullscreen>
</iframe>
```

### Fallback
- If embed fails, show "Open in YouTube/Vimeo" link
- Link opens in new tab

---

## 09.5 Completion Tracking

### Marking Complete
1. User clicks checkbox or "Mark as Done" button
2. POST to `/api/participants/me/prerequisites/{id}/complete`
3. Show loading indicator
4. On success:
   - Update checkbox to checked state
   - Update progress counter
   - Log event

### Completion Rules
- **Honor system** - No actual video watch tracking
- User self-reports completion
- Required prerequisites block exam access

---

## 09.6 Required vs Optional

### Visual Indicators

| Type | Badge | Behavior |
|------|-------|----------|
| Required | `[Required]` red badge | Must complete before exam |
| Optional | `[Optional]` gray badge | Nice to have |

### Blocking Logic
- If ANY required prerequisite incomplete → "Start Exam" disabled
- Show message: "Complete all required prerequisites to start"
- Progress: "3 of 5 required items completed"

---

## 09.7 Progress Display

### Progress Counter
- Format: "X of Y prerequisites completed"
- Separate count for required items
- Visual progress bar (optional)

### Completion State
- All complete: "All prerequisites completed! ✓"
- Enable "Start Exam" button
- Green success styling

---

## 09.8 Expandable/Collapsible

### Default State
- On landing page: Expanded
- On dashboard: Collapsed (if all complete)

### Toggle Behavior
- Click "[+] Show" to expand
- Click "[-] Hide" to collapse
- Remember preference in localStorage

---

## 09.9 API Dependencies

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `GET /api/exams/{id}/prerequisites` | GET | Load prerequisites list |
| `POST /api/participants/me/prerequisites/{id}/complete` | POST | Mark as done |
| `DELETE /api/participants/me/prerequisites/{id}/complete` | DELETE | Undo completion |

---

## 09.10 Logged Events

| Event | Trigger | Details |
|-------|---------|---------|
| `prerequisiteViewed` | Item expanded/clicked | `{type, title, id}` |
| `prerequisiteCompleted` | Marked as done | `{type, title, id}` |
| `prerequisiteUndone` | Unmarked | `{type, title, id}` |
| `allPrerequisitesCompleted` | All required done | `{count}` |
| `videoPlayed` | Video embed played | `{videoId, platform}` |
| `linkClicked` | External link opened | `{url, title}` |

---

## 09.11 Acceptance Criteria

### Display
- [ ] All prerequisites display in order
- [ ] Videos embed correctly (YouTube/Vimeo)
- [ ] Links open in new tabs
- [ ] Required badge shows for required items

### Interaction
- [ ] Checkbox marks item as complete
- [ ] Progress counter updates in real-time
- [ ] Expand/collapse works

### Blocking
- [ ] Required incomplete → Start Exam disabled
- [ ] All complete → Start Exam enabled
- [ ] Clear message about completion requirements

### Persistence
- [ ] Completion persists across sessions
- [ ] Progress loaded from backend on page load

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Dashboard** | [06-dashboard-page](06-dashboard-page.md) | Contains prerequisites section |
| **Landing Page** | [02-public-landing-page](02-public-landing-page.md) | Shows prerequisites overview |
| **Backend Prerequisites** | [18-exam-prerequisites-tab](../../01-admin-backend/split-spec/18-exam-prerequisites-tab.md) | Admin configuration |
| **Checklist Items** | [19-exam-checklists-tab](../../01-admin-backend/split-spec/19-exam-checklists-tab.md) | PRE phase items |

---

*Next: `10-sub-exams-display.md`*
