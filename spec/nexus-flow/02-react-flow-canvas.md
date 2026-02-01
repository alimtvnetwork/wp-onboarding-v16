# 07 - React Flow Canvas Specification

## Overview

The React Flow Canvas provides a visual interface for designing, editing, and monitoring Nexus-Flow pipelines. Built on `@xyflow/react`, it enables drag-and-drop pipeline construction with real-time validation and execution visualization.

---

## Technology Stack

| Layer | Technology | Purpose |
|-------|------------|---------|
| Canvas | `@xyflow/react` v12+ | Node-based graph editor |
| State | Zustand + Immer | Immutable state management |
| Validation | Zod | Schema validation for nodes/edges |
| Persistence | React Query + Debounce | Auto-save with optimistic updates |
| Styling | Tailwind CSS + CSS Variables | Themed node styling |

---

## Directory Structure

```
src/
├── features/
│   └── pipeline-editor/
│       ├── components/
│       │   ├── Canvas.tsx                 # Main ReactFlow wrapper
│       │   ├── CanvasControls.tsx         # Zoom, minimap, controls
│       │   ├── ConnectionLine.tsx         # Custom connection line
│       │   └── ValidationOverlay.tsx      # Error/warning display
│       ├── nodes/
│       │   ├── index.ts                   # Node type registry
│       │   ├── BaseNode.tsx               # Shared node wrapper
│       │   ├── PromptNode.tsx             # LLM prompt block
│       │   ├── SearchNode.tsx             # Scout search block
│       │   ├── CodeGenNode.tsx            # Code generation block
│       │   ├── ValidationNode.tsx         # Script validation block
│       │   ├── TransformNode.tsx          # Data transformation block
│       │   ├── HttpNode.tsx               # HTTP request block
│       │   ├── FileOpNode.tsx             # File operation block
│       │   ├── BranchNode.tsx             # Conditional branching
│       │   ├── LoopNode.tsx               # Loop control block
│       │   ├── StartNode.tsx              # Pipeline entry point
│       │   └── EndNode.tsx                # Pipeline exit point
│       ├── edges/
│       │   ├── index.ts                   # Edge type registry
│       │   ├── DataEdge.tsx               # Standard data flow
│       │   ├── ConditionalEdge.tsx        # Branch condition edge
│       │   └── ErrorEdge.tsx              # Error path edge
│       ├── panels/
│       │   ├── NodePalette.tsx            # Draggable node library
│       │   ├── PropertyPanel.tsx          # Selected node config
│       │   ├── VariableInspector.tsx      # Variable registry view
│       │   └── ExecutionPanel.tsx         # Run status & logs
│       ├── hooks/
│       │   ├── useCanvasState.ts          # Zustand store hook
│       │   ├── useConnectionValidator.ts  # Edge validation logic
│       │   ├── useAutoLayout.ts           # Dagre/ELK auto-layout
│       │   ├── useKeyboardShortcuts.ts    # Hotkey bindings
│       │   ├── usePipelineSync.ts         # Backend persistence
│       │   └── useExecutionState.ts       # WebSocket execution state
│       ├── stores/
│       │   ├── canvasStore.ts             # Main canvas state
│       │   ├── selectionStore.ts          # Multi-select state
│       │   └── executionStore.ts          # Runtime state
│       ├── utils/
│       │   ├── nodeFactory.ts             # Create nodes with defaults
│       │   ├── edgeFactory.ts             # Create edges with validation
│       │   ├── serializer.ts              # Canvas ↔ Pipeline JSON
│       │   ├── validator.ts               # Pipeline validation
│       │   └── layoutEngine.ts            # Auto-layout algorithms
│       ├── types/
│       │   └── canvas.types.ts            # TypeScript definitions
│       └── constants/
│           └── nodeConfig.ts              # Node type configurations
```

---

## Node Type System

### Node Type Enum

```typescript
// src/features/pipeline-editor/types/canvas.types.ts

export enum NodeCategory {
  CONTROL = 'CONTROL',
  AI = 'AI',
  DATA = 'DATA',
  IO = 'IO',
}

export enum PipelineNodeType {
  // Control Flow
  START = 'START',
  END = 'END',
  BRANCH = 'BRANCH',
  LOOP = 'LOOP',
  
  // AI Operations
  PROMPT = 'PROMPT',
  CODEGEN = 'CODEGEN',
  
  // Data Operations
  SEARCH = 'SEARCH',
  TRANSFORM = 'TRANSFORM',
  VALIDATION = 'VALIDATION',
  
  // I/O Operations
  HTTP = 'HTTP',
  FILE_OP = 'FILE_OP',
}

export enum HandleType {
  INPUT = 'INPUT',
  OUTPUT = 'OUTPUT',
  ERROR = 'ERROR',
  CONDITION_TRUE = 'CONDITION_TRUE',
  CONDITION_FALSE = 'CONDITION_FALSE',
  LOOP_BODY = 'LOOP_BODY',
  LOOP_COMPLETE = 'LOOP_COMPLETE',
}

export enum EdgeType {
  DATA = 'DATA',
  CONDITIONAL = 'CONDITIONAL',
  ERROR = 'ERROR',
}

export enum ExecutionStatus {
  IDLE = 'IDLE',
  PENDING = 'PENDING',
  RUNNING = 'RUNNING',
  SUCCESS = 'SUCCESS',
  FAILED = 'FAILED',
  SKIPPED = 'SKIPPED',
  AWAITING_HUMAN = 'AWAITING_HUMAN',
}
```

### Node Configuration Interface

```typescript
// src/features/pipeline-editor/types/canvas.types.ts

export interface HandleConfig {
  readonly id: string;
  readonly type: HandleType;
  readonly position: 'top' | 'bottom' | 'left' | 'right';
  readonly accepts: readonly PipelineNodeType[];  // Which node types can connect
  readonly maxConnections: number;                // -1 for unlimited
  readonly required: boolean;
  readonly label?: string;
}

export interface NodeConfig {
  readonly type: PipelineNodeType;
  readonly category: NodeCategory;
  readonly label: string;
  readonly description: string;
  readonly icon: string;                          // Lucide icon name
  readonly color: string;                         // Tailwind color token
  readonly handles: readonly HandleConfig[];
  readonly defaultData: Record<string, unknown>;
  readonly schema: z.ZodSchema;                   // Validation schema
  readonly minWidth: number;
  readonly minHeight: number;
}

export interface PipelineNode<T = Record<string, unknown>> {
  readonly id: string;
  readonly type: PipelineNodeType;
  readonly position: { readonly x: number; readonly y: number };
  readonly data: T;
  readonly selected: boolean;
  readonly dragging: boolean;
  readonly executionStatus: ExecutionStatus;
  readonly executionError?: string;
  readonly executionOutput?: unknown;
}

export interface PipelineEdge {
  readonly id: string;
  readonly source: string;
  readonly target: string;
  readonly sourceHandle: string;
  readonly targetHandle: string;
  readonly type: EdgeType;
  readonly animated: boolean;
  readonly label?: string;
  readonly data?: {
    readonly condition?: string;  // CEL expression for conditional edges
  };
}
```

