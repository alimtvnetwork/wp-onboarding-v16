# 10. Sub-Exams Display

## Overview
Hierarchical display of parent/child exam relationships with progress tracking and navigation.

---

## 10.1 Exam Hierarchy Concept

Exams can have parent-child relationships:
```
Advanced JavaScript (Parent)
├── ES6 Fundamentals (Child)
├── Async Programming (Child)
└── Testing Strategies (Child)
```

Each child exam:
- Has its own sections and progress
- May inherit deadlines from parent
- Tracks independently

---

## 10.2 Sub-Exams on Landing Page

When parent exam has children, show "Sub-Exams" section:

```
┌─────────────────────────────────────────────┐
│  Sub-Exams                                  │
│  "This exam contains 3 modules"             │
├─────────────────────────────────────────────┤
│  ┌─────────────────────────────────────┐    │
│  │  📚 ES6 Fundamentals                │    │
│  │  8 sections | ~2 hours              │    │
│  │  [View Module]                      │    │
│  └─────────────────────────────────────┘    │
│                                             │
│  ┌─────────────────────────────────────┐    │
│  │  📚 Async Programming               │    │
│  │  6 sections | ~1.5 hours            │    │
│  │  [View Module]                      │    │
│  └─────────────────────────────────────┘    │
└─────────────────────────────────────────────┘
```

---

## 10.3 Sub-Exams on Dashboard

When participant is viewing parent exam dashboard:

```
┌─────────────────────────────────────────────┐
│  Your Modules                               │
│  "Complete all modules to finish the exam"  │
├─────────────────────────────────────────────┤
│  ┌─────────────────────────────────────┐    │
│  │  ✓ ES6 Fundamentals       Complete │    │
│  │  ████████████████████████ 100%     │    │
│  │  8 of 8 sections | [Review]        │    │
│  └─────────────────────────────────────┘    │
│                                             │
│  ┌─────────────────────────────────────┐    │
│  │  ⟳ Async Programming    In Progress│    │
│  │  ████████░░░░░░░░░░░░░░ 50%        │    │
│  │  3 of 6 sections | [Continue]      │    │
│  └─────────────────────────────────────┘    │
│                                             │
│  ┌─────────────────────────────────────┐    │
│  │  ○ Testing Strategies   Not Started│    │
│  │  ░░░░░░░░░░░░░░░░░░░░░░ 0%         │    │
│  │  0 of 5 sections | [Start]         │    │
│  └─────────────────────────────────────┘    │
└─────────────────────────────────────────────┘
```

---

## 10.4 Sub-Exam Card States

| State | Icon | Badge | Progress Bar | Button |
|-------|------|-------|--------------|--------|
| Not Started | ○ | "Not Started" (gray) | Empty | "Start" |
| In Progress | ⟳ | "In Progress" (yellow) | Partial fill | "Continue" |
| Completed | ✓ | "Complete" (green) | Full | "Review" |
| Locked | 🔒 | "Locked" (red) | N/A | Disabled |

---

## 10.5 Breadcrumb Navigation

When viewing child exam, show navigation path:

```
Parent Exam > Child Exam > Section 3
     ↑            ↑           ↑
  Clickable   Clickable   Current
```

### Breadcrumb Behavior
- Click parent → Navigate to parent dashboard
- Click child → Navigate to child dashboard
- Current item not clickable

---

## 10.6 Deadline Inheritance

Child exams can:
1. **Inherit** - Use parent's deadline
2. **Override** - Have their own deadline

### Display Logic
| Mode | Deadline Display |
|------|------------------|
| Inherit | "Deadline: (from parent) Jan 31, 1:00 PM" |
| Override | "Deadline: Feb 5, 1:00 PM" |

---

## 10.7 Navigation Flow

### From Parent to Child
1. User on parent dashboard
2. Clicks child exam card
3. Navigate to `/{child-slug}/dashboard`
4. Child dashboard shows breadcrumb to parent

### From Child to Parent
1. User on child exam
2. Clicks parent in breadcrumb
3. Navigate to `/{parent-slug}/dashboard`

---

## 10.8 Overall Progress Calculation

Parent exam progress can aggregate child progress:

```
Parent: Advanced JavaScript
├── ES6 Fundamentals: 100% (8/8)
├── Async Programming: 50% (3/6)
└── Testing Strategies: 0% (0/5)

Overall: 11 of 19 sections = 57%
```

### Display
- Show individual child progress
- Optionally show aggregate for parent
- "Complete all modules to finish"

---

## 10.9 API Dependencies

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `GET /api/exams/{id}/children` | GET | List child exams |
| `GET /api/exams/{id}/parent` | GET | Get parent exam |
| `GET /api/participants/me/progress?examId={id}` | GET | Progress per child |

---

## 10.10 Acceptance Criteria

### Display
- [ ] Child exams display as cards with progress
- [ ] Card states reflect completion status
- [ ] Progress bars show accurate percentage

### Navigation
- [ ] Click card navigates to child dashboard
- [ ] Breadcrumb shows full path
- [ ] Back navigation works correctly

### Progress
- [ ] Each child tracks independently
- [ ] Parent shows aggregate progress (if configured)
- [ ] Deadline inheritance displays correctly

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Exam Hierarchy** | [13-exam-hierarchy](../../01-admin-backend/split-spec/13-exam-hierarchy.md) | Backend parent/child logic |
| **Sub-Exams Tab** | [17-exam-subexams-tab](../../01-admin-backend/split-spec/17-exam-subexams-tab.md) | Admin configuration |
| **Dashboard** | [06-dashboard-page](06-dashboard-page.md) | Contains sub-exams section |
| **Deadline Engine** | [29-deadline-engine](../../01-admin-backend/split-spec/29-deadline-engine.md) | Deadline inheritance |

---

*Next: `11-deadline-countdown.md`*
