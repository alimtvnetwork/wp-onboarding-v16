# Component: React Flow Canvas

**Parent:** [Automation Pipeline](./00-overview.md)  
**Version:** 2.0.0  
**Status:** Complete  
**Phase:** 4 - Node Canvas UI  

---

## Summary

Visual node-based pipeline editor built on React Flow. Provides drag-drop block placement, connection wiring, pan/zoom navigation, and real-time execution visualization.

---

## User Stories

- As a user, I want to visually create pipelines by dragging blocks onto a canvas
- As a user, I want to connect blocks by drawing lines between them
- As a user, I want to pan and zoom the canvas to navigate large pipelines
- As a user, I want to see real-time execution progress on the canvas
- As a user, I want to select, move, and delete multiple blocks at once

---

## Technology

### React Flow

Using `reactflow` (v11.x) for the node-based editor:

```bash
npm install reactflow
```

**Key Features:**
- Custom node types
- Custom edge types
- Built-in pan/zoom
- Selection and multi-select
- Minimap and controls
- Keyboard shortcuts

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      Pipeline Canvas                             │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                     Toolbar                               │   │
│  │  [Run] [Debug] [Validate] | [Undo] [Redo] | [Zoom] [Fit] │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌────────────┐  ┌────────────────────────────────────────────┐ │
│  │            │  │                                            │ │
│  │  Block     │  │            React Flow Canvas               │ │
│  │  Palette   │  │                                            │ │
│  │            │  │    ┌─────────┐      ┌─────────┐           │ │
│  │  ├ Prompt  │  │    │ Block A │──────│ Block B │           │ │
│  │  ├ Search  │  │    └─────────┘      └────┬────┘           │ │
│  │  ├ Code    │  │                          │                 │ │
│  │  ├ Valid.  │  │                     ┌────┴────┐           │ │
│  │  ├ HTTP    │  │                     │ Block C │           │ │
│  │  └ File    │  │                     └─────────┘           │ │
│  │            │  │                                            │ │
│  └────────────┘  └────────────────────────────────────────────┘ │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    Minimap                                │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Core Components

### PipelineCanvas

Main container component.

```typescript
interface PipelineCanvasProps {
  pipelineId: string;
  readonly?: boolean;
  onSave?: (pipeline: PipelineState) => void;
  onExecute?: () => void;
  executionState?: PipelineExecutionState;
}

interface PipelineState {
  nodes: Node<BlockNodeData>[];
  edges: Edge<ConnectionEdgeData>[];
  viewport: Viewport;
}

interface Viewport {
  x: number;
  y: number;
  zoom: number;
}
```

### Implementation

