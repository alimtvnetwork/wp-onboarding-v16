# Component: Connection Wiring

**Parent:** [Automation Pipeline](./00-overview.md)  
**Version:** 1.0.0  
**Status:** Planned  
**Phase:** 4 - Node Canvas UI  

---

## Summary

Visual connection system for linking blocks and stages on the pipeline canvas. Provides custom edge rendering, connection validation, animated data flow visualization, and interactive connection editing.

---

## User Stories

- As a user, I want to draw connections by dragging from one block to another
- As a user, I want to see different line styles for different connection types
- As a user, I want to see data flowing through connections during execution
- As a user, I want to click on connections to edit their configuration
- As a user, I want invalid connections to be visually indicated

---

## Edge Types

### Connection Edge Data

```typescript
interface ConnectionEdgeData {
  connection: BlockConnection;
  connectionType: ConnectionType;
  isValid: boolean;
  validationError?: string;
  isActive: boolean;           // Currently executing
  dataFlowProgress?: number;   // 0-100 for animation
}

enum ConnectionType {
  DATA = 'DATA',
  CONTROL = 'CONTROL',
  CONDITIONAL = 'CONDITIONAL',
}
```

---

## Custom Edge Components

### Data Edge

Standard data flow connection.

```tsx
import { EdgeProps, getBezierPath, EdgeLabelRenderer } from 'reactflow';

export function DataEdge({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  data,
  selected,
  style,
}: EdgeProps<ConnectionEdgeData>) {
  const [edgePath, labelX, labelY] = getBezierPath({
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
  });
  
  const isActive = data?.isActive;
  const isValid = data?.isValid ?? true;
  
  return (
    <>
      {/* Base edge path */}
      <path
        id={id}
        className={cn(
          "react-flow__edge-path",
          !isValid && "stroke-destructive",
          selected && "stroke-primary stroke-[3px]"
        )}
        d={edgePath}
        strokeWidth={selected ? 3 : 2}
        stroke={isValid ? "hsl(var(--primary))" : "hsl(var(--destructive))"}
        fill="none"
        markerEnd="url(#arrow)"
      />
      
      {/* Animated data flow overlay */}
      {isActive && (
        <path
          d={edgePath}
          strokeWidth={4}
          stroke="hsl(var(--primary))"
          fill="none"
          strokeDasharray="10,10"
          className="animate-dash"
          style={{
            animation: 'dash 1s linear infinite',
          }}
        />
      )}
      
      {/* Data flow particles */}
      {isActive && data?.dataFlowProgress !== undefined && (
        <DataFlowParticle
          path={edgePath}
          progress={data.dataFlowProgress}
        />
      )}
      
      {/* Edge label */}
      <EdgeLabelRenderer>
        <div
          className={cn(
            "absolute pointer-events-auto nodrag nopan",
            "px-2 py-1 rounded text-xs bg-background border shadow-sm",
            selected && "ring-2 ring-primary"
          )}
          style={{
            transform: `translate(-50%, -50%) translate(${labelX}px, ${labelY}px)`,
          }}
        >
          <ConnectionLabel connection={data?.connection} />
        </div>
      </EdgeLabelRenderer>
    </>
  );
}

// CSS for dash animation
const dashAnimation = `
  @keyframes dash {
    to {
      stroke-dashoffset: -20;
    }
  }
`;
```

### Control Edge

Execution order dependency.

```tsx
export function ControlEdge({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  data,
  selected,
}: EdgeProps<ConnectionEdgeData>) {
  const [edgePath] = getSmoothStepPath({
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
    borderRadius: 8,
  });
  
  return (
    <>
      <path
        id={id}
        d={edgePath}
        strokeWidth={selected ? 2 : 1}
        stroke="hsl(var(--muted-foreground))"
        strokeDasharray="5,5"
        fill="none"
        markerEnd="url(#arrow-control)"
      />
      
      {/* Control badge */}
      <EdgeLabelRenderer>
        <Badge
          variant="outline"
          className="absolute pointer-events-auto"
          style={{
            transform: `translate(-50%, -50%) translate(${(sourceX + targetX) / 2}px, ${(sourceY + targetY) / 2}px)`,
          }}
        >
          <ArrowRight className="h-3 w-3" />
        </Badge>
      </EdgeLabelRenderer>
    </>
  );
}
```

