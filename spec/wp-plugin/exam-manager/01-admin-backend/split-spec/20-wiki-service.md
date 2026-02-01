# 18 - Wiki Service

## Overview

PHP service class for managing Wiki pages, providing CRUD operations, Markdown processing, visibility enforcement, and revision tracking. Wikis serve as supplementary documentation accessible via `[[Wiki Link]]` syntax in exam content.

---

## Dependencies

- `03-orm-base-classes.md` (BaseService, BaseRepository)
- `06-entity-models.md` (Wiki, WikiRevision, WikiCategory entities)
- `04-enums-constants.md` (WikiVisibility enum)
- `08-rbac-system.md` (permission checks)

---

## Class Structure

### 18.1 WikiService

```
CLASS WikiService EXTENDS BaseService:
  
  PROPERTIES:
    - repository: WikiRepository
    - revisionRepository: WikiRevisionRepository
    - categoryRepository: WikiCategoryRepository
    - markdownParser: MarkdownParserInterface
  
  METHODS:
    # CRUD Operations
    + create(data: WikiCreateDTO): Wiki
    + update(id: int, data: WikiUpdateDTO): Wiki
    + delete(id: int, hardDelete: bool = false): bool
    + findById(id: int): Wiki|null
    + findBySlug(slug: string): Wiki|null
    
    # Listing & Search
    + list(filters: WikiFilters): PaginatedResult<Wiki>
    + search(query: string, visibility: array): array<Wiki>
    + listByCategory(categoryId: int): array<Wiki>
    
    # Content Processing
    + parseContent(markdown: string): ParsedContent
    + extractWikiLinks(content: string): array<string>
    + validateWikiLinks(links: array): ValidationResult
    + renderToHtml(wiki: Wiki): string
    
    # Visibility & Access
    + canUserAccess(wiki: Wiki, userId: int|null): bool
    + getAccessibleWikis(userId: int|null): array<Wiki>
    + updateVisibility(id: int, visibility: WikiVisibility, roles: array): bool
    
    # Revisions
    + createRevision(wikiId: int, content: string, userId: int): WikiRevision
    + getRevisions(wikiId: int): array<WikiRevision>
    + restoreRevision(revisionId: int): Wiki
    + diffRevisions(fromId: int, toId: int): DiffResult
```

---

## Functional Requirements

### 18.2 Wiki Entity Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | Integer | Auto-increment PK |
| `slug` | String(100) | URL-safe unique identifier |
| `title` | String(255) | Display title |
| `content` | Text | Markdown content |
| `categoryId` | Integer/null | FK to WikiCategory |
| `visibility` | Enum | PUBLIC, AUTHENTICATED, ROLE, PRIVATE |
| `allowedRoles` | JSON/null | Role IDs for ROLE visibility |
| `authorId` | Integer | Created by user ID |
| `isPublished` | Boolean | Draft vs published |
| `publishedAt` | DateTime/null | First publish timestamp |
| `sortOrder` | Integer | Order within category |
| `createdAt` | DateTime | Creation timestamp |
| `updatedAt` | DateTime | Last modification |

### 18.3 Visibility Levels

```
WIKI_VISIBILITY:
  - PUBLIC         # Anyone can view (no auth required)
  - AUTHENTICATED  # Any logged-in user
  - ROLE           # Users with specific roles only
  - PRIVATE        # Author and Admins only
```

### 18.4 Markdown Processing

**Supported Syntax**
- Standard Markdown (headers, lists, code blocks, etc.)
- Tables with alignment
- Task lists (checkboxes)
- Syntax-highlighted code blocks
- Image embedding with captions
- `[[Wiki Link]]` internal linking
- `[[Wiki Link|Custom Text]]` aliased links

**Wiki Link Resolution**
- [ ] Parse `[[slug]]` or `[[slug|text]]` syntax
- [ ] Validate target wiki exists
- [ ] Check user has access to linked wiki
- [ ] Generate appropriate URL or "no access" indicator

---

## Business Rules

### 18.5 Slug Generation

- [ ] Auto-generate from title on create
- [ ] Lowercase, hyphenated, alphanumeric only
- [ ] Append number suffix on conflict
- [ ] Manual override allowed
- [ ] Slug changes create redirect entry

### 18.6 Revision Management

- [ ] New revision on every content save
- [ ] Store diff, not full content (optional optimization)
- [ ] Revision limit per wiki (configurable, default 50)
- [ ] Auto-prune old revisions beyond limit
- [ ] Revision author and timestamp tracked

### 18.7 Access Control

- [ ] PUBLIC: No authentication check
- [ ] AUTHENTICATED: Valid session required
- [ ] ROLE: Check user roles against `allowedRoles`
- [ ] PRIVATE: Author ID match or Admin role
- [ ] Admins bypass all visibility restrictions

---

## Acceptance Criteria

### CRUD Operations
- [ ] Create wiki with all required fields
- [ ] Update wiki triggers revision creation
- [ ] Soft delete preserves data, hard delete removes
- [ ] Find by ID and slug work correctly
- [ ] List supports pagination and filtering

### Content Processing
- [ ] Markdown renders to valid HTML
- [ ] Wiki links extracted correctly
- [ ] Invalid links flagged in validation
- [ ] Code blocks syntax highlighted
- [ ] Images processed with lazy loading

### Visibility Enforcement
- [ ] PUBLIC wikis accessible without auth
- [ ] AUTHENTICATED requires valid session
- [ ] ROLE checks against user's assigned roles
- [ ] PRIVATE restricted to author/admin
- [ ] Visibility change logged to audit

### Revisions
- [ ] Revision created on each save
- [ ] Revision list ordered by date desc
- [ ] Restore creates new revision (not destructive)
- [ ] Diff shows additions/deletions clearly
- [ ] Old revisions pruned per configuration

### Performance
- [ ] Wiki list query < 100ms for 1000 wikis
- [ ] Markdown parsing cached per content hash
- [ ] Revision diff computed efficiently
- [ ] Bulk link validation batched

---

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Duplicate slug | Append numeric suffix |
| Invalid Markdown | Sanitize, log warning |
| Broken wiki link | Render as plain text with warning class |
| Revision not found | 404 with clear message |
| Unauthorized access | 403 with visibility level hint |

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wikis` | List wikis (filtered by access) |
| POST | `/wikis` | Create new wiki |
| GET | `/wikis/{slug}` | Get wiki by slug |
| PUT | `/wikis/{id}` | Update wiki |
| DELETE | `/wikis/{id}` | Delete wiki |
| GET | `/wikis/{id}/revisions` | List revisions |
| POST | `/wikis/{id}/revisions/{revId}/restore` | Restore revision |

---

## Notes

- Wiki content sanitized before storage (XSS prevention)
- Full-text search index on title + content
- Category assignment optional but recommended
- Orphan wikis (no category) listed separately