```tsx
import ReactFlow, {
  Background,
  Controls,
  MiniMap,
  Node,
  Edge,
  useNodesState,
  useEdgesState,
  addEdge,
  Connection,
  NodeTypes,
  EdgeTypes,
  Panel,
} from 'reactflow';
import 'reactflow/dist/style.css';

const nodeTypes: NodeTypes = {
  blockNode: BlockNode,
  stageNode: StageNode,
  conditionalNode: ConditionalNode,
  loopNode: LoopNode,
  parallelNode: ParallelNode,
};

const edgeTypes: EdgeTypes = {
  dataEdge: DataEdge,
  controlEdge: ControlEdge,
  conditionalEdge: ConditionalEdge,
};

export function PipelineCanvas({
  pipelineId,
  readonly = false,
  onSave,
  onExecute,
  executionState,
}: PipelineCanvasProps) {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [selectedNodes, setSelectedNodes] = useState<string[]>([]);
  
  // Load pipeline data
  const { data: pipeline } = useQuery({
    queryKey: ['pipeline', pipelineId],
    queryFn: () => fetchPipeline(pipelineId),
  });
  
  // Convert pipeline to React Flow format
  useEffect(() => {
    if (pipeline) {
      const { nodes: flowNodes, edges: flowEdges } = pipelineToFlow(pipeline);
      setNodes(flowNodes);
      setEdges(flowEdges);
    }
  }, [pipeline]);
  
  // Handle new connections
  const onConnect = useCallback((connection: Connection) => {
    if (readonly) return;
    
    const newEdge = {
      ...connection,
      type: 'dataEdge',
      data: { connectionType: ConnectionType.DATA },
    };
    
    setEdges((eds) => addEdge(newEdge, eds));
  }, [readonly]);
  
  // Handle node drag from palette
  const onDragOver = useCallback((event: React.DragEvent) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
  }, []);
  
  const onDrop = useCallback((event: React.DragEvent) => {
    if (readonly) return;
    
    event.preventDefault();
    
    const type = event.dataTransfer.getData('application/reactflow');
    const position = reactFlowInstance.screenToFlowPosition({
      x: event.clientX,
      y: event.clientY,
    });
    
    const newNode = createBlockNode(type, position);
    setNodes((nds) => [...nds, newNode]);
  }, [readonly, reactFlowInstance]);
  
  // Auto-save on changes
  useEffect(() => {
    const timer = setTimeout(() => {
      if (onSave) {
        onSave({ nodes, edges, viewport: reactFlowInstance?.getViewport() });
      }
    }, 1000);
    
    return () => clearTimeout(timer);
  }, [nodes, edges]);
  
  return (
    <div className="w-full h-full">
      <ReactFlow
        nodes={nodes}
        edges={edges}
        onNodesChange={readonly ? undefined : onNodesChange}
        onEdgesChange={readonly ? undefined : onEdgesChange}
        onConnect={onConnect}
        onDragOver={onDragOver}
        onDrop={onDrop}
        nodeTypes={nodeTypes}
        edgeTypes={edgeTypes}
        fitView
        snapToGrid
        snapGrid={[16, 16]}
        connectionMode={ConnectionMode.Loose}
        defaultEdgeOptions={{
          type: 'dataEdge',
          animated: false,
        }}
      >
        <Background variant={BackgroundVariant.Dots} gap={16} size={1} />
        <Controls showInteractive={!readonly} />
        <MiniMap 
          nodeColor={getNodeColor}
          maskColor="rgba(0, 0, 0, 0.1)"
        />
        
        {/* Top toolbar */}
        <Panel position="top-center">
          <CanvasToolbar
            onExecute={onExecute}
            onValidate={handleValidate}
            isExecuting={executionState?.status === 'RUNNING'}
            readonly={readonly}
          />
        </Panel>
        
        {/* Block palette */}
        <Panel position="top-left">
          <BlockPalette readonly={readonly} />
        </Panel>
      </ReactFlow>
    </div>
  );
}
```

---

## Block Palette

Draggable block type selector.

```typescript
interface BlockPaletteProps {
  readonly: boolean;
}

interface PaletteItem {
  type: StageType;
  label: string;
  icon: LucideIcon;
  description: string;
  color: string;
}

const paletteItems: PaletteItem[] = [
  {
    type: StageType.PROMPT,
    label: 'Prompt',
    icon: MessageSquare,
    description: 'Execute AI prompt',
    color: 'bg-blue-500',
  },
  {
    type: StageType.SEARCH,
    label: 'Search',
    icon: Search,
    description: 'Web search',
    color: 'bg-green-500',
  },
  {
    type: StageType.CODE_GEN,
    label: 'Code',
    icon: Code,
    description: 'Generate code',
    color: 'bg-purple-500',
  },
  {
    type: StageType.VALIDATION,
    label: 'Validate',
    icon: CheckCircle,
    description: 'Run validation',
    color: 'bg-orange-500',
  },
  {
    type: StageType.HTTP,
    label: 'HTTP',
    icon: Globe,
    description: 'API request',
    color: 'bg-cyan-500',
  },
  {
    type: StageType.TRANSFORM,
    label: 'Transform',
    icon: Shuffle,
    description: 'Data transform',
    color: 'bg-yellow-500',
  },
  {
    type: StageType.FILE_OP,
    label: 'File',
    icon: FileText,
    description: 'File operation',
    color: 'bg-gray-500',
  },
];

export function BlockPalette({ readonly }: BlockPaletteProps) {
  const onDragStart = (event: React.DragEvent, type: StageType) => {
    event.dataTransfer.setData('application/reactflow', type);
    event.dataTransfer.effectAllowed = 'move';
  };
  
  return (
    <div className="bg-background border rounded-lg shadow-lg p-2 w-48">
      <h3 className="text-sm font-semibold mb-2 px-2">Blocks</h3>
      
      <div className="space-y-1">
        {paletteItems.map((item) => (
          <div
            key={item.type}
            draggable={!readonly}
            onDragStart={(e) => onDragStart(e, item.type)}
            className={cn(
              "flex items-center gap-2 p-2 rounded cursor-grab",
              "hover:bg-muted transition-colors",
              readonly && "opacity-50 cursor-not-allowed"
            )}
          >
            <div className={cn("p-1.5 rounded", item.color)}>
              <item.icon className="h-4 w-4 text-white" />
            </div>
            <div className="flex-1 min-w-0">
              <div className="text-sm font-medium">{item.label}</div>
              <div className="text-xs text-muted-foreground truncate">
                {item.description}
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
```