### Conditional Edge

Connection with condition evaluation.

```tsx
export function ConditionalEdge({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  data,
  selected,
}: EdgeProps<ConnectionEdgeData>) {
  const [edgePath, labelX, labelY] = getBezierPath({
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
  });
  
  const condition = data?.connection?.Condition 
    ? JSON.parse(data.connection.Condition) as ConnectionCondition
    : null;
  
  // Determine if condition was met (during execution)
  const conditionMet = data?.conditionResult;
  
  return (
    <>
      {/* Edge path with conditional styling */}
      <path
        id={id}
        d={edgePath}
        strokeWidth={selected ? 3 : 2}
        stroke={
          conditionMet === true
            ? "hsl(var(--success))"
            : conditionMet === false
            ? "hsl(var(--muted))"
            : "hsl(var(--warning))"
        }
        strokeDasharray={conditionMet === false ? "5,5" : undefined}
        fill="none"
        markerEnd="url(#arrow-conditional)"
      />
      
      {/* Condition label */}
      <EdgeLabelRenderer>
        <div
          className={cn(
            "absolute pointer-events-auto nodrag nopan",
            "px-2 py-1 rounded-full text-xs font-medium",
            "bg-warning/20 border border-warning",
            selected && "ring-2 ring-primary"
          )}
          style={{
            transform: `translate(-50%, -50%) translate(${labelX}px, ${labelY}px)`,
          }}
        >
          <div className="flex items-center gap-1">
            <GitBranch className="h-3 w-3" />
            {condition && (
              <span className="max-w-[100px] truncate">
                {formatCondition(condition)}
              </span>
            )}
          </div>
        </div>
      </EdgeLabelRenderer>
    </>
  );
}
```

---

## Data Flow Particles

Animated particles showing data transfer.

```tsx
interface DataFlowParticleProps {
  path: string;
  progress: number;  // 0-100
}

export function DataFlowParticle({ path, progress }: DataFlowParticleProps) {
  const particleRef = useRef<SVGCircleElement>(null);
  
  useEffect(() => {
    if (!particleRef.current) return;
    
    // Get point along path at progress percentage
    const pathElement = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    pathElement.setAttribute('d', path);
    
    const pathLength = pathElement.getTotalLength();
    const point = pathElement.getPointAtLength((progress / 100) * pathLength);
    
    particleRef.current.setAttribute('cx', String(point.x));
    particleRef.current.setAttribute('cy', String(point.y));
  }, [path, progress]);
  
  return (
    <g>
      {/* Glow effect */}
      <circle
        ref={particleRef}
        r={8}
        fill="hsl(var(--primary))"
        opacity={0.3}
      />
      {/* Core particle */}
      <circle
        ref={particleRef}
        r={4}
        fill="hsl(var(--primary))"
      />
    </g>
  );
}

// Multiple particles for stream effect
export function DataFlowStream({ path, isActive }: { path: string; isActive: boolean }) {
  const [particles, setParticles] = useState<number[]>([]);
  
  useEffect(() => {
    if (!isActive) {
      setParticles([]);
      return;
    }
    
    const interval = setInterval(() => {
      setParticles((prev) => {
        // Add new particle at start
        const next = [0, ...prev.map(p => p + 5).filter(p => p <= 100)];
        return next;
      });
    }, 200);
    
    return () => clearInterval(interval);
  }, [isActive]);
  
  return (
    <>
      {particles.map((progress, index) => (
        <DataFlowParticle key={index} path={path} progress={progress} />
      ))}
    </>
  );
}
```

---

## Connection Handles

Custom connection handles on nodes.

