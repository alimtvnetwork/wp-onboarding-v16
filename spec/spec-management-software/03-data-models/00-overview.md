# Data Models

**Version:** 1.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Overview

Shared TypeScript interfaces used across the Spec Management Software. These interfaces define the contracts between frontend and backend, ensuring type safety and consistency.

---

## Model Index

| # | Model File | Description |
|---|-----------|-------------|
| 01 | [Core Entities](./01-core-entities.md) | User, Project, File, Spec |
| 02 | [AI Types](./02-ai-types.md) | AI requests, responses, models |
| 03 | [Pipeline Types](./03-pipeline-types.md) | Automation pipeline entities |
| 04 | [History Types](./04-history-types.md) | Versions, snapshots, diffs |
| 05 | [Realtime Types](./05-realtime-types.md) | WebSocket, SSE, presence |
| 06 | [RAG Types](./06-rag-types.md) | Embeddings, retrieval, context |

---

## Usage

All models should be imported from the central `@/types` directory:

```typescript
import type { User, Project, File } from '@/types/core';
import type { AIRequest, AIResponse } from '@/types/ai';
import type { Pipeline, Stage, Block } from '@/types/pipeline';
```

---

## Conventions

1. **Naming**: Use PascalCase for types, camelCase for properties
2. **Optional**: Use `?` for optional fields, never `| undefined`
3. **Dates**: Use ISO 8601 strings (`string` type)
4. **IDs**: Use `string` type (UUIDs)
5. **Enums**: Use string literal unions or TypeScript enums
6. **Readonly**: Mark immutable properties with `readonly`

---

## Related Specs

- [API Design](../04-coding-guidelines/00-overview.md)
- [Database Schema](../07-database-design/00-overview.md)