### Node Configuration Registry

```typescript
// src/features/pipeline-editor/constants/nodeConfig.ts

import { z } from 'zod';
import { 
  PipelineNodeType, 
  NodeCategory, 
  HandleType,
  type NodeConfig 
} from '../types/canvas.types';

const createHandle = (
  id: string,
  type: HandleType,
  position: 'top' | 'bottom' | 'left' | 'right',
  options: Partial<Omit<HandleConfig, 'id' | 'type' | 'position'>> = {}
): HandleConfig => ({
  id,
  type,
  position,
  accepts: options.accepts ?? Object.values(PipelineNodeType),
  maxConnections: options.maxConnections ?? -1,
  required: options.required ?? false,
  label: options.label,
});

export const NODE_CONFIGS: Readonly<Record<PipelineNodeType, NodeConfig>> = {
  [PipelineNodeType.START]: {
    type: PipelineNodeType.START,
    category: NodeCategory.CONTROL,
    label: 'Start',
    description: 'Pipeline entry point. Receives initial context.',
    icon: 'Play',
    color: 'emerald',
    handles: [
      createHandle('output', HandleType.OUTPUT, 'bottom', { required: true }),
    ],
    defaultData: {
      label: 'Start',
      inputSchema: {},
    },
    schema: z.object({
      label: z.string().min(1).max(50),
      inputSchema: z.record(z.unknown()),
    }),
    minWidth: 120,
    minHeight: 60,
  },

  [PipelineNodeType.END]: {
    type: PipelineNodeType.END,
    category: NodeCategory.CONTROL,
    label: 'End',
    description: 'Pipeline exit point. Collects final output.',
    icon: 'Square',
    color: 'rose',
    handles: [
      createHandle('input', HandleType.INPUT, 'top', { required: true }),
    ],
    defaultData: {
      label: 'End',
      outputMapping: {},
    },
    schema: z.object({
      label: z.string().min(1).max(50),
      outputMapping: z.record(z.string()),
    }),
    minWidth: 120,
    minHeight: 60,
  },

  [PipelineNodeType.BRANCH]: {
    type: PipelineNodeType.BRANCH,
    category: NodeCategory.CONTROL,
    label: 'Branch',
    description: 'Conditional branching using CEL expressions.',
    icon: 'GitBranch',
    color: 'amber',
    handles: [
      createHandle('input', HandleType.INPUT, 'top', { required: true }),
      createHandle('true', HandleType.CONDITION_TRUE, 'bottom', { 
        label: 'True',
        required: true,
      }),
      createHandle('false', HandleType.CONDITION_FALSE, 'bottom', { 
        label: 'False',
        required: true,
      }),
    ],
    defaultData: {
      label: 'Condition',
      condition: '',  // CEL expression
    },
    schema: z.object({
      label: z.string().min(1).max(50),
      condition: z.string().min(1, 'CEL expression required'),
    }),
    minWidth: 160,
    minHeight: 80,
  },

  [PipelineNodeType.LOOP]: {
    type: PipelineNodeType.LOOP,
    category: NodeCategory.CONTROL,
    label: 'Loop',
    description: 'Iterate over collections or repeat until condition.',
    icon: 'Repeat',
    color: 'violet',
    handles: [
      createHandle('input', HandleType.INPUT, 'top', { required: true }),
      createHandle('body', HandleType.LOOP_BODY, 'right', { 
        label: 'Body',
        required: true,
      }),
      createHandle('complete', HandleType.LOOP_COMPLETE, 'bottom', { 
        label: 'Complete',
        required: true,
      }),
    ],
    defaultData: {
      label: 'Loop',
      loopType: 'forEach',  // forEach | while | repeat
      source: '',           // Variable path for forEach
      condition: '',        // CEL for while
      maxIterations: 100,
      concurrency: 1,
    },
    schema: z.object({
      label: z.string().min(1).max(50),
      loopType: z.enum(['forEach', 'while', 'repeat']),
      source: z.string().optional(),
      condition: z.string().optional(),
      maxIterations: z.number().int().min(1).max(10000),
      concurrency: z.number().int().min(1).max(50),
    }),
    minWidth: 180,
    minHeight: 100,
  },

  [PipelineNodeType.PROMPT]: {
    type: PipelineNodeType.PROMPT,
    category: NodeCategory.AI,
    label: 'Prompt',
    description: 'Send prompt to LLM via AI-Bridge.',
    icon: 'MessageSquare',
    color: 'blue',
    handles: [
      createHandle('input', HandleType.INPUT, 'top', { required: true }),
      createHandle('output', HandleType.OUTPUT, 'bottom'),
      createHandle('error', HandleType.ERROR, 'right', { label: 'Error' }),
    ],
    defaultData: {
      label: 'LLM Prompt',
      provider: 'default',
      model: '',
      systemPrompt: '',
      userPrompt: '',
      temperature: 0.7,
      maxTokens: 2048,
      streaming: true,
      outputVariable: 'response',
    },
    schema: z.object({
      label: z.string().min(1).max(50),
      provider: z.string(),
      model: z.string(),
      systemPrompt: z.string(),
      userPrompt: z.string().min(1, 'User prompt required'),
      temperature: z.number().min(0).max(2),
      maxTokens: z.number().int().min(1).max(128000),
      streaming: z.boolean(),
      outputVariable: z.string().regex(/^[a-zA-Z_][a-zA-Z0-9_]*$/),
    }),
    minWidth: 200,
    minHeight: 120,
  },

  [PipelineNodeType.SEARCH]: {
    type: PipelineNodeType.SEARCH,
    category: NodeCategory.DATA,
    label: 'Search',
    description: 'Query Scout for semantic/full-text search.',
    icon: 'Search',
    color: 'cyan',
    handles: [
      createHandle('input', HandleType.INPUT, 'top', { required: true }),
      createHandle('output', HandleType.OUTPUT, 'bottom'),
      createHandle('error', HandleType.ERROR, 'right', { label: 'Error' }),
    ],
    defaultData: {
      label: 'Search',
      query: '',
      searchType: 'hybrid',  // fts | vss | hybrid
      limit: 10,
      threshold: 0.7,
      filters: {},
      outputVariable: 'searchResults',
    },
    schema: z.object({
      label: z.string().min(1).max(50),
      query: z.string().min(1),
      searchType: z.enum(['fts', 'vss', 'hybrid']),
      limit: z.number().int().min(1).max(100),
      threshold: z.number().min(0).max(1),
      filters: z.record(z.unknown()),
      outputVariable: z.string().regex(/^[a-zA-Z_][a-zA-Z0-9_]*$/),
    }),
    minWidth: 180,
    minHeight: 100,
  },

  [PipelineNodeType.CODEGEN]: {
    type: PipelineNodeType.CODEGEN,
    category: NodeCategory.AI,
    label: 'Code Gen',
    description: 'Generate code using specialized prompts.',
    icon: 'Code',
    color: 'indigo',
    handles: [
      createHandle('input', HandleType.INPUT, 'top', { required: true }),
      createHandle('output', HandleType.OUTPUT, 'bottom'),
      createHandle('error', HandleType.ERROR, 'right', { label: 'Error' }),
    ],
    defaultData: {
      label: 'Generate Code',
      language: 'typescript',
      specification: '',
      contextFiles: [],
      outputVariable: 'generatedCode',
    },
    schema: z.object({
      label: z.string().min(1).max(50),
      language: z.enum(['typescript', 'go', 'python', 'rust', 'sql']),
      specification: z.string().min(1),
      contextFiles: z.array(z.string()),
      outputVariable: z.string().regex(/^[a-zA-Z_][a-zA-Z0-9_]*$/),
    }),
    minWidth: 200,
    minHeight: 100,
  },

  [PipelineNodeType.VALIDATION]: {
    type: PipelineNodeType.VALIDATION,
    category: NodeCategory.DATA,
    label: 'Validation',
    description: 'Run validation scripts (Go, Python, TS).',
    icon: 'CheckCircle',
    color: 'green',
    handles: [
      createHandle('input', HandleType.INPUT, 'top', { required: true }),
      createHandle('pass', HandleType.OUTPUT, 'bottom', { label: 'Pass' }),
      createHandle('fail', HandleType.ERROR, 'right', { label: 'Fail' }),
    ],
    defaultData: {
      label: 'Validate',
      runtime: 'typescript',
      script: '',
      timeout: 30,
      outputVariable: 'validationResult',
    },
    schema: z.object({
      label: z.string().min(1).max(50),
      runtime: z.enum(['go', 'python', 'typescript']),
      script: z.string().min(1),
      timeout: z.number().int().min(1).max(300),
      outputVariable: z.string().regex(/^[a-zA-Z_][a-zA-Z0-9_]*$/),
    }),
    minWidth: 180,
    minHeight: 100,
  },

  [PipelineNodeType.TRANSFORM]: {
    type: PipelineNodeType.TRANSFORM,
    category: NodeCategory.DATA,
    label: 'Transform',
    description: 'Transform data using JSONPath or JQ expressions.',
    icon: 'Shuffle',
    color: 'orange',
    handles: [
      createHandle('input', HandleType.INPUT, 'top', { required: true }),
      createHandle('output', HandleType.OUTPUT, 'bottom'),
      createHandle('error', HandleType.ERROR, 'right', { label: 'Error' }),
    ],
    defaultData: {
      label: 'Transform',
      engine: 'jsonpath',  // jsonpath | jq | template
      expression: '',
      outputVariable: 'transformed',
    },
    schema: z.object({
      label: z.string().min(1).max(50),
      engine: z.enum(['jsonpath', 'jq', 'template']),
      expression: z.string().min(1),
      outputVariable: z.string().regex(/^[a-zA-Z_][a-zA-Z0-9_]*$/),
    }),
    minWidth: 180,
    minHeight: 80,
  },

  [PipelineNodeType.HTTP]: {
    type: PipelineNodeType.HTTP,
    category: NodeCategory.IO,
    label: 'HTTP',
    description: 'Make HTTP requests to external services.',
    icon: 'Globe',
    color: 'sky',
    handles: [
      createHandle('input', HandleType.INPUT, 'top', { required: true }),
      createHandle('output', HandleType.OUTPUT, 'bottom'),
      createHandle('error', HandleType.ERROR, 'right', { label: 'Error' }),
    ],
    defaultData: {
      label: 'HTTP Request',
      method: 'GET',
      url: '',
      headers: {},
      body: '',
      timeout: 30,
      retries: 3,
      outputVariable: 'httpResponse',
    },
    schema: z.object({
      label: z.string().min(1).max(50),
      method: z.enum(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
      url: z.string().url(),
      headers: z.record(z.string()),
      body: z.string().optional(),
      timeout: z.number().int().min(1).max(300),
      retries: z.number().int().min(0).max(10),
      outputVariable: z.string().regex(/^[a-zA-Z_][a-zA-Z0-9_]*$/),
    }),
    minWidth: 200,
    minHeight: 100,
  },

  [PipelineNodeType.FILE_OP]: {
    type: PipelineNodeType.FILE_OP,
    category: NodeCategory.IO,
    label: 'File Op',
    description: 'Read, write, or manipulate files.',
    icon: 'File',
    color: 'slate',
    handles: [
      createHandle('input', HandleType.INPUT, 'top', { required: true }),
      createHandle('output', HandleType.OUTPUT, 'bottom'),
      createHandle('error', HandleType.ERROR, 'right', { label: 'Error' }),
    ],
    defaultData: {
      label: 'File Operation',
      operation: 'read',  // read | write | append | delete | copy | move
      path: '',
      content: '',
      encoding: 'utf-8',
      outputVariable: 'fileContent',
    },
    schema: z.object({
      label: z.string().min(1).max(50),
      operation: z.enum(['read', 'write', 'append', 'delete', 'copy', 'move']),
      path: z.string().min(1),
      content: z.string().optional(),
      encoding: z.enum(['utf-8', 'base64', 'binary']),
      outputVariable: z.string().regex(/^[a-zA-Z_][a-zA-Z0-9_]*$/),
    }),
    minWidth: 180,
    minHeight: 100,
  },
} as const;
```

