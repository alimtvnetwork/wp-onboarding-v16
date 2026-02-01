# RAG-Friendly Spec Writing Guidelines

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-28  

---

## Overview

This document defines guidelines for writing specifications that are optimized for RAG (Retrieval-Augmented Generation) processing. Following these conventions ensures AI systems can effectively parse, chunk, index, and retrieve relevant context from spec files.

**Cross-References:**
- [RAG System](./01-rag-system.md) - Technical RAG implementation
- [Path Manager](../02-file-management/02-path-manager.md) - File path handling
- [File Operations](../02-file-management/01-file-operations.md) - File naming conventions

---

## 18.1 Document Structure

### Standard Template

Every spec file MUST follow this structure:

```markdown
# {Document Title}

**Version:** {Major.Minor.Patch}  
**Status:** Draft | Active | Deprecated  
**Updated:** {YYYY-MM-DD}  

---

## Overview

{One paragraph summary of what this document covers}

**Cross-References:**
- [Related Spec 1](./path.md) - Brief description
- [Related Spec 2](./path.md) - Brief description

---

## {N}.1 Section Title

{Content with clear subsections}

### {N}.1.1 Subsection

{Detailed content}

---

## {N}.2 Next Section

...

---

## Related Specs

- [Link 1](./path.md)
- [Link 2](./path.md)
```

### Section Numbering

- Use **document-relative numbering**: `16.1`, `16.2`, etc.
- Match document number to filename: `16-rag-system.md` → sections `16.1`, `16.2`
- Subsections use dot notation: `16.1.1`, `16.1.2`
- Enables stable anchor references: `#16-1-section-title`

---

## 18.2 RAG-Optimized Formatting

### Headers for Chunking

RAG systems chunk documents at headers. Optimize header structure:

```markdown
## Good: Descriptive, Self-Contained Headers

## 16.5 RAG Pipeline Architecture

### 16.5.1 Ingestion Phase
Content that makes sense without reading previous sections...

### 16.5.2 Retrieval Phase
Content with context included...
```

```markdown
## Bad: Context-Dependent Headers

## Architecture

### The Flow
This builds on what we discussed above...

### Next Steps
Continuing from before...
```

### Chunk-Friendly Content

Each section should be **self-contained** enough to be useful in isolation:

✅ **Good:**
```markdown
## 16.3 Database Schema for RAG

The RAG system requires these SQLite tables:

### Artifact Table

The `Artifact` table stores indexed ideas and instructions...

| Column | Type | Description |
|--------|------|-------------|
| Id | TEXT | Primary key UUID |
```

❌ **Bad:**
```markdown
## 16.3 Schema

As mentioned in section 16.1, we need tables.

### Table 1

See above for details.
```

---

## 18.3 Semantic Richness

### Explicit Context

Include relevant context within each section:

```markdown
## 16.7 Embedding Storage

**Context:** The RAG system uses vector embeddings for semantic search.

Embeddings are stored as BLOB columns in SQLite using little-endian 
float32 encoding. Each chunk has exactly one embedding vector.

**Key Decisions:**
- BLOB storage vs. external vector DB: Chose SQLite for simplicity
- Embedding dimensions: 768 (matches LLaMA embedding layer)
```

### Keyword Density

Include relevant keywords that users might search for:

```markdown
## 16.4 Idea to Instruction Promotion

This section covers the **promotion workflow** for converting 
**draft ideas** into **actionable instructions**. The promotion 
process includes **validation**, **linking**, and **re-indexing**.

Keywords: idea promotion, instruction creation, artifact linking, 
workflow automation, idea refinement
```

---

## 18.4 Cross-Reference Standards

### Internal Links

Always use relative paths:

```markdown
## Good: Relative Paths

See [RAG System](./16-rag-system.md) for implementation details.
For path validation, refer to [Path Manager](./17-path-manager.md#17-4-core-methods).
```

### Section Anchors

Use predictable anchor formats:

```markdown
## 16.5 RAG Pipeline Architecture
→ Anchor: #16-5-rag-pipeline-architecture

### 16.5.1 Ingestion Phase  
→ Anchor: #16-5-1-ingestion-phase
```

### Cross-Reference Block

Every document MUST have a cross-references section at the start:

```markdown
**Cross-References:**
- [Database Schema](./02-database-schema.md) - Entity models
- [File Operations](./04-file-operations.md) - Path validation
- [AI Integration](./08-ai-integration.md) - LLM invocation
```

---

## 18.5 Code Block Standards

### Language Tags

Always specify language for syntax highlighting and indexing:

```markdown
\`\`\`go
func Example() {}
\`\`\`

\`\`\`typescript
interface Example {}
\`\`\`

\`\`\`sql
SELECT * FROM Table;
\`\`\`
```

### Code Block Context

Provide context before code blocks:

