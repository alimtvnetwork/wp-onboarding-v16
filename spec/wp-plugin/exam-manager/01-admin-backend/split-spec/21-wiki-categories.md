# 19 - Wiki Categories

## Overview

Management system for organizing Wiki pages into hierarchical categories. Categories support nested structures, custom visibility rules, and bulk operations for efficient content organization.

---

## Dependencies

- `18-wiki-service.md` (WikiService integration)
- `06-entity-models.md` (WikiCategory entity)
- `08-rbac-system.md` (permission checks)

---

## Functional Requirements

### 19.1 Category Entity Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | Integer | Auto-increment PK |
| `slug` | String(100) | URL-safe unique identifier |
| `name` | String(255) | Display name |
| `description` | Text/null | Category description |
| `parentId` | Integer/null | FK to parent category |
| `icon` | String(50)/null | Icon class or emoji |
| `color` | String(7)/null | Hex color for UI |
| `visibility` | Enum | Inherited visibility default |
| `sortOrder` | Integer | Order within parent |
| `isExpanded` | Boolean | Default UI state |
| `createdAt` | DateTime | Creation timestamp |
| `updatedAt` | DateTime | Last modification |

### 19.2 Category Hierarchy

```
HIERARCHY RULES:
  - Maximum depth: 5 levels
  - Root categories have parentId = null
  - Children inherit parent visibility by default
  - Deleting parent requires handling children
```

### 19.3 Category Service Methods

```
CLASS WikiCategoryService:
  
  METHODS:
    # CRUD
    + create(data: CategoryCreateDTO): WikiCategory
    + update(id: int, data: CategoryUpdateDTO): WikiCategory
    + delete(id: int, strategy: DeleteStrategy): bool
    + findById(id: int): WikiCategory|null
    + findBySlug(slug: string): WikiCategory|null
    
    # Hierarchy
    + getTree(): array<CategoryNode>
    + getChildren(parentId: int): array<WikiCategory>
    + getAncestors(id: int): array<WikiCategory>
    + move(id: int, newParentId: int|null, position: int): bool
    
    # Wiki Association
    + getWikis(categoryId: int, includeChildren: bool): array<Wiki>
    + assignWiki(wikiId: int, categoryId: int): bool
    + unassignWiki(wikiId: int): bool
    + bulkMove(wikiIds: array, categoryId: int): int
```

---

## Business Rules

### 19.4 Hierarchy Constraints

- [ ] Maximum 5 levels of nesting
- [ ] Circular references prevented
- [ ] Root categories always accessible to admins
- [ ] Empty categories can be hidden from public view

### 19.5 Delete Strategies

```
DELETE_STRATEGIES:
  - MOVE_TO_PARENT   # Children move up one level
  - MOVE_TO_ROOT     # Children become root categories
  - CASCADE          # Delete all children (requires confirmation)
  - BLOCK            # Prevent deletion if has children
```

### 19.6 Visibility Inheritance

- [ ] Categories have default visibility setting
- [ ] Wikis can override category visibility
- [ ] Child categories can be more restrictive than parent
- [ ] Child categories cannot be less restrictive than parent

---

## UI Components

### 19.7 Category Tree View

**Features**
- Collapsible tree structure
- Drag-and-drop reordering
- Drag to reparent categories
- Context menu (edit, delete, add child)
- Wiki count badges
- Quick add button per category

**Visual Indicators**
- Icon/emoji display
- Color coding
- Visibility level icons
- Expanded/collapsed state
- Empty category styling

### 19.8 Category Editor Modal

**Fields**
- Name input (required)
- Slug input (auto-generated, editable)
- Description textarea
- Parent category dropdown
- Icon picker (emoji or icon class)
- Color picker
- Visibility dropdown
- Sort order input

**Actions**
- Save / Save and Add Another
- Cancel
- Delete (with strategy selection)

### 19.9 Bulk Operations Panel

**Available Operations**
- Move multiple wikis to category
- Change visibility of category + all wikis
- Export category with wikis
- Merge two categories

---

## Acceptance Criteria

### Category Management
- [ ] Create category with all fields
- [ ] Nest categories up to 5 levels
- [ ] Move categories via drag-and-drop
- [ ] Delete with all strategies working
- [ ] Slug auto-generation and uniqueness

### Hierarchy Operations
- [ ] Tree view renders correctly
- [ ] Expand/collapse persisted
- [ ] Circular reference prevention works
- [ ] Ancestor chain retrieval accurate
- [ ] Depth limit enforced

### Wiki Association
- [ ] Assign wiki to category
- [ ] Move wiki between categories
- [ ] Bulk move multiple wikis
- [ ] Unassigned wikis in "Uncategorized" section
- [ ] Wiki count accurate in tree

### Visibility
- [ ] Default visibility applied to new wikis
- [ ] Override visibility per wiki works
- [ ] Inheritance rules enforced
- [ ] Visibility changes logged

### Performance
- [ ] Tree renders < 200ms with 100 categories
- [ ] Drag operations smooth (60fps)
- [ ] Bulk operations batched efficiently

---

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Circular reference attempt | Block with error message |
| Depth limit exceeded | Block move, show limit info |
| Duplicate slug | Append numeric suffix |
| Delete with children | Show strategy selection |
| Visibility conflict | Show inheritance warning |

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wiki-categories` | Get full tree |
| POST | `/wiki-categories` | Create category |
| GET | `/wiki-categories/{id}` | Get single category |
| PUT | `/wiki-categories/{id}` | Update category |
| DELETE | `/wiki-categories/{id}` | Delete with strategy |
| POST | `/wiki-categories/{id}/move` | Move in tree |
| GET | `/wiki-categories/{id}/wikis` | List wikis in category |

---

## Notes

- Category tree cached, invalidated on changes
- Flat list alternative for accessibility
- Breadcrumb navigation in wiki view
- Category statistics in dashboard