---

## Connection Validation

### Validation Rules

```typescript
// src/features/pipeline-editor/hooks/useConnectionValidator.ts

import { useCallback } from 'react';
import type { Connection } from '@xyflow/react';
import { 
  PipelineNodeType, 
  HandleType, 
  EdgeType,
  type PipelineNode,
  type PipelineEdge,
} from '../types/canvas.types';
import { NODE_CONFIGS } from '../constants/nodeConfig';

export enum ConnectionError {
  SELF_CONNECTION = 'SELF_CONNECTION',
  DUPLICATE_CONNECTION = 'DUPLICATE_CONNECTION',
  INCOMPATIBLE_TYPES = 'INCOMPATIBLE_TYPES',
  MAX_CONNECTIONS_EXCEEDED = 'MAX_CONNECTIONS_EXCEEDED',
  WOULD_CREATE_CYCLE = 'WOULD_CREATE_CYCLE',
  INVALID_HANDLE = 'INVALID_HANDLE',
  MISSING_REQUIRED = 'MISSING_REQUIRED',
}

export interface ValidationResult {
  readonly valid: boolean;
  readonly error?: ConnectionError;
  readonly message?: string;
  readonly suggestedEdgeType?: EdgeType;
}

export function useConnectionValidator(
  nodes: readonly PipelineNode[],
  edges: readonly PipelineEdge[]
) {
  const getNode = useCallback(
    (id: string) => nodes.find(n => n.id === id),
    [nodes]
  );

  const getHandleConfig = useCallback(
    (nodeType: PipelineNodeType, handleId: string) => {
      const config = NODE_CONFIGS[nodeType];
      return config?.handles.find(h => h.id === handleId);
    },
    []
  );

  const countConnections = useCallback(
    (nodeId: string, handleId: string, direction: 'source' | 'target') => {
      return edges.filter(e => 
        direction === 'source' 
          ? e.source === nodeId && e.sourceHandle === handleId
          : e.target === nodeId && e.targetHandle === handleId
      ).length;
    },
    [edges]
  );

  const wouldCreateCycle = useCallback(
    (sourceId: string, targetId: string): boolean => {
      // DFS to detect cycles
      const visited = new Set<string>();
      const stack: string[] = [targetId];

      while (stack.length > 0) {
        const current = stack.pop()!;
        if (current === sourceId) return true;
        if (visited.has(current)) continue;
        visited.add(current);

        // Find all nodes this one connects to
        edges
          .filter(e => e.source === current)
          .forEach(e => stack.push(e.target));
      }

      return false;
    },
    [edges]
  );

  const validateConnection = useCallback(
    (connection: Connection): ValidationResult => {
      const { source, target, sourceHandle, targetHandle } = connection;

      // Rule 1: No self-connections
      if (source === target) {
        return {
          valid: false,
          error: ConnectionError.SELF_CONNECTION,
          message: 'Cannot connect a node to itself',
        };
      }

      // Rule 2: Check duplicate connections
      const isDuplicate = edges.some(
        e => e.source === source && 
             e.target === target && 
             e.sourceHandle === sourceHandle &&
             e.targetHandle === targetHandle
      );
      if (isDuplicate) {
        return {
          valid: false,
          error: ConnectionError.DUPLICATE_CONNECTION,
          message: 'Connection already exists',
        };
      }

      const sourceNode = getNode(source!);
      const targetNode = getNode(target!);

      if (!sourceNode || !targetNode) {
        return {
          valid: false,
          error: ConnectionError.INVALID_HANDLE,
          message: 'Invalid source or target node',
        };
      }

      const sourceHandleConfig = getHandleConfig(sourceNode.type, sourceHandle!);
      const targetHandleConfig = getHandleConfig(targetNode.type, targetHandle!);

      if (!sourceHandleConfig || !targetHandleConfig) {
        return {
          valid: false,
          error: ConnectionError.INVALID_HANDLE,
          message: 'Invalid handle configuration',
        };
      }

      // Rule 3: Check type compatibility
      if (!targetHandleConfig.accepts.includes(sourceNode.type)) {
        return {
          valid: false,
          error: ConnectionError.INCOMPATIBLE_TYPES,
          message: `${sourceNode.type} cannot connect to this input`,
        };
      }

      // Rule 4: Check max connections
      const currentConnections = countConnections(
        target!,
        targetHandle!,
        'target'
      );
      if (
        targetHandleConfig.maxConnections !== -1 &&
        currentConnections >= targetHandleConfig.maxConnections
      ) {
        return {
          valid: false,
          error: ConnectionError.MAX_CONNECTIONS_EXCEEDED,
          message: `Maximum ${targetHandleConfig.maxConnections} connection(s) allowed`,
        };
      }

      // Rule 5: Cycle detection
      if (wouldCreateCycle(source!, target!)) {
        return {
          valid: false,
          error: ConnectionError.WOULD_CREATE_CYCLE,
          message: 'Connection would create a cycle',
        };
      }

      // Determine edge type based on source handle
      let suggestedEdgeType = EdgeType.DATA;
      if (
        sourceHandleConfig.type === HandleType.CONDITION_TRUE ||
        sourceHandleConfig.type === HandleType.CONDITION_FALSE
      ) {
        suggestedEdgeType = EdgeType.CONDITIONAL;
      } else if (sourceHandleConfig.type === HandleType.ERROR) {
        suggestedEdgeType = EdgeType.ERROR;
      }

      return {
        valid: true,
        suggestedEdgeType,
      };
    },
    [edges, getNode, getHandleConfig, countConnections, wouldCreateCycle]
  );

  const validatePipeline = useCallback((): ValidationResult[] => {
    const errors: ValidationResult[] = [];

    // Check for required handles without connections
    nodes.forEach(node => {
      const config = NODE_CONFIGS[node.type];
      config.handles.forEach(handle => {
        if (!handle.required) return;

        const hasConnection = edges.some(e =>
          handle.type === HandleType.INPUT || 
          handle.type === HandleType.LOOP_BODY
            ? e.target === node.id && e.targetHandle === handle.id
            : e.source === node.id && e.sourceHandle === handle.id
        );

        if (!hasConnection) {
          errors.push({
            valid: false,
            error: ConnectionError.MISSING_REQUIRED,
            message: `${node.data.label}: Required handle "${handle.label || handle.id}" is not connected`,
          });
        }
      });
    });

    // Check for exactly one START node
    const startNodes = nodes.filter(n => n.type === PipelineNodeType.START);
    if (startNodes.length === 0) {
      errors.push({
        valid: false,
        error: ConnectionError.MISSING_REQUIRED,
        message: 'Pipeline must have a Start node',
      });
    } else if (startNodes.length > 1) {
      errors.push({
        valid: false,
        error: ConnectionError.INCOMPATIBLE_TYPES,
        message: 'Pipeline can only have one Start node',
      });
    }

    // Check for at least one END node
    const endNodes = nodes.filter(n => n.type === PipelineNodeType.END);
    if (endNodes.length === 0) {
      errors.push({
        valid: false,
        error: ConnectionError.MISSING_REQUIRED,
        message: 'Pipeline must have at least one End node',
      });
    }

    return errors;
  },
  [nodes, edges]);

  return {
    validateConnection,
    validatePipeline,
  };
}
```