```markdown
### 16.4.2 Chunk ID Generation

Chunk IDs must be deterministic and stable across re-indexing.
Format: `{fileId}:chunk_{index}`

\`\`\`go
func GenerateChunkId(fileId string, chunkIndex int) string {
    return fmt.Sprintf("%s:chunk_%03d", fileId, chunkIndex)
}
\`\`\`
```

---

## 18.6 Tables for Structured Data

### Data Model Tables

Use consistent table format:

```markdown
| Column | Type | Required | Default | Description |
|--------|------|----------|---------|-------------|
| Id | TEXT | Yes | UUID | Primary key |
| ProjectId | TEXT | Yes | - | Foreign key to Project |
| Status | TEXT | Yes | "draft" | Current status |
```

### API Endpoint Tables

```markdown
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/projects/{id}/ideas` | Create new idea |
| GET | `/api/v1/projects/{id}/ideas` | List ideas |
```

### Error Code Tables

```markdown
| Code | Name | Description |
|------|------|-------------|
| 9001 | ERR_RAG_INDEX_FAILED | Failed to index artifact |
| 9002 | ERR_RAG_EMBED_FAILED | Embedding generation failed |
```

---

## 18.7 Diagrams

### Mermaid for Flows

Use Mermaid for indexable diagrams:

```markdown
### 16.6.1 Ingestion Flow

\`\`\`mermaid
graph TD
    A[File Created] --> B[Read Content]
    B --> C[Split Chunks]
    C --> D[Generate Embeddings]
    D --> E[Store in SQLite]
\`\`\`
```

### ASCII for Architecture

Use ASCII art for architecture that should be chunked as text:

```markdown
### System Overview

\`\`\`
┌─────────────────────────────────────────┐
│            RAG Pipeline                  │
├─────────────────────────────────────────┤
│  Ingestion → Chunking → Embedding       │
│  Retrieval → Ranking → Context Build    │
└─────────────────────────────────────────┘
\`\`\`
```

---

## 18.8 Acceptance Criteria Format

Every spec MUST include acceptance criteria:

```markdown
## 16.15 Acceptance Criteria

### File Storage
- [ ] All paths stored as relative to workDirectory
- [ ] No absolute paths in database
- [ ] Path traversal prevented

### Indexing
- [ ] Chunks created with stable IDs
- [ ] Embeddings stored correctly
- [ ] FTS5 index maintained

### Retrieval
- [ ] Top-K memory context included
- [ ] Semantic search functional
- [ ] Cache invalidation works
```

---

## 18.9 Metadata for Indexing

### Document Frontmatter

Include machine-readable metadata:

```markdown
# RAG System Specification

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-28  
**Tags:** rag, embedding, retrieval, indexing, ai  
**Difficulty:** Advanced  
**Estimated Read Time:** 15 min  
```

### Summary Block

Start with a clear summary for the AI to index:

```markdown
## Overview

This specification defines the Retrieval-Augmented Generation (RAG) 
system for the Spec Management Software. Key capabilities include:

- **Ingestion:** Markdown file watching, chunking, embedding
- **Retrieval:** Semantic search, keyword search, top-K memory
- **Caching:** Query result caching with TTL
- **Storage:** SQLite-based chunk and embedding storage
```

---

## 18.10 Anti-Patterns

### Avoid These Patterns

| Anti-Pattern | Problem | Solution |
|--------------|---------|----------|
| Implicit context | "As mentioned above..." | Repeat essential context |
| Unnamed references | "The table shown earlier" | Use explicit section links |
| Inline-only code | Short snippets without blocks | Use fenced code blocks |
| Generic headers | "Introduction", "Details" | Use numbered, descriptive headers |
| Missing anchors | Headers without stable IDs | Use section numbering |
| Long paragraphs | 10+ sentences in one block | Break into bullets/sections |
| Orphan abbreviations | Using "RAG" without defining | Define on first use |

---

## 18.11 File Naming for RAG

### Spec Files

```
{nn}-{topic-slug}.md
01-overview.md
16-rag-system.md
17-path-manager.md
```

### Idea Files

```
{nn}-idea-{slug}.md
01-idea-authentication-flow.md
02-idea-export-feature.md
```

### Instruction Files

```
{nn}-instruction-{slug}.md
01-instruction-add-logging.md
02-instruction-rate-limiting.md
```

---

## 18.12 Checklist for New Specs

Before committing a new spec file:

- [ ] Follows standard template structure
- [ ] Has version, status, and date in header
- [ ] Includes Overview with summary
- [ ] Has Cross-References section
- [ ] Uses numbered section headers (N.1, N.2)
- [ ] Each section is reasonably self-contained
- [ ] Code blocks have language tags
- [ ] Tables use consistent column format
- [ ] Mermaid diagrams render correctly
- [ ] Acceptance criteria included
- [ ] Links use relative paths
- [ ] File follows naming convention

---

## Related Specs

- [RAG System](./01-rag-system.md)
- [Path Manager](../02-file-management/02-path-manager.md)
- [File Operations](../02-file-management/01-file-operations.md)
- [Consistency Checker](../08-consistency-checker/01-consistency-checker.md)