```tsx
interface ConnectionHandleProps {
  type: 'source' | 'target';
  position: Position;
  id?: string;
  isConnectable?: boolean;
  onConnect?: (params: Connection) => void;
}

export function ConnectionHandle({
  type,
  position,
  id,
  isConnectable = true,
  onConnect,
}: ConnectionHandleProps) {
  const [isHovered, setIsHovered] = useState(false);
  const [isDragging, setIsDragging] = useState(false);
  
  return (
    <Handle
      type={type}
      position={position}
      id={id}
      isConnectable={isConnectable}
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
      className={cn(
        "w-3 h-3 rounded-full border-2 transition-all duration-150",
        "border-background",
        type === 'source' ? 'bg-primary' : 'bg-muted-foreground',
        isHovered && "scale-150",
        isDragging && "scale-150 ring-4 ring-primary/20"
      )}
      style={{
        // Position adjustments based on handle position
        ...(position === Position.Top && { top: -6 }),
        ...(position === Position.Bottom && { bottom: -6 }),
        ...(position === Position.Left && { left: -6 }),
        ...(position === Position.Right && { right: -6 }),
      }}
    />
  );
}
```

---

## Connection Validation

Real-time validation during connection creation.

```typescript
interface ConnectionValidator {
  validate(connection: Connection): ValidationResult;
  getValidationHint(connection: Partial<Connection>): string;
}

interface ValidationResult {
  isValid: boolean;
  error?: string;
  warning?: string;
}

export function useConnectionValidation(): ConnectionValidator {
  const canvasStore = useCanvasStore();
  
  const validate = useCallback((connection: Connection): ValidationResult => {
    const { source, target, sourceHandle, targetHandle } = connection;
    
    // 1. Can't connect to self
    if (source === target) {
      return {
        isValid: false,
        error: "Cannot connect a block to itself",
      };
    }
    
    // 2. Check for existing connection
    const existingConnection = canvasStore.edges.find(
      (e) => e.source === source && e.target === target
    );
    if (existingConnection) {
      return {
        isValid: false,
        error: "Connection already exists",
      };
    }
    
    // 3. Check for cycles
    if (wouldCreateCycle(source, target, canvasStore.edges)) {
      return {
        isValid: false,
        error: "This would create a circular dependency",
      };
    }
    
    // 4. Type compatibility check
    const sourceNode = canvasStore.nodes.find((n) => n.id === source);
    const targetNode = canvasStore.nodes.find((n) => n.id === target);
    
    if (sourceNode && targetNode) {
      const compatibility = checkTypeCompatibility(
        sourceNode.data,
        targetNode.data
      );
      
      if (!compatibility.compatible) {
        return {
          isValid: false,
          error: compatibility.reason,
        };
      }
      
      if (compatibility.warning) {
        return {
          isValid: true,
          warning: compatibility.warning,
        };
      }
    }
    
    return { isValid: true };
  }, [canvasStore.edges, canvasStore.nodes]);
  
  return { validate, getValidationHint };
}

function wouldCreateCycle(
  source: string,
  target: string,
  edges: Edge[]
): boolean {
  // DFS to check if target can reach source
  const visited = new Set<string>();
  const stack = [target];
  
  while (stack.length > 0) {
    const current = stack.pop()!;
    
    if (current === source) {
      return true;  // Found cycle
    }
    
    if (visited.has(current)) continue;
    visited.add(current);
    
    // Find all nodes reachable from current
    const outgoing = edges
      .filter((e) => e.source === current)
      .map((e) => e.target);
    
    stack.push(...outgoing);
  }
  
  return false;
}
```

---

## Visual Connection Feedback