---

## Canvas Toolbar

```typescript
interface CanvasToolbarProps {
  onExecute: () => void;
  onValidate: () => void;
  isExecuting: boolean;
  readonly: boolean;
}

export function CanvasToolbar({
  onExecute,
  onValidate,
  isExecuting,
  readonly,
}: CanvasToolbarProps) {
  const { undo, redo, canUndo, canRedo } = useUndoRedo();
  const { fitView, zoomIn, zoomOut, getZoom } = useReactFlow();
  
  return (
    <div className="flex items-center gap-2 bg-background border rounded-lg shadow-lg p-2">
      {/* Execution controls */}
      <div className="flex items-center gap-1 border-r pr-2">
        <Button
          size="sm"
          onClick={onExecute}
          disabled={isExecuting || readonly}
        >
          {isExecuting ? (
            <Loader2 className="h-4 w-4 animate-spin mr-1" />
          ) : (
            <Play className="h-4 w-4 mr-1" />
          )}
          {isExecuting ? 'Running' : 'Run'}
        </Button>
        
        <Button
          size="sm"
          variant="outline"
          onClick={onValidate}
          disabled={readonly}
        >
          <CheckCircle className="h-4 w-4 mr-1" />
          Validate
        </Button>
      </div>
      
      {/* History controls */}
      <div className="flex items-center gap-1 border-r pr-2">
        <Button
          size="icon"
          variant="ghost"
          onClick={undo}
          disabled={!canUndo || readonly}
          title="Undo (Ctrl+Z)"
        >
          <Undo className="h-4 w-4" />
        </Button>
        
        <Button
          size="icon"
          variant="ghost"
          onClick={redo}
          disabled={!canRedo || readonly}
          title="Redo (Ctrl+Shift+Z)"
        >
          <Redo className="h-4 w-4" />
        </Button>
      </div>
      
      {/* Zoom controls */}
      <div className="flex items-center gap-1">
        <Button
          size="icon"
          variant="ghost"
          onClick={() => zoomOut()}
          title="Zoom out"
        >
          <ZoomOut className="h-4 w-4" />
        </Button>
        
        <span className="text-xs text-muted-foreground w-12 text-center">
          {Math.round(getZoom() * 100)}%
        </span>
        
        <Button
          size="icon"
          variant="ghost"
          onClick={() => zoomIn()}
          title="Zoom in"
        >
          <ZoomIn className="h-4 w-4" />
        </Button>
        
        <Button
          size="icon"
          variant="ghost"
          onClick={() => fitView({ padding: 0.2 })}
          title="Fit to view"
        >
          <Maximize className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}
```

---

## Execution Visualization

Real-time execution state overlay.

