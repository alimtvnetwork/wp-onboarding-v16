# RAG Integration Update Plan

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-28  

---

## Overview

This document outlines the required updates to existing specifications to align with the new RAG system, Path Manager, and workDirectory-relative path handling.

---

## 19.1 Summary of New Specifications

| Spec | Description |
|------|-------------|
| [01-rag-system.md](./01-rag-system.md) | RAG pipeline, chunking, embedding, retrieval, top-K memory |
| [../02-file-management/02-path-manager.md](../02-file-management/02-path-manager.md) | workDirectory config, relative path handling, security |
| [02-rag-spec-guidelines.md](./02-rag-spec-guidelines.md) | Writing RAG-optimized specifications |
| [03-rag-integration-plan.md](./03-rag-integration-plan.md) | This document - update plan |

---

## 19.2 Specs Requiring Updates

### Database Schema (02-database-schema.md)

**Updates Required:**

1. **Add new entity models:**
   - `Artifact` table for indexed ideas/instructions
   - `Chunk` table for text segments
   - `Embedding` table for vector storage
   - `RetrievalSession` table for query logging
   - `RetrievalSessionChunk` junction table

2. **Update ER diagram** to include RAG tables

3. **Add FTS5 virtual table documentation** (exception to ORM-only policy)

**Sections to Add:**
```markdown
## RAG Indexing Tables

### Artifact
### Chunk
### Embedding
### RetrievalSession
### RetrievalSessionChunk

## FTS5 Virtual Tables (Exception)
```

---

### File Operations (04-file-operations.md)

**Updates Required:**

1. **Add ideas/ and instructions/ folder conventions** to path structure
2. **Reference PathManager** for all path operations
3. **Add numbered filename generation** section
4. **Add artifact file creation** workflow

**Sections to Update:**
- 4.2.1 Path Structure → Add ideas/, instructions/ directories
- 4.3.1 Naming Pattern → Add idea-{slug}, instruction-{slug} patterns
- 4.4.1 Create File → Reference PathManager.SafeWrite

**New Sections:**
```markdown
## 4.X Artifact File Management

### 4.X.1 Idea Files
### 4.X.2 Instruction Files
### 4.X.3 Numbered Filename Generation
```

---

### Seeding Configuration (09-seeding-configuration.md)

**Updates Completed:**

✅ Added `path.workDirectory` configuration key  
✅ Added `path.maxLength` configuration key  
✅ Added `path.allowedExtensions` configuration key  
✅ Added all `rag.*` configuration keys  

**Remaining Updates:**

1. **Add to seed.json example:**
   - Path configuration block
   - RAG configuration block

2. **Add initialization order** section documenting PathManager init before file ops

---

### AI Integration (08-ai-integration.md)

**Updates Required:**

1. **Reference RAG system** for context retrieval
2. **Add RAG-aware prompt building** section
3. **Document top-K memory injection** into AI prompts

**Sections to Add:**
```markdown
## 7.X RAG-Augmented Prompts

### 7.X.1 Context Retrieval Before Generation
### 7.X.2 Top-K Memory Injection
### 7.X.3 Grounding References in Responses
```

---

### Instruction System (11-instruction-system.md)

**Updates Required:**

1. **Link to RAG system** for context retrieval during planning
2. **Add idea promotion** workflow details
3. **Reference ideas/ folder** structure
4. **Add sourceIdeaId** to instruction linking

**Sections to Update:**
- 11.3 Database Schema → Add `SourceIdeaId` field reference
- 11.4 Instruction Processing → Reference RAG context retrieval
- 11.6 Filesystem Storage → Align with ideas/instructions structure

**New Sections:**
```markdown
## 11.X Idea Promotion

### 11.X.1 Promotion Workflow
### 11.X.2 Artifact Linking
### 11.X.3 Re-indexing After Promotion
```

---

### API Endpoints (03-api-endpoints.md)

**Updates Required:**

1. **Add Idea endpoints:**
   - POST /projects/{id}/ideas
   - GET /projects/{id}/ideas
   - PUT /projects/{id}/ideas/{ideaId}
   - POST /projects/{id}/ideas/{ideaId}/promote
   - DELETE /projects/{id}/ideas/{ideaId}

2. **Add RAG endpoints:**
   - POST /projects/{id}/rag/query
   - POST /projects/{id}/rag/reindex
   - GET /projects/{id}/rag/stats
   - DELETE /projects/{id}/rag/cache

**New Sections:**
```markdown
## X.X Idea Management Endpoints
## X.X RAG Retrieval Endpoints
```

---

### Backend Overview (01-overview.md)

**Updates Completed:**

✅ Added RAG System to Related Specs  
✅ Added Path Manager to Related Specs  
✅ Added RAG Spec Guidelines to Related Specs  
✅ Reorganized specs into logical groups  

---

## 19.3 Database Model Updates

