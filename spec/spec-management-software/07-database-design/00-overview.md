# Database Design

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-28  

---

## Overview

Database architecture and system design specifications for the Spec Management Software. Uses SQLite as the primary database.

**Cross-References:**
- [Backend Database Schema](./01-schema.md) — Detailed table definitions
- [Coding Guidelines](../04-coding-guidelines/00-overview.md)
- [Error Management](../06-error-management/backend/01-error-codes.md)

---

## Document Index

| # | Document | Description |
|---|----------|-------------|
| 00 | [Overview](./00-overview.md) | This file |
| 01 | [Schema](./01-schema.md) | Complete schema definition |
| 02 | [Migrations](./02-migrations.md) | Migration patterns |
| 03 | [Relationships](./03-relationships.md) | FK constraints, indexes |
| 04 | [Conventions](./04-conventions.md) | Naming and type conventions |

### Diagrams

| # | Diagram | Description |
|---|---------|-------------|
| 01 | [ERD](./diagrams/01-erd.md) | Entity-relationship diagram |
| 02 | [System Architecture](./diagrams/02-system-architecture.md) | Overall system design |

---

## Technology Stack

| Component | Technology |
|-----------|------------|
| Database | SQLite 3 |
| ORM | GORM |
| Migrations | GORM AutoMigrate |
| Vector Search | sqlite-vss |
| Full-Text Search | FTS5 |

---

## Naming Conventions

| Element | Convention | Example |
|---------|------------|---------|
| Table names | PascalCase | `UserSessions`, `SpecFiles` |
| Column names | PascalCase | `CreatedAt`, `UserId` |
| Primary keys | `Id` suffix | `Id`, `UserId` |
| Foreign keys | Referenced table + `Id` | `ProjectId`, `AuthorId` |
| Timestamps | `*At` suffix | `CreatedAt`, `UpdatedAt` |
| Booleans | `Is*` or `Has*` prefix | `IsActive`, `HasAccess` |

---

## Data Types

| Type | SQLite | Go |
|------|--------|-----|
| Primary Key | TEXT (UUID) | `string` |
| String | TEXT | `string` |
| Integer | INTEGER | `int`, `int64` |
| Boolean | INTEGER (0/1) | `bool` |
| Timestamp | TEXT (ISO8601) | `time.Time` |
| JSON | TEXT | `datatypes.JSON` |

---

## Core Entity Groups

### 1. User & Authentication

- `User` — User accounts
- `Session` — Active sessions

### 2. Project & Organization

- `Project` — Specification projects
- `ProjectMetadata` — Extended project info
- `VectorIndexMetadata` — Vector search indexes

### 3. Content & Files

- `File` — Files and folders
- `Snapshot` — Point-in-time snapshots

### 4. AI & Knowledge

- `Artifact` — Ideas and instructions (RAG)
- `Chunk` — Content segments
- `Embedding` — Vector embeddings
- `Instruction` — Refined instructions

---

## Quick Schema Reference

```sql
-- Core tables (simplified, ORM generates these)

CREATE TABLE User (
    Id TEXT PRIMARY KEY,
    Email TEXT UNIQUE NOT NULL,
    PasswordHash TEXT NOT NULL,
    CreatedAt TEXT NOT NULL
);

CREATE TABLE Project (
    Id TEXT PRIMARY KEY,
    Name TEXT NOT NULL,
    OwnerId TEXT REFERENCES User(Id),
    CreatedAt TEXT NOT NULL
);

CREATE TABLE File (
    Id TEXT PRIMARY KEY,
    ProjectId TEXT REFERENCES Project(Id),
    Path TEXT NOT NULL,
    ContentHash TEXT,
    CreatedAt TEXT NOT NULL
);
```

---

## Related Specs

- [Detailed Schema](./01-schema.md)
- [RAG System](../05-features/09-knowledge-memory/01-rag-system.md)
- [Vector Database Plan](../05-features/09-knowledge-memory/04-vector-database-plan.md)