```typescript
interface ExecutionOverlayProps {
  executionState: PipelineExecutionState;
  nodes: Node[];
  edges: Edge[];
}

interface PipelineExecutionState {
  status: PipelineExecutionStatus;
  currentPhase: number;
  blockStates: Map<string, BlockExecutionState>;
  dataFlows: DataFlowEvent[];
}

interface BlockExecutionState {
  status: BlockExecutionStatus;
  progress: number;
  currentStage?: string;
  startedAt?: string;
  error?: string;
}

interface DataFlowEvent {
  id: string;
  sourceBlockId: string;
  targetBlockId: string;
  timestamp: string;
  dataSize: number;
}

export function ExecutionOverlay({
  executionState,
  nodes,
  edges,
}: ExecutionOverlayProps) {
  // Update node styles based on execution state
  const getNodeStyle = (nodeId: string): CSSProperties => {
    const state = executionState.blockStates.get(nodeId);
    
    if (!state) return {};
    
    switch (state.status) {
      case BlockExecutionStatus.RUNNING:
        return {
          boxShadow: '0 0 0 2px var(--primary)',
          animation: 'pulse 2s infinite',
        };
      case BlockExecutionStatus.SUCCESS:
        return {
          boxShadow: '0 0 0 2px var(--success)',
        };
      case BlockExecutionStatus.FAILED:
        return {
          boxShadow: '0 0 0 2px var(--destructive)',
        };
      default:
        return { opacity: 0.5 };
    }
  };
  
  // Animate data flow along edges
  const activeFlows = executionState.dataFlows.filter(
    flow => Date.now() - new Date(flow.timestamp).getTime() < 2000
  );
  
  return (
    <>
      {/* Progress indicators on nodes */}
      {nodes.map((node) => {
        const state = executionState.blockStates.get(node.id);
        if (!state || state.status !== BlockExecutionStatus.RUNNING) return null;
        
        return (
          <div
            key={`progress-${node.id}`}
            className="absolute pointer-events-none"
            style={{
              left: node.position.x,
              top: node.position.y - 8,
              width: node.width,
            }}
          >
            <Progress value={state.progress} className="h-1" />
          </div>
        );
      })}
      
      {/* Animated data particles on edges */}
      {activeFlows.map((flow) => (
        <DataFlowParticle
          key={flow.id}
          sourceId={flow.sourceBlockId}
          targetId={flow.targetBlockId}
          edges={edges}
        />
      ))}
    </>
  );
}
```

---

## Keyboard Shortcuts

```typescript
const keyboardShortcuts = {
  // Selection
  'Delete': 'deleteSelected',
  'Backspace': 'deleteSelected',
  'Escape': 'clearSelection',
  'Ctrl+A': 'selectAll',
  
  // History
  'Ctrl+Z': 'undo',
  'Ctrl+Shift+Z': 'redo',
  'Ctrl+Y': 'redo',
  
  // Clipboard
  'Ctrl+C': 'copy',
  'Ctrl+V': 'paste',
  'Ctrl+D': 'duplicate',
  
  // View
  'Ctrl+0': 'fitView',
  'Ctrl+=': 'zoomIn',
  'Ctrl+-': 'zoomOut',
  
  // Execution
  'Ctrl+Enter': 'execute',
  'Ctrl+Shift+V': 'validate',
};

export function useCanvasKeyboardShortcuts(handlers: ShortcutHandlers) {
  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      const key = getShortcutKey(event);
      const action = keyboardShortcuts[key];
      
      if (action && handlers[action]) {
        event.preventDefault();
        handlers[action]();
      }
    };
    
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [handlers]);
}
```

---

## Undo/Redo System