---

## State Management

### Canvas Store (Zustand + Immer)

```typescript
// src/features/pipeline-editor/stores/canvasStore.ts

import { create } from 'zustand';
import { immer } from 'zustand/middleware/immer';
import { devtools, persist } from 'zustand/middleware';
import type { 
  PipelineNode, 
  PipelineEdge, 
  PipelineNodeType,
  ExecutionStatus,
} from '../types/canvas.types';

export interface CanvasState {
  // Pipeline metadata
  readonly pipelineId: string | null;
  readonly pipelineName: string;
  readonly isDirty: boolean;
  readonly lastSaved: Date | null;

  // Graph data
  readonly nodes: readonly PipelineNode[];
  readonly edges: readonly PipelineEdge[];

  // Viewport
  readonly viewport: {
    readonly x: number;
    readonly y: number;
    readonly zoom: number;
  };

  // UI State
  readonly isPanelOpen: boolean;
  readonly activePanelTab: 'properties' | 'variables' | 'execution';
}

export interface CanvasActions {
  // Pipeline operations
  loadPipeline: (id: string, name: string, nodes: PipelineNode[], edges: PipelineEdge[]) => void;
  clearPipeline: () => void;
  setPipelineName: (name: string) => void;
  markDirty: () => void;
  markSaved: () => void;

  // Node operations
  addNode: (type: PipelineNodeType, position: { x: number; y: number }) => string;
  updateNode: (id: string, data: Partial<PipelineNode['data']>) => void;
  updateNodePosition: (id: string, position: { x: number; y: number }) => void;
  removeNode: (id: string) => void;
  duplicateNode: (id: string) => string;

  // Edge operations
  addEdge: (edge: Omit<PipelineEdge, 'id'>) => string;
  updateEdge: (id: string, data: Partial<PipelineEdge>) => void;
  removeEdge: (id: string) => void;

  // Batch operations
  removeSelected: () => void;
  updateNodePositions: (updates: Array<{ id: string; position: { x: number; y: number } }>) => void;

  // Execution state
  setNodeExecutionStatus: (id: string, status: ExecutionStatus, error?: string, output?: unknown) => void;
  resetExecutionState: () => void;

  // Viewport
  setViewport: (viewport: { x: number; y: number; zoom: number }) => void;

  // UI
  togglePanel: () => void;
  setActivePanelTab: (tab: 'properties' | 'variables' | 'execution') => void;
}

const generateId = () => `node_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`;

const initialState: CanvasState = {
  pipelineId: null,
  pipelineName: 'Untitled Pipeline',
  isDirty: false,
  lastSaved: null,
  nodes: [],
  edges: [],
  viewport: { x: 0, y: 0, zoom: 1 },
  isPanelOpen: true,
  activePanelTab: 'properties',
};

export const useCanvasStore = create<CanvasState & CanvasActions>()(
  devtools(
    persist(
      immer((set, get) => ({
        ...initialState,

        loadPipeline: (id, name, nodes, edges) => {
          set(state => {
            state.pipelineId = id;
            state.pipelineName = name;
            state.nodes = nodes;
            state.edges = edges;
            state.isDirty = false;
            state.lastSaved = new Date();
          });
        },

        clearPipeline: () => {
          set(state => {
            Object.assign(state, initialState);
          });
        },

        setPipelineName: (name) => {
          set(state => {
            state.pipelineName = name;
            state.isDirty = true;
          });
        },

        markDirty: () => {
          set(state => {
            state.isDirty = true;
          });
        },

        markSaved: () => {
          set(state => {
            state.isDirty = false;
            state.lastSaved = new Date();
          });
        },

        addNode: (type, position) => {
          const id = generateId();
          set(state => {
            const config = NODE_CONFIGS[type];
            const newNode: PipelineNode = {
              id,
              type,
              position,
              data: { ...config.defaultData },
              selected: false,
              dragging: false,
              executionStatus: ExecutionStatus.IDLE,
            };
            state.nodes.push(newNode);
            state.isDirty = true;
          });
          return id;
        },

        updateNode: (id, data) => {
          set(state => {
            const node = state.nodes.find(n => n.id === id);
            if (node) {
              Object.assign(node.data, data);
              state.isDirty = true;
            }
          });
        },

        updateNodePosition: (id, position) => {
          set(state => {
            const node = state.nodes.find(n => n.id === id);
            if (node) {
              node.position = position;
              state.isDirty = true;
            }
          });
        },

        removeNode: (id) => {
          set(state => {
            state.nodes = state.nodes.filter(n => n.id !== id);
            state.edges = state.edges.filter(
              e => e.source !== id && e.target !== id
            );
            state.isDirty = true;
          });
        },

        duplicateNode: (id) => {
          const node = get().nodes.find(n => n.id === id);
          if (!node) return '';
          
          const newId = generateId();
          set(state => {
            const newNode: PipelineNode = {
              ...node,
              id: newId,
              position: {
                x: node.position.x + 50,
                y: node.position.y + 50,
              },
              selected: false,
              executionStatus: ExecutionStatus.IDLE,
            };
            state.nodes.push(newNode);
            state.isDirty = true;
          });
          return newId;
        },

        addEdge: (edge) => {
          const id = `edge_${Date.now()}`;
          set(state => {
            state.edges.push({ ...edge, id });
            state.isDirty = true;
          });
          return id;
        },

        updateEdge: (id, data) => {
          set(state => {
            const edge = state.edges.find(e => e.id === id);
            if (edge) {
              Object.assign(edge, data);
              state.isDirty = true;
            }
          });
        },

        removeEdge: (id) => {
          set(state => {
            state.edges = state.edges.filter(e => e.id !== id);
            state.isDirty = true;
          });
        },

        removeSelected: () => {
          set(state => {
            const selectedIds = new Set(
              state.nodes.filter(n => n.selected).map(n => n.id)
            );
            state.nodes = state.nodes.filter(n => !n.selected);
            state.edges = state.edges.filter(
              e => !selectedIds.has(e.source) && !selectedIds.has(e.target)
            );
            state.isDirty = true;
          });
        },

        updateNodePositions: (updates) => {
          set(state => {
            updates.forEach(({ id, position }) => {
              const node = state.nodes.find(n => n.id === id);
              if (node) {
                node.position = position;
              }
            });
            state.isDirty = true;
          });
        },

        setNodeExecutionStatus: (id, status, error, output) => {
          set(state => {
            const node = state.nodes.find(n => n.id === id);
            if (node) {
              node.executionStatus = status;
              node.executionError = error;
              node.executionOutput = output;
            }
          });
        },

        resetExecutionState: () => {
          set(state => {
            state.nodes.forEach(node => {
              node.executionStatus = ExecutionStatus.IDLE;
              node.executionError = undefined;
              node.executionOutput = undefined;
            });
          });
        },

        setViewport: (viewport) => {
          set(state => {
            state.viewport = viewport;
          });
        },

        togglePanel: () => {
          set(state => {
            state.isPanelOpen = !state.isPanelOpen;
          });
        },

        setActivePanelTab: (tab) => {
          set(state => {
            state.activePanelTab = tab;
          });
        },
      })),
      {
        name: 'pipeline-canvas',
        partialize: (state) => ({
          viewport: state.viewport,
          isPanelOpen: state.isPanelOpen,
          activePanelTab: state.activePanelTab,
        }),
      }
    ),
    { name: 'CanvasStore' }
  )
);
```

