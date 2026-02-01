# Nexus Flow Error Codes

**Version:** 1.0.0  
**Updated:** 2026-01-29  

---

## Error Code Range

Nexus Flow uses the **8xxx** error code range.

---

## Error Categories

### 80xx — Canvas Errors

| Code | Name | Description |
|------|------|-------------|
| 8001 | CANVAS_INIT_FAILED | React Flow canvas initialization failed |
| 8002 | NODE_CREATE_FAILED | Failed to create workflow node |
| 8003 | EDGE_CREATE_FAILED | Failed to create node connection |
| 8004 | LAYOUT_ERROR | Auto-layout calculation failed |

### 81xx — Workflow Errors

| Code | Name | Description |
|------|------|-------------|
| 8101 | WORKFLOW_INVALID | Workflow definition validation failed |
| 8102 | CYCLE_DETECTED | Circular dependency detected in workflow |
| 8103 | ORPHAN_NODE | Node has no connections |
| 8104 | MISSING_INPUT | Required node input not connected |

### 82xx — Execution Errors

| Code | Name | Description |
|------|------|-------------|
| 8201 | EXEC_START_FAILED | Workflow execution failed to start |
| 8202 | NODE_EXEC_FAILED | Individual node execution failed |
| 8203 | TIMEOUT | Execution timeout exceeded |
| 8204 | ABORT_REQUESTED | Execution aborted by user |

### 83xx — State Errors

| Code | Name | Description |
|------|------|-------------|
| 8301 | STATE_SAVE_FAILED | Failed to persist workflow state |
| 8302 | STATE_LOAD_FAILED | Failed to load workflow state |
| 8303 | STATE_CORRUPT | Workflow state data corrupted |

---

## See Also

- [Core Specification](./01-core-specification.md)
- [Error Management](../spec-management-software/06-error-management/)
