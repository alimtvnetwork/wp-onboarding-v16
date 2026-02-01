# 11.1 Project Dashboard

**Version:** 1.0.0  
**Status:** Planned  
**Last Updated:** 2026-01-28

---

## Overview

Central hub for project management, displaying project lists, recent activity, quick actions, and navigation to all project features.

**Cross-References:**
- [Route Configuration](../12-routing-navigation/02-route-config.md) - Path constants and builders
- [State Management](../16-state-management/00-overview.md) - Dashboard state
- [Database Schema](../../07-database-design/01-schema.md) - Project model

---

## 11.1.1 Dashboard Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Header: Logo | Search | Theme Toggle | User Menu                       │
├─────────────────────────────────────────────────────────────────────────┤
│  ┌──────────────────┐  ┌──────────────────────────────────────────────┐ │
│  │  Sidebar         │  │  Main Content                                │ │
│  │  ─────────────── │  │  ┌────────────────────────────────────────┐  │ │
│  │  📁 Projects     │  │  │  Welcome Banner / Quick Stats          │  │ │
│  │    └─ Project A  │  │  └────────────────────────────────────────┘  │ │
│  │    └─ Project B  │  │  ┌────────────────────────────────────────┐  │ │
│  │  📊 Analytics    │  │  │  Project Grid / List View               │  │ │
│  │  ⚙️ Settings    │  │  │  [Card] [Card] [Card] [Card]            │  │ │
│  │  ─────────────── │  │  │  [Card] [Card] [+ New Project]         │  │ │
│  │  Recent Files    │  │  └────────────────────────────────────────┘  │ │
│  │  • file1.md      │  │  ┌────────────────────────────────────────┐  │ │
│  │  • file2.md      │  │  │  Recent Activity Feed                   │  │ │
│  │                  │  │  └────────────────────────────────────────┘  │ │
│  └──────────────────┘  └──────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 11.1.2 Project Card Component

```typescript
interface ProjectCardProps {
  project: Project;
  onOpen: (id: string) => void;
  onEdit: (id: string) => void;
  onDelete: (id: string) => void;
}

// Card displays:
// - Project name and description
// - File count and last modified
// - Health score badge (from consistency checker)
// - Quick action menu (edit, export, delete)
// - Visibility indicator (private/global)
```

---

## 11.1.3 Quick Stats Widget

| Metric | Description | Icon |
|--------|-------------|------|
| Total Projects | Count of user's projects | 📁 |
| Total Specs | Sum of spec files across projects | 📄 |
| Health Score | Average consistency score | ✅ |
| Recent Edits | Files modified in last 7 days | ✏️ |

---

## 11.1.4 Search Functionality

```typescript
interface DashboardSearch {
  query: string;
  filters: {
    type: 'all' | 'projects' | 'files' | 'content';
    dateRange?: DateRange;
    status?: ProjectStatus[];
  };
  results: SearchResult[];
}

// Search targets:
// - Project names and descriptions
// - File names and content (full-text)
// - Tags and categories
```

---

## 11.1.5 View Modes

| Mode | Description | Best For |
|------|-------------|----------|
| Grid | Card-based visual layout | Overview browsing |
| List | Compact table with sorting | Many projects |
| Kanban | Status-based columns | Workflow tracking |

---

## 11.1.6 New Project Flow

```
[+ New Project] → Dialog
├─ Option 1: Blank Project
│  └─ Enter name, description → Create
├─ Option 2: From Template
│  └─ Select template → Customize → Create
├─ Option 3: Import
│  └─ Upload ZIP/MD/PRD → Parse → Review → Create
└─ Option 4: From Preset
   └─ Select preset → Apply guidelines → Create
```

---

## 11.1.7 Activity Feed

```typescript
interface ActivityItem {
  id: string;
  type: 'file_created' | 'file_updated' | 'project_created' | 
        'snapshot_created' | 'instruction_completed';
  timestamp: Date;
  actor: User;
  target: {
    type: 'file' | 'project' | 'snapshot' | 'instruction';
    id: string;
    name: string;
  };
  metadata?: Record<string, unknown>;
}
```

---

## Related Specs

- [Routing Navigation](../12-routing-navigation/00-overview.md)
- [Project Management](../03-project-management/00-overview.md)
- [File Management](../02-file-management/00-overview.md)