### Selection Store

```typescript
// src/features/pipeline-editor/stores/selectionStore.ts

import { create } from 'zustand';
import { immer } from 'zustand/middleware/immer';

export interface SelectionState {
  readonly selectedNodeIds: ReadonlySet<string>;
  readonly selectedEdgeIds: ReadonlySet<string>;
  readonly selectionBox: {
    readonly start: { x: number; y: number };
    readonly end: { x: number; y: number };
  } | null;
}

export interface SelectionActions {
  selectNode: (id: string, additive?: boolean) => void;
  selectEdge: (id: string, additive?: boolean) => void;
  selectNodes: (ids: string[]) => void;
  selectAll: (nodeIds: string[], edgeIds: string[]) => void;
  deselectAll: () => void;
  toggleNodeSelection: (id: string) => void;
  setSelectionBox: (box: SelectionState['selectionBox']) => void;
}

export const useSelectionStore = create<SelectionState & SelectionActions>()(
  immer((set) => ({
    selectedNodeIds: new Set(),
    selectedEdgeIds: new Set(),
    selectionBox: null,

    selectNode: (id, additive = false) => {
      set(state => {
        if (!additive) {
          state.selectedNodeIds = new Set([id]);
          state.selectedEdgeIds = new Set();
        } else {
          state.selectedNodeIds = new Set([...state.selectedNodeIds, id]);
        }
      });
    },

    selectEdge: (id, additive = false) => {
      set(state => {
        if (!additive) {
          state.selectedNodeIds = new Set();
          state.selectedEdgeIds = new Set([id]);
        } else {
          state.selectedEdgeIds = new Set([...state.selectedEdgeIds, id]);
        }
      });
    },

    selectNodes: (ids) => {
      set(state => {
        state.selectedNodeIds = new Set(ids);
      });
    },

    selectAll: (nodeIds, edgeIds) => {
      set(state => {
        state.selectedNodeIds = new Set(nodeIds);
        state.selectedEdgeIds = new Set(edgeIds);
      });
    },

    deselectAll: () => {
      set(state => {
        state.selectedNodeIds = new Set();
        state.selectedEdgeIds = new Set();
        state.selectionBox = null;
      });
    },

    toggleNodeSelection: (id) => {
      set(state => {
        const newSet = new Set(state.selectedNodeIds);
        if (newSet.has(id)) {
          newSet.delete(id);
        } else {
          newSet.add(id);
        }
        state.selectedNodeIds = newSet;
      });
    },

    setSelectionBox: (box) => {
      set(state => {
        state.selectionBox = box;
      });
    },
  }))
);
```