```tsx
interface ConnectionLineProps {
  fromX: number;
  fromY: number;
  toX: number;
  toY: number;
  connectionStatus: ConnectionStatus;
}

export function ConnectionLine({
  fromX,
  fromY,
  toX,
  toY,
  connectionStatus,
}: ConnectionLineProps) {
  const path = `M${fromX},${fromY} C${fromX},${(fromY + toY) / 2} ${toX},${(fromY + toY) / 2} ${toX},${toY}`;
  
  const colors = {
    [ConnectionStatus.VALID]: 'stroke-primary',
    [ConnectionStatus.INVALID]: 'stroke-destructive',
    [ConnectionStatus.UNKNOWN]: 'stroke-muted-foreground',
  };
  
  return (
    <g>
      {/* Glow for valid connections */}
      {connectionStatus === ConnectionStatus.VALID && (
        <path
          d={path}
          fill="none"
          strokeWidth={6}
          stroke="hsl(var(--primary))"
          opacity={0.3}
          strokeLinecap="round"
        />
      )}
      
      {/* Main line */}
      <path
        d={path}
        fill="none"
        strokeWidth={2}
        className={colors[connectionStatus]}
        strokeLinecap="round"
        strokeDasharray={connectionStatus === ConnectionStatus.UNKNOWN ? '5,5' : undefined}
      />
      
      {/* Target indicator */}
      <circle
        cx={toX}
        cy={toY}
        r={6}
        className={cn(
          "transition-all duration-150",
          connectionStatus === ConnectionStatus.VALID && "fill-primary",
          connectionStatus === ConnectionStatus.INVALID && "fill-destructive",
          connectionStatus === ConnectionStatus.UNKNOWN && "fill-muted-foreground"
        )}
      />
    </g>
  );
}
```

---

## Connection Editor Dialog

```tsx
interface ConnectionEditorDialogProps {
  connection: BlockConnection;
  open: boolean;
  onClose: () => void;
  onSave: (connection: BlockConnection) => void;
  onDelete: () => void;
}

export function ConnectionEditorDialog({
  connection,
  open,
  onClose,
  onSave,
  onDelete,
}: ConnectionEditorDialogProps) {
  const [connectionType, setConnectionType] = useState(connection.ConnectionType);
  const [outputMapping, setOutputMapping] = useState<OutputMapping>(
    connection.OutputMapping ? JSON.parse(connection.OutputMapping) : { mappings: [] }
  );
  const [condition, setCondition] = useState<ConnectionCondition | null>(
    connection.Condition ? JSON.parse(connection.Condition) : null
  );
  
  const sourceBlock = useBlock(connection.SourceBlockId);
  const targetBlock = useBlock(connection.TargetBlockId);
  
  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Edit Connection</DialogTitle>
          <DialogDescription>
            Configure how data flows from{' '}
            <span className="font-medium">{sourceBlock?.Name}</span> to{' '}
            <span className="font-medium">{targetBlock?.Name}</span>
          </DialogDescription>
        </DialogHeader>
        
        <Tabs defaultValue="type" className="mt-4">
          <TabsList>
            <TabsTrigger value="type">Connection Type</TabsTrigger>
            <TabsTrigger value="mapping">Data Mapping</TabsTrigger>
            {connectionType === ConnectionType.CONDITIONAL && (
              <TabsTrigger value="condition">Condition</TabsTrigger>
            )}
          </TabsList>
          
          <TabsContent value="type" className="space-y-4">
            <RadioGroup
              value={connectionType}
              onValueChange={(v) => setConnectionType(v as ConnectionType)}
            >
              <div className="flex items-start space-x-3 p-3 border rounded-lg">
                <RadioGroupItem value={ConnectionType.DATA} id="data" />
                <div className="space-y-1">
                  <Label htmlFor="data" className="font-medium">Data Connection</Label>
                  <p className="text-sm text-muted-foreground">
                    Pass output data from source to target input
                  </p>
                </div>
              </div>
              
              <div className="flex items-start space-x-3 p-3 border rounded-lg">
                <RadioGroupItem value={ConnectionType.CONTROL} id="control" />
                <div className="space-y-1">
                  <Label htmlFor="control" className="font-medium">Control Connection</Label>
                  <p className="text-sm text-muted-foreground">
                    Define execution order without data transfer
                  </p>
                </div>
              </div>
              
              <div className="flex items-start space-x-3 p-3 border rounded-lg">
                <RadioGroupItem value={ConnectionType.CONDITIONAL} id="conditional" />
                <div className="space-y-1">
                  <Label htmlFor="conditional" className="font-medium">Conditional Connection</Label>
                  <p className="text-sm text-muted-foreground">
                    Execute target only if condition is met
                  </p>
                </div>
              </div>
            </RadioGroup>
          </TabsContent>
          
          <TabsContent value="mapping" className="space-y-4">
            <MappingEditor
              sourceBlock={sourceBlock}
              targetBlock={targetBlock}
              mapping={outputMapping}
              onChange={setOutputMapping}
            />
          </TabsContent>
          
          <TabsContent value="condition" className="space-y-4">
            <ConditionEditor
              condition={condition}
              sourceBlock={sourceBlock}
              onChange={setCondition}
            />
          </TabsContent>
        </Tabs>
        
        <DialogFooter className="flex justify-between">
          <Button variant="destructive" onClick={onDelete}>
            <Trash2 className="h-4 w-4 mr-1" />
            Delete
          </Button>
          
          <div className="flex gap-2">
            <Button variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button onClick={() => {
              onSave({
                ...connection,
                ConnectionType: connectionType,
                OutputMapping: connectionType !== ConnectionType.CONTROL
                  ? JSON.stringify(outputMapping)
                  : null,
                Condition: connectionType === ConnectionType.CONDITIONAL
                  ? JSON.stringify(condition)
                  : null,
              });
              onClose();
            }}>
              Save Changes
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

---

## Mapping Editor

Visual mapping builder for data connections.

```tsx
interface MappingEditorProps {
  sourceBlock: ExecutionBlock;
  targetBlock: ExecutionBlock;
  mapping: OutputMapping;
  onChange: (mapping: OutputMapping) => void;
}