### New Tables Summary

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `Artifact` | Indexed idea/instruction | `Id`, `ProjectId`, `FileId`, `ArtifactType`, `RelativePath`, `IsPinned` |
| `Chunk` | Text segment | `Id`, `ArtifactId`, `ChunkIndex`, `Content`, `TokenCount`, `SectionAnchor` |
| `Embedding` | Vector storage | `ChunkId`, `ModelId`, `Dimensions`, `Vector` (BLOB) |
| `RetrievalSession` | Query log | `Id`, `ProjectId`, `QueryText`, `LatencyMs`, `CacheHit` |
| `RetrievalSessionChunk` | Session-chunk link | `SessionId`, `ChunkId`, `Score`, `RankPosition` |

### Existing Table Modifications

| Table | Field | Type | Purpose |
|-------|-------|------|---------|
| `Instruction` | `SourceIdeaId` | TEXT (FK) | Link to promoting idea |
| `Project` | (none) | - | Path already relative |
| `File` | (none) | - | Path already relative, verify |

---

## 19.4 Path Handling Migration

### Verification Checklist

For each spec that handles file paths:

- [ ] All stored paths are relative to workDirectory
- [ ] No absolute paths in examples or code
- [ ] References PathManager for resolution
- [ ] Uses PathManager.ValidateRelativePath before storage
- [ ] Uses PathManager.Resolve before filesystem access

### Specs to Verify

1. **02-database-schema.md** — Path column definitions
2. **04-file-operations.md** — CRUD operations
3. **05-git-integration.md** — Git paths
4. **06-history-system.md** — Snapshot paths
5. **11-instruction-system.md** — Artifact paths

---

## 19.5 Top-K Memory Behavior Updates

### Specs to Update

1. **08-ai-integration.md** — Add top-K memory injection
2. **11-instruction-system.md** — Reference RAG for planning context

### Implementation Requirement

Before any AI generation, the system MUST:

1. Call RAG retrieval with current context
2. Include top-K recent ideas (default: 3)
3. Include top-K recent instructions (default: 2)
4. Include semantic chunks (default: 10)
5. Build combined context for prompt

---

## 19.6 ideas/ and instructions/ Folder Standard

### Folder Structure Rule

Every project MUST have:

```
{project-slug}/
├── ideas/
│   ├── README.md
│   └── {nn}-idea-{slug}.md
├── instructions/
│   └── {nn}-instruction-{slug}.md
└── spec/
    └── ...
```

### File Creation Rules

1. **ideas/** created on first voice input or manual idea
2. **instructions/** created on first instruction or promotion
3. **README.md** in ideas/ auto-generated with template
4. Numeric prefixes auto-increment from MAX + 1

---

## 19.7 Implementation Priority

### Phase 1: Foundation (Critical)

1. ✅ Create 16-rag-system.md
2. ✅ Create 17-path-manager.md
3. ✅ Create 18-rag-spec-guidelines.md
4. ✅ Update 09-seeding-configuration.md with new config keys
5. ✅ Update 01-overview.md with new spec links

### Phase 2: Database (Required)

1. [ ] Update 02-database-schema.md with new tables
2. [ ] Add GORM models for Artifact, Chunk, Embedding
3. [ ] Add FTS5 virtual table documentation

### Phase 3: Integration (Required)

1. [ ] Update 04-file-operations.md with artifact paths
2. [ ] Update 03-api-endpoints.md with new endpoints
3. [ ] Update 08-ai-integration.md with RAG context

### Phase 4: Instruction System (Required)

1. [ ] Update 11-instruction-system.md with idea promotion
2. [ ] Add sourceIdeaId field documentation
3. [ ] Document re-indexing workflow

### Phase 5: Quality (Recommended)

1. [ ] Run consistency checker on all updated specs
2. [ ] Verify cross-references resolve correctly
3. [ ] Add acceptance tests for path handling

---

## 19.8 Acceptance Criteria

### Foundation Complete
- [x] RAG system spec created with full pipeline
- [x] Path Manager spec created with security rules
- [x] Spec guidelines created for RAG-friendly writing
- [x] Seeding config updated with new keys
- [x] Backend overview updated with new links

### Database Ready
- [ ] All new GORM models documented
- [ ] ER diagram updated
- [ ] FTS5 exception documented

### API Ready
- [ ] Idea endpoints documented
- [ ] RAG endpoints documented
- [ ] Request/response formats complete

### Path Handling Ready
- [ ] All specs reference PathManager
- [ ] No absolute paths in storage examples
- [ ] workDirectory initialization documented

---

## Related Specs

- [RAG System](./01-rag-system.md)
- [Path Manager](../02-file-management/02-path-manager.md)
- [RAG Spec Guidelines](./02-rag-spec-guidelines.md)
- [Database Schema](../../07-database-design/01-schema.md)
- [File Operations](../02-file-management/01-file-operations.md)
- [AI Integration](../06-ai-integration/01-ai-integration.md)
- [Instruction System](../06-ai-integration/03-instruction-system.md)