### Execution Store (WebSocket Integration)

```typescript
// src/features/pipeline-editor/stores/executionStore.ts

import { create } from 'zustand';
import { immer } from 'zustand/middleware/immer';

export enum PipelineExecutionStatus {
  IDLE = 'IDLE',
  CONNECTING = 'CONNECTING',
  RUNNING = 'RUNNING',
  PAUSED = 'PAUSED',
  COMPLETED = 'COMPLETED',
  FAILED = 'FAILED',
  CANCELLED = 'CANCELLED',
}

export interface ExecutionLogEntry {
  readonly timestamp: Date;
  readonly level: 'info' | 'warn' | 'error' | 'debug';
  readonly nodeId?: string;
  readonly message: string;
  readonly data?: unknown;
}

export interface ExecutionState {
  readonly status: PipelineExecutionStatus;
  readonly sessionId: string | null;
  readonly startedAt: Date | null;
  readonly completedAt: Date | null;
  readonly currentNodeId: string | null;
  readonly progress: {
    readonly completed: number;
    readonly total: number;
  };
  readonly logs: readonly ExecutionLogEntry[];
  readonly variables: Readonly<Record<string, unknown>>;
  readonly error: string | null;
}

export interface ExecutionActions {
  startExecution: (sessionId: string, totalNodes: number) => void;
  updateProgress: (nodeId: string, completed: number) => void;
  setVariables: (variables: Record<string, unknown>) => void;
  addLog: (entry: Omit<ExecutionLogEntry, 'timestamp'>) => void;
  pauseExecution: () => void;
  resumeExecution: () => void;
  completeExecution: () => void;
  failExecution: (error: string) => void;
  cancelExecution: () => void;
  resetExecution: () => void;
}

const MAX_LOG_ENTRIES = 1000;

export const useExecutionStore = create<ExecutionState & ExecutionActions>()(
  immer((set) => ({
    status: PipelineExecutionStatus.IDLE,
    sessionId: null,
    startedAt: null,
    completedAt: null,
    currentNodeId: null,
    progress: { completed: 0, total: 0 },
    logs: [],
    variables: {},
    error: null,

    startExecution: (sessionId, totalNodes) => {
      set(state => {
        state.status = PipelineExecutionStatus.RUNNING;
        state.sessionId = sessionId;
        state.startedAt = new Date();
        state.completedAt = null;
        state.progress = { completed: 0, total: totalNodes };
        state.logs = [];
        state.variables = {};
        state.error = null;
      });
    },

    updateProgress: (nodeId, completed) => {
      set(state => {
        state.currentNodeId = nodeId;
        state.progress.completed = completed;
      });
    },

    setVariables: (variables) => {
      set(state => {
        state.variables = variables;
      });
    },

    addLog: (entry) => {
      set(state => {
        const newEntry: ExecutionLogEntry = {
          ...entry,
          timestamp: new Date(),
        };
        
        // Maintain max log entries
        if (state.logs.length >= MAX_LOG_ENTRIES) {
          state.logs = [...state.logs.slice(-MAX_LOG_ENTRIES + 1), newEntry];
        } else {
          state.logs.push(newEntry);
        }
      });
    },

    pauseExecution: () => {
      set(state => {
        if (state.status === PipelineExecutionStatus.RUNNING) {
          state.status = PipelineExecutionStatus.PAUSED;
        }
      });
    },

    resumeExecution: () => {
      set(state => {
        if (state.status === PipelineExecutionStatus.PAUSED) {
          state.status = PipelineExecutionStatus.RUNNING;
        }
      });
    },

    completeExecution: () => {
      set(state => {
        state.status = PipelineExecutionStatus.COMPLETED;
        state.completedAt = new Date();
        state.currentNodeId = null;
      });
    },

    failExecution: (error) => {
      set(state => {
        state.status = PipelineExecutionStatus.FAILED;
        state.completedAt = new Date();
        state.error = error;
      });
    },

    cancelExecution: () => {
      set(state => {
        state.status = PipelineExecutionStatus.CANCELLED;
        state.completedAt = new Date();
      });
    },

    resetExecution: () => {
      set(state => {
        state.status = PipelineExecutionStatus.IDLE;
        state.sessionId = null;
        state.startedAt = null;
        state.completedAt = null;
        state.currentNodeId = null;
        state.progress = { completed: 0, total: 0 };
        state.logs = [];
        state.variables = {};
        state.error = null;
      });
    },
  }))
);
```

---

## Keyboard Shortcuts

