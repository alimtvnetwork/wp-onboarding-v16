# Diagrams

**Version:** 1.2.0  
**Status:** Active  
**Updated:** 2026-01-30  

---

## Overview

Cross-cutting workflow diagrams and system architecture visualizations.

---

## Document Index

| # | Document | Description |
|---|----------|-------------|
| 00 | [Overview](./00-overview.md) | This file |
| 01 | [System Architecture Overview](./00-system-architecture-overview.md) | Master system diagram |
| 02 | [Idea Promotion Workflow](./01-idea-promotion-workflow.md) | Voice → Idea → Instruction |
| 03 | [RAG Retrieval Flow](./02-rag-retrieval-flow.md) | Query → Context injection |
| 04 | [Instruction Builder Pipeline](./03-instruction-builder-pipeline.md) | Voice → Spec generation |
| 05 | [Prompt Preset Layering](./04-prompt-preset-layering.md) | Base → Override → Final |
| 06 | [Inconsistency Clarification](./05-inconsistency-clarification-workflow.md) | Detect → Question → Regenerate |
| 07 | [Feature Dependency Graph](./06-feature-dependency-graph.md) | Feature relationships & implementation order |
| 08 | [Folder Structure Diagram](./07-folder-structure-diagram.md) | Visual spec folder hierarchy |
| 09 | [Feature Dependency Diagram](./08-feature-dependency-diagram.md) | All 25 features with Mermaid graphs |
| 10 | [Master Architecture Diagram](./07-master-architecture-diagram.md) | Microservices, ports, databases, flows |

---

## Architecture Diagrams

The [Master Architecture Diagram](./07-master-architecture-diagram.md) provides comprehensive views of:

- **System Overview** - All microservices and their relationships
- **Port & Protocol Reference** - Service ports, REST/WebSocket/SSE protocols
- **Database Architecture** - Four-tier SQLite structure
- **Request Flow Diagrams** - Create spec, search, voice, pipeline flows
- **Error Propagation** - Error code routing through Gateway
- **Resilience Patterns** - Circuit breaker, retry, bulkhead isolation
- **Deployment Architecture** - Dev and production layouts
- **Service Dependency Graph** - Build order and dependencies

---

## Related Specs

- [Project Overview](../03-project-overview/00-overview.md)
- [Microservices Overview](../14-microservices/00-overview.md)
- [AI Integration](../05-features/06-ai-integration/00-overview.md)
- [Master Index](../00-master-index.md)