```typescript
interface HistoryState {
  nodes: Node[];
  edges: Edge[];
}

interface UndoRedoContext {
  undo: () => void;
  redo: () => void;
  canUndo: boolean;
  canRedo: boolean;
  pushState: (state: HistoryState) => void;
}

export function useUndoRedo(maxHistory = 50): UndoRedoContext {
  const [history, setHistory] = useState<HistoryState[]>([]);
  const [currentIndex, setCurrentIndex] = useState(-1);
  
  const pushState = useCallback((state: HistoryState) => {
    setHistory((prev) => {
      // Remove any future states if we're not at the end
      const newHistory = prev.slice(0, currentIndex + 1);
      
      // Add new state
      newHistory.push(state);
      
      // Limit history size
      if (newHistory.length > maxHistory) {
        newHistory.shift();
      }
      
      return newHistory;
    });
    
    setCurrentIndex((prev) => Math.min(prev + 1, maxHistory - 1));
  }, [currentIndex, maxHistory]);
  
  const undo = useCallback(() => {
    if (currentIndex > 0) {
      setCurrentIndex((prev) => prev - 1);
      return history[currentIndex - 1];
    }
    return null;
  }, [currentIndex, history]);
  
  const redo = useCallback(() => {
    if (currentIndex < history.length - 1) {
      setCurrentIndex((prev) => prev + 1);
      return history[currentIndex + 1];
    }
    return null;
  }, [currentIndex, history]);
  
  return {
    undo,
    redo,
    canUndo: currentIndex > 0,
    canRedo: currentIndex < history.length - 1,
    pushState,
  };
}
```

---

## Canvas State Management

```typescript
interface CanvasStore {
  // State
  nodes: Node[];
  edges: Edge[];
  selectedNodes: string[];
  selectedEdges: string[];
  viewport: Viewport;
  isExecuting: boolean;
  executionState: PipelineExecutionState | null;
  
  // Actions
  setNodes: (nodes: Node[]) => void;
  setEdges: (edges: Edge[]) => void;
  addNode: (node: Node) => void;
  removeNodes: (ids: string[]) => void;
  updateNode: (id: string, data: Partial<Node>) => void;
  addEdge: (edge: Edge) => void;
  removeEdges: (ids: string[]) => void;
  selectNodes: (ids: string[]) => void;
  clearSelection: () => void;
  setViewport: (viewport: Viewport) => void;
  setExecutionState: (state: PipelineExecutionState | null) => void;
}

export const useCanvasStore = create<CanvasStore>((set, get) => ({
  nodes: [],
  edges: [],
  selectedNodes: [],
  selectedEdges: [],
  viewport: { x: 0, y: 0, zoom: 1 },
  isExecuting: false,
  executionState: null,
  
  setNodes: (nodes) => set({ nodes }),
  setEdges: (edges) => set({ edges }),
  
  addNode: (node) => set((state) => ({
    nodes: [...state.nodes, node],
  })),
  
  removeNodes: (ids) => set((state) => ({
    nodes: state.nodes.filter((n) => !ids.includes(n.id)),
    edges: state.edges.filter(
      (e) => !ids.includes(e.source) && !ids.includes(e.target)
    ),
    selectedNodes: state.selectedNodes.filter((id) => !ids.includes(id)),
  })),
  
  updateNode: (id, data) => set((state) => ({
    nodes: state.nodes.map((n) =>
      n.id === id ? { ...n, ...data } : n
    ),
  })),
  
  addEdge: (edge) => set((state) => ({
    edges: [...state.edges, edge],
  })),
  
  removeEdges: (ids) => set((state) => ({
    edges: state.edges.filter((e) => !ids.includes(e.id)),
    selectedEdges: state.selectedEdges.filter((id) => !ids.includes(id)),
  })),
  
  selectNodes: (ids) => set({ selectedNodes: ids }),
  clearSelection: () => set({ selectedNodes: [], selectedEdges: [] }),
  setViewport: (viewport) => set({ viewport }),
  setExecutionState: (executionState) => set({ 
    executionState,
    isExecuting: executionState?.status === PipelineExecutionStatus.RUNNING,
  }),
}));
```

---

## Context Menu