```typescript
// src/features/pipeline-editor/hooks/useKeyboardShortcuts.ts

import { useEffect, useCallback } from 'react';
import { useCanvasStore } from '../stores/canvasStore';
import { useSelectionStore } from '../stores/selectionStore';

export interface ShortcutConfig {
  readonly key: string;
  readonly ctrl?: boolean;
  readonly shift?: boolean;
  readonly alt?: boolean;
  readonly action: () => void;
  readonly description: string;
}

export function useKeyboardShortcuts() {
  const { removeSelected, duplicateNode } = useCanvasStore();
  const { selectedNodeIds, deselectAll, selectAll } = useSelectionStore();
  const nodes = useCanvasStore(s => s.nodes);
  const edges = useCanvasStore(s => s.edges);

  const shortcuts: readonly ShortcutConfig[] = [
    {
      key: 'Delete',
      action: removeSelected,
      description: 'Delete selected nodes',
    },
    {
      key: 'Backspace',
      action: removeSelected,
      description: 'Delete selected nodes',
    },
    {
      key: 'Escape',
      action: deselectAll,
      description: 'Deselect all',
    },
    {
      key: 'a',
      ctrl: true,
      action: () => selectAll(
        nodes.map(n => n.id),
        edges.map(e => e.id)
      ),
      description: 'Select all',
    },
    {
      key: 'd',
      ctrl: true,
      action: () => {
        const firstSelected = [...selectedNodeIds][0];
        if (firstSelected) {
          duplicateNode(firstSelected);
        }
      },
      description: 'Duplicate selected node',
    },
  ];

  const handleKeyDown = useCallback(
    (event: KeyboardEvent) => {
      // Ignore if typing in input/textarea
      const target = event.target as HTMLElement;
      if (
        target.tagName === 'INPUT' ||
        target.tagName === 'TEXTAREA' ||
        target.isContentEditable
      ) {
        return;
      }

      for (const shortcut of shortcuts) {
        const ctrlMatch = shortcut.ctrl ? event.ctrlKey || event.metaKey : !event.ctrlKey && !event.metaKey;
        const shiftMatch = shortcut.shift ? event.shiftKey : !event.shiftKey;
        const altMatch = shortcut.alt ? event.altKey : !event.altKey;
        const keyMatch = event.key === shortcut.key;

        if (ctrlMatch && shiftMatch && altMatch && keyMatch) {
          event.preventDefault();
          shortcut.action();
          return;
        }
      }
    },
    [shortcuts]
  );

  useEffect(() => {
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [handleKeyDown]);

  return shortcuts;
}
```

---

## Auto-Layout Engine

```typescript
// src/features/pipeline-editor/utils/layoutEngine.ts

import type { PipelineNode, PipelineEdge } from '../types/canvas.types';

export interface LayoutOptions {
  readonly direction: 'TB' | 'LR';  // Top-Bottom or Left-Right
  readonly nodeSpacing: number;
  readonly rankSpacing: number;
  readonly align: 'UL' | 'UR' | 'DL' | 'DR';  // Up-Left, Up-Right, etc.
}

const DEFAULT_OPTIONS: LayoutOptions = {
  direction: 'TB',
  nodeSpacing: 80,
  rankSpacing: 120,
  align: 'UL',
};

interface LayoutNode {
  id: string;
  width: number;
  height: number;
  x: number;
  y: number;
  rank: number;
  order: number;
}

/**
 * Simple layered layout algorithm (Sugiyama-style)
 * For production, consider using dagre or elkjs
 */
export function calculateLayout(
  nodes: readonly PipelineNode[],
  edges: readonly PipelineEdge[],
  options: Partial<LayoutOptions> = {}
): Map<string, { x: number; y: number }> {
  const opts = { ...DEFAULT_OPTIONS, ...options };
  const positions = new Map<string, { x: number; y: number }>();

  if (nodes.length === 0) return positions;

  // Build adjacency list
  const adjacency = new Map<string, string[]>();
  const inDegree = new Map<string, number>();

  nodes.forEach(n => {
    adjacency.set(n.id, []);
    inDegree.set(n.id, 0);
  });

  edges.forEach(e => {
    adjacency.get(e.source)?.push(e.target);
    inDegree.set(e.target, (inDegree.get(e.target) || 0) + 1);
  });

  // Topological sort to assign ranks
  const ranks = new Map<string, number>();
  const queue: string[] = [];

  inDegree.forEach((degree, id) => {
    if (degree === 0) queue.push(id);
  });

  let rank = 0;
  while (queue.length > 0) {
    const levelSize = queue.length;
    const currentLevel: string[] = [];

    for (let i = 0; i < levelSize; i++) {
      const nodeId = queue.shift()!;
      currentLevel.push(nodeId);
      ranks.set(nodeId, rank);

      adjacency.get(nodeId)?.forEach(neighbor => {
        const newDegree = (inDegree.get(neighbor) || 0) - 1;
        inDegree.set(neighbor, newDegree);
        if (newDegree === 0) queue.push(neighbor);
      });
    }

    rank++;
  }

  // Group nodes by rank
  const rankGroups = new Map<number, string[]>();
  ranks.forEach((r, id) => {
    if (!rankGroups.has(r)) rankGroups.set(r, []);
    rankGroups.get(r)!.push(id);
  });

  // Calculate positions
  const isHorizontal = opts.direction === 'LR';

  rankGroups.forEach((nodeIds, r) => {
    nodeIds.forEach((id, order) => {
      const node = nodes.find(n => n.id === id);
      if (!node) return;

      const primaryPos = r * opts.rankSpacing;
      const secondaryPos = order * opts.nodeSpacing;

      positions.set(id, {
        x: isHorizontal ? primaryPos : secondaryPos,
        y: isHorizontal ? secondaryPos : primaryPos,
      });
    });
  });

  return positions;
}
```

---

## Serialization

```typescript
// src/features/pipeline-editor/utils/serializer.ts

import type { 
  PipelineNode, 
  PipelineEdge,
  PipelineNodeType,
  EdgeType,
} from '../types/canvas.types';

/**
 * Backend pipeline JSON format (Nexus-Flow compatible)
 */
export interface PipelineJSON {
  readonly id: string;
  readonly name: string;
  readonly version: string;
  readonly stages: readonly StageJSON[];
  readonly connections: readonly ConnectionJSON[];
  readonly metadata: {
    readonly createdAt: string;
    readonly updatedAt: string;
    readonly canvasViewport?: {
      readonly x: number;
      readonly y: number;
      readonly zoom: number;
    };
  };
}

export interface StageJSON {
  readonly id: string;
  readonly type: string;
  readonly config: Record<string, unknown>;
  readonly position?: { x: number; y: number };
}

export interface ConnectionJSON {
  readonly id: string;
  readonly from: { stage: string; output: string };
  readonly to: { stage: string; input: string };
  readonly condition?: string;
}

/**
 * Convert canvas state to Nexus-Flow pipeline JSON
 */
export function serializePipeline(
  pipelineId: string,
  pipelineName: string,
  nodes: readonly PipelineNode[],
  edges: readonly PipelineEdge[],
  viewport?: { x: number; y: number; zoom: number }
): PipelineJSON {
  const stages: StageJSON[] = nodes.map(node => ({
    id: node.id,
    type: node.type.toLowerCase(),
    config: node.data as Record<string, unknown>,
    position: node.position,
  }));

  const connections: ConnectionJSON[] = edges.map(edge => ({
    id: edge.id,
    from: { stage: edge.source, output: edge.sourceHandle },
    to: { stage: edge.target, input: edge.targetHandle },
    condition: edge.data?.condition,
  }));

  return {
    id: pipelineId,
    name: pipelineName,
    version: '1.0.0',
    stages,
    connections,
    metadata: {
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
      canvasViewport: viewport,
    },
  };
}

/**
 * Convert Nexus-Flow pipeline JSON to canvas state
 */
export function deserializePipeline(
  json: PipelineJSON
): {
  nodes: PipelineNode[];
  edges: PipelineEdge[];
  viewport?: { x: number; y: number; zoom: number };
} {
  const nodes: PipelineNode[] = json.stages.map(stage => ({
    id: stage.id,
    type: stage.type.toUpperCase() as PipelineNodeType,
    position: stage.position || { x: 0, y: 0 },
    data: stage.config,
    selected: false,
    dragging: false,
    executionStatus: 'IDLE' as const,
  }));

  const edges: PipelineEdge[] = json.connections.map(conn => ({
    id: conn.id,
    source: conn.from.stage,
    target: conn.to.stage,
    sourceHandle: conn.from.output,
    targetHandle: conn.to.input,
    type: conn.condition ? 'CONDITIONAL' as EdgeType : 'DATA' as EdgeType,
    animated: false,
    data: conn.condition ? { condition: conn.condition } : undefined,
  }));

  return {
    nodes,
    edges,
    viewport: json.metadata.canvasViewport,
  };
}
```

