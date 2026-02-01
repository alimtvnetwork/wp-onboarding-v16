# Nexus Flow Reference

> **External Spec:** `spec/nexus-flow/`  
> **Version:** 1.0.0  
> **Error Range:** 8000-8399  
> **Status:** ✅ Extracted

---

## Summary

Visual workflow orchestration engine using React Flow for drag-and-drop pipeline construction. Connects microservices, AI operations, and data transformations into executable workflows.

---

## Full Specification

📁 **Location:** [`spec/nexus-flow/`](../../nexus-flow/00-overview.md)

---

## Specification Files

| File | Description |
|------|-------------|
| `00-overview.md` | Module overview and navigation |
| `00-microservices-context.md` | Microservices ecosystem context |
| `01-core-specification.md` | Core workflow engine specification |
| `02-react-flow-canvas.md` | React Flow canvas implementation |
| `03-standalone-architecture.md` | Standalone deployment architecture |
| `04-openapi-specification.md` | REST API specification |
| `05-error-codes.md` | Error codes (8xxx range) |

---

## Error Code Range

| Range | Category |
|-------|----------|
| 80xx | Canvas errors |
| 81xx | Workflow validation |
| 82xx | Execution errors |
| 83xx | State management |

---

## Core Capabilities

| Feature | Description |
|---------|-------------|
| Visual Canvas | React Flow-based drag-and-drop workflow builder |
| Node Types | AI nodes, data nodes, conditional nodes, loop nodes |
| Execution Engine | Topological execution with dependency resolution |
| State Management | Workflow state persistence and recovery |
| Real-time Updates | WebSocket-based execution status streaming |

---

## Integration Points

### Frontend Integration

```typescript
import { NexusFlowCanvas } from '@/features/nexus-flow';

<NexusFlowCanvas
  workflowId={currentWorkflow.id}
  onSave={handleWorkflowSave}
  onExecute={handleWorkflowExecute}
/>
```

### Backend Integration

```
POST /api/nexus/workflows         — Create workflow
GET  /api/nexus/workflows/:id     — Get workflow
POST /api/nexus/workflows/:id/run — Execute workflow
GET  /api/nexus/executions/:id    — Get execution status
```

---

## Dependencies

- **AI Bridge** — For AI node execution
- **BRun CLI** — For build/task nodes
- **GSearch CLI** — For search-related nodes

---

## See Also

- [Full Specification](../../nexus-flow/00-overview.md)
- [AI Bridge Reference](./02-ai-bridge-reference.md)
- [BRun CLI Reference](./04-brun-reference.md)
- [External Tools Overview](./00-overview.md)