```typescript
interface ContextMenuProps {
  x: number;
  y: number;
  type: 'canvas' | 'node' | 'edge';
  targetId?: string;
  onClose: () => void;
}

export function CanvasContextMenu({
  x,
  y,
  type,
  targetId,
  onClose,
}: ContextMenuProps) {
  const canvasStore = useCanvasStore();
  
  const menuItems = useMemo(() => {
    switch (type) {
      case 'canvas':
        return [
          { label: 'Paste', icon: Clipboard, action: 'paste', shortcut: 'Ctrl+V' },
          { label: 'Select All', icon: Square, action: 'selectAll', shortcut: 'Ctrl+A' },
          { divider: true },
          { label: 'Fit View', icon: Maximize, action: 'fitView', shortcut: 'Ctrl+0' },
        ];
      
      case 'node':
        return [
          { label: 'Edit', icon: Edit, action: 'editNode' },
          { label: 'Duplicate', icon: Copy, action: 'duplicateNode', shortcut: 'Ctrl+D' },
          { divider: true },
          { label: 'Delete', icon: Trash2, action: 'deleteNode', shortcut: 'Del' },
        ];
      
      case 'edge':
        return [
          { label: 'Edit Connection', icon: Edit, action: 'editEdge' },
          { divider: true },
          { label: 'Delete', icon: Trash2, action: 'deleteEdge', shortcut: 'Del' },
        ];
      
      default:
        return [];
    }
  }, [type]);
  
  return (
    <ContextMenu open onOpenChange={(open) => !open && onClose()}>
      <ContextMenuContent style={{ position: 'fixed', left: x, top: y }}>
        {menuItems.map((item, index) =>
          item.divider ? (
            <ContextMenuSeparator key={index} />
          ) : (
            <ContextMenuItem
              key={item.action}
              onClick={() => handleAction(item.action, targetId)}
            >
              <item.icon className="h-4 w-4 mr-2" />
              {item.label}
              {item.shortcut && (
                <ContextMenuShortcut>{item.shortcut}</ContextMenuShortcut>
              )}
            </ContextMenuItem>
          )
        )}
      </ContextMenuContent>
    </ContextMenu>
  );
}
```

---

## Performance Optimizations

### Virtualization

```typescript
// Only render nodes within viewport + buffer
const visibleNodes = useMemo(() => {
  const { x, y, zoom } = viewport;
  const viewportBounds = {
    minX: -x / zoom - BUFFER,
    maxX: (-x + width) / zoom + BUFFER,
    minY: -y / zoom - BUFFER,
    maxY: (-y + height) / zoom + BUFFER,
  };
  
  return nodes.filter((node) =>
    node.position.x >= viewportBounds.minX &&
    node.position.x <= viewportBounds.maxX &&
    node.position.y >= viewportBounds.minY &&
    node.position.y <= viewportBounds.maxY
  );
}, [nodes, viewport, width, height]);
```

### Memoization

```typescript
// Memoize expensive node components
const BlockNode = memo(function BlockNode({ data, selected }: NodeProps) {
  // Component implementation
}, (prevProps, nextProps) => {
  return (
    prevProps.selected === nextProps.selected &&
    prevProps.data.status === nextProps.data.status &&
    prevProps.data.name === nextProps.data.name
  );
});
```

---

## Accessibility

```typescript
const accessibilityFeatures = {
  // Keyboard navigation
  tabIndex: 0,
  ariaLabel: 'Pipeline canvas',
  ariaDescribedBy: 'canvas-instructions',
  
  // Screen reader announcements
  announceNodeAdd: (name: string) => `Added block: ${name}`,
  announceConnection: (source: string, target: string) => 
    `Connected ${source} to ${target}`,
  announceExecution: (status: string) => `Pipeline ${status}`,
  
  // Focus management
  focusNode: (id: string) => { /* Focus node */ },
  focusEdge: (id: string) => { /* Focus edge */ },
};
```

---

## Performance Targets

| Metric | Target |
|--------|--------|
| Initial render (50 nodes) | < 100ms |
| Node add/remove | < 16ms |
| Pan/zoom frame rate | 60fps |
| Edge path calculation | < 5ms |
| Selection update | < 10ms |
| Execution overlay update | < 16ms |

---

## Related Specs

- [Stage Node Components](./11-stage-nodes.md)
- [Connection Wiring](./12-connection-wiring.md)
- [Execution Blocks](./07-execution-blocks.md)
- [Block Chaining](./09-block-chaining.md)