---

## Component Patterns

### BaseNode Pattern

```typescript
// src/features/pipeline-editor/nodes/BaseNode.tsx

import { memo, type ReactNode } from 'react';
import { Handle, Position, type NodeProps } from '@xyflow/react';
import { cn } from '@/lib/utils';
import { NODE_CONFIGS } from '../constants/nodeConfig';
import { 
  type PipelineNode,
  ExecutionStatus,
  HandleType,
} from '../types/canvas.types';
import * as Icons from 'lucide-react';

interface BaseNodeProps extends NodeProps<PipelineNode> {
  children?: ReactNode;
}

const statusStyles: Record<ExecutionStatus, string> = {
  [ExecutionStatus.IDLE]: 'border-border',
  [ExecutionStatus.PENDING]: 'border-muted-foreground',
  [ExecutionStatus.RUNNING]: 'border-primary animate-pulse',
  [ExecutionStatus.SUCCESS]: 'border-green-500',
  [ExecutionStatus.FAILED]: 'border-destructive',
  [ExecutionStatus.SKIPPED]: 'border-muted',
  [ExecutionStatus.AWAITING_HUMAN]: 'border-amber-500 animate-pulse',
};

export const BaseNode = memo(function BaseNode({
  data,
  selected,
  type,
  children,
}: BaseNodeProps) {
  const config = NODE_CONFIGS[type];
  const IconComponent = Icons[config.icon as keyof typeof Icons] as React.ComponentType<{ className?: string }>;

  return (
    <div
      className={cn(
        'relative rounded-lg border-2 bg-card shadow-md transition-all',
        'min-w-[120px]',
        statusStyles[data.executionStatus],
        selected && 'ring-2 ring-primary ring-offset-2',
      )}
      style={{ minWidth: config.minWidth, minHeight: config.minHeight }}
    >
      {/* Handles */}
      {config.handles.map(handle => {
        const positionMap = {
          top: Position.Top,
          bottom: Position.Bottom,
          left: Position.Left,
          right: Position.Right,
        };

        const isInput = 
          handle.type === HandleType.INPUT ||
          handle.type === HandleType.LOOP_BODY;

        return (
          <Handle
            key={handle.id}
            id={handle.id}
            type={isInput ? 'target' : 'source'}
            position={positionMap[handle.position]}
            className={cn(
              'h-3 w-3 rounded-full border-2 border-background',
              handle.type === HandleType.ERROR 
                ? 'bg-destructive' 
                : handle.type === HandleType.CONDITION_TRUE
                ? 'bg-green-500'
                : handle.type === HandleType.CONDITION_FALSE
                ? 'bg-red-500'
                : 'bg-primary'
            )}
          />
        );
      })}

      {/* Header */}
      <div
        className={cn(
          'flex items-center gap-2 rounded-t-md px-3 py-2',
          `bg-${config.color}-500/10`
        )}
      >
        {IconComponent && (
          <IconComponent className={cn('h-4 w-4', `text-${config.color}-500`)} />
        )}
        <span className="text-sm font-medium truncate">
          {data.label as string || config.label}
        </span>
      </div>

      {/* Content */}
      <div className="p-3">
        {children}
      </div>

      {/* Execution status indicator */}
      {data.executionStatus === ExecutionStatus.RUNNING && (
        <div className="absolute -top-1 -right-1 h-3 w-3 rounded-full bg-primary animate-ping" />
      )}
      
      {data.executionStatus === ExecutionStatus.FAILED && data.executionError && (
        <div className="absolute -bottom-6 left-0 right-0 text-xs text-destructive truncate">
          {data.executionError}
        </div>
      )}
    </div>
  );
});
```

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/pipelines` | List all pipelines |
| `GET` | `/api/pipelines/:id` | Get pipeline by ID |
| `POST` | `/api/pipelines` | Create new pipeline |
| `PUT` | `/api/pipelines/:id` | Update pipeline |
| `DELETE` | `/api/pipelines/:id` | Delete pipeline |
| `POST` | `/api/pipelines/:id/validate` | Validate pipeline configuration |
| `POST` | `/api/pipelines/:id/export` | Export pipeline as JSON/YAML |
| `POST` | `/api/pipelines/import` | Import pipeline from JSON/YAML |

---

## WebSocket Events (Execution Monitoring)

| Event | Direction | Payload |
|-------|-----------|---------|
| `execution:start` | Server → Client | `{ sessionId, pipelineId, totalStages }` |
| `execution:stage:start` | Server → Client | `{ stageId, stageName }` |
| `execution:stage:progress` | Server → Client | `{ stageId, progress, message }` |
| `execution:stage:complete` | Server → Client | `{ stageId, output, duration }` |
| `execution:stage:error` | Server → Client | `{ stageId, error, stack }` |
| `execution:variables` | Server → Client | `{ variables: Record<string, unknown> }` |
| `execution:complete` | Server → Client | `{ success, output, duration }` |
| `execution:cancel` | Client → Server | `{ sessionId }` |
| `execution:pause` | Client → Server | `{ sessionId }` |
| `execution:resume` | Client → Server | `{ sessionId }` |

---

## Performance Considerations

1. **Virtualization**: Render only visible nodes for pipelines with 100+ nodes
2. **Debounced Updates**: Auto-save with 500ms debounce
3. **Memoization**: All node components wrapped with `memo()`
4. **Selective Re-renders**: Use Zustand selectors to prevent unnecessary updates
5. **Edge Bundling**: Collapse parallel edges for cleaner visualization
6. **Lazy Loading**: Property panels loaded on-demand

---

## Accessibility

1. **Keyboard Navigation**: Arrow keys to move between nodes, Tab to cycle handles
2. **Screen Reader**: ARIA labels on all interactive elements
3. **Focus Management**: Visible focus rings, logical tab order
4. **Color Contrast**: All node colors meet WCAG AA standards
5. **Motion Preferences**: Respect `prefers-reduced-motion`

---

## Cross-References

| Reference | Link |
|-----------|------|
| Nexus-Flow CLI | `14-microservices/06-nexus-flow.md` |
| Block Types | `14-microservices/06-nexus-flow.md#block-types` |
| WebSocket Protocol | `14-microservices/06-nexus-flow.md#websocket-protocol` |
| Variable Registry | `14-microservices/06-nexus-flow.md#variable-registry` |
| React Guidelines | `.lovable/memories/constraints/react-guidelines.md` |