export function MappingEditor({
  sourceBlock,
  targetBlock,
  mapping,
  onChange,
}: MappingEditorProps) {
  const sourceOutputs = useBlockOutputs(sourceBlock.Id);
  const targetInputs = useBlockInputs(targetBlock.Id);
  
  const addMapping = () => {
    onChange({
      ...mapping,
      mappings: [
        ...mapping.mappings,
        { sourceVariable: '', targetVariable: '', required: true },
      ],
    });
  };
  
  const updateMapping = (index: number, updates: Partial<VariableMapping>) => {
    const newMappings = [...mapping.mappings];
    newMappings[index] = { ...newMappings[index], ...updates };
    onChange({ ...mapping, mappings: newMappings });
  };
  
  const removeMapping = (index: number) => {
    onChange({
      ...mapping,
      mappings: mapping.mappings.filter((_, i) => i !== index),
    });
  };
  
  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <Label>Variable Mappings</Label>
        <Button variant="outline" size="sm" onClick={addMapping}>
          <Plus className="h-4 w-4 mr-1" />
          Add Mapping
        </Button>
      </div>
      
      {mapping.mappings.length === 0 ? (
        <div className="text-center py-8 text-muted-foreground border-2 border-dashed rounded-lg">
          <ArrowRightLeft className="h-8 w-8 mx-auto mb-2 opacity-50" />
          <p>No mappings configured</p>
          <p className="text-sm">Add a mapping to pass data between blocks</p>
        </div>
      ) : (
        <div className="space-y-3">
          {mapping.mappings.map((m, index) => (
            <div
              key={index}
              className="flex items-center gap-2 p-3 border rounded-lg bg-muted/50"
            >
              {/* Source variable selector */}
              <div className="flex-1">
                <Label className="text-xs text-muted-foreground mb-1">Source</Label>
                <Select
                  value={m.sourceVariable}
                  onValueChange={(v) => updateMapping(index, { sourceVariable: v })}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select output..." />
                  </SelectTrigger>
                  <SelectContent>
                    {sourceOutputs.map((output) => (
                      <SelectItem key={output.path} value={output.path}>
                        <div className="flex items-center gap-2">
                          <span>{output.name}</span>
                          <Badge variant="secondary" className="text-xs">
                            {output.type}
                          </Badge>
                        </div>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              
              {/* Arrow */}
              <ArrowRight className="h-4 w-4 text-muted-foreground" />
              
              {/* Target variable selector */}
              <div className="flex-1">
                <Label className="text-xs text-muted-foreground mb-1">Target</Label>
                <Select
                  value={m.targetVariable}
                  onValueChange={(v) => updateMapping(index, { targetVariable: v })}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select input..." />
                  </SelectTrigger>
                  <SelectContent>
                    {targetInputs.map((input) => (
                      <SelectItem key={input.path} value={input.path}>
                        <div className="flex items-center gap-2">
                          <span>{input.name}</span>
                          <Badge variant="secondary" className="text-xs">
                            {input.type}
                          </Badge>
                        </div>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              
              {/* Required toggle */}
              <div className="flex items-center gap-1">
                <Switch
                  checked={m.required}
                  onCheckedChange={(v) => updateMapping(index, { required: v })}
                />
                <Label className="text-xs">Required</Label>
              </div>
              
              {/* Remove button */}
              <Button
                variant="ghost"
                size="icon"
                onClick={() => removeMapping(index)}
              >
                <X className="h-4 w-4" />
              </Button>
            </div>
          ))}
        </div>
      )}
      
      {/* Merge strategy */}
      <div className="space-y-2">
        <Label>Merge Strategy (for multiple inputs)</Label>
        <Select
          value={mapping.mergeStrategy || MergeStrategy.REPLACE}
          onValueChange={(v) => onChange({ ...mapping, mergeStrategy: v as MergeStrategy })}
        >
          <SelectTrigger>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={MergeStrategy.REPLACE}>Replace (last value wins)</SelectItem>
            <SelectItem value={MergeStrategy.MERGE}>Deep merge objects</SelectItem>
            <SelectItem value={MergeStrategy.APPEND}>Append to array</SelectItem>
            <SelectItem value={MergeStrategy.CONCAT}>Concatenate strings</SelectItem>
            <SelectItem value={MergeStrategy.FIRST}>First non-null value</SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>
  );
}
```

---

## Arrow Markers

SVG markers for edge arrows.

```tsx
export function EdgeMarkers() {
  return (
    <svg className="absolute" style={{ width: 0, height: 0 }}>
      <defs>
        {/* Data connection arrow */}
        <marker
          id="arrow"
          markerWidth="12"
          markerHeight="12"
          refX="10"
          refY="6"
          orient="auto"
        >
          <path
            d="M2,2 L10,6 L2,10 L4,6 Z"
            fill="hsl(var(--primary))"
          />
        </marker>
        
        {/* Control connection arrow */}
        <marker
          id="arrow-control"
          markerWidth="10"
          markerHeight="10"
          refX="8"
          refY="5"
          orient="auto"
        >
          <path
            d="M1,1 L8,5 L1,9"
            fill="none"
            stroke="hsl(var(--muted-foreground))"
            strokeWidth="1.5"
            strokeLinecap="round"
          />
        </marker>
        
        {/* Conditional connection arrow */}
        <marker
          id="arrow-conditional"
          markerWidth="12"
          markerHeight="12"
          refX="10"
          refY="6"
          orient="auto"
        >
          <path
            d="M2,2 L10,6 L2,10 L4,6 Z"
            fill="hsl(var(--warning))"
          />
        </marker>
        
        {/* Invalid connection arrow */}
        <marker
          id="arrow-invalid"
          markerWidth="12"
          markerHeight="12"
          refX="10"
          refY="6"
          orient="auto"
        >
          <path
            d="M2,2 L10,6 L2,10 L4,6 Z"
            fill="hsl(var(--destructive))"
          />
        </marker>
      </defs>
    </svg>
  );
}
```

---

## Performance Targets

| Metric | Target |
|--------|--------|
| Edge render | < 8ms |
| Path calculation | < 2ms |
| Particle animation | 60fps |
| Connection validation | < 5ms |
| Drag feedback | < 16ms |

---

## Related Specs

- [React Flow Canvas](./10-react-flow-canvas.md)
- [Stage Node Components](./11-stage-nodes.md)
- [Block Chaining](./09-block-chaining.md)
