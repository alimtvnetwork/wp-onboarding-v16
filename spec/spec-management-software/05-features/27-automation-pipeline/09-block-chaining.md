# Component: Block Chaining

**Parent:** [Automation Pipeline](./00-overview.md)  
**Version:** 1.0.0  
**Status:** Planned  
**Phase:** 3 - Block Orchestration  

---

## Summary

System for connecting execution blocks through data and control flow connections. Manages output-to-input mappings, conditional routing, and visual connection representation on the pipeline canvas.

---

## User Stories

- As a user, I want to pass output from one block as input to another
- As a user, I want to create conditional connections based on block results
- As a user, I want to visualize connections between blocks on the canvas
- As a user, I want to validate that connected blocks have compatible types
- As a user, I want to merge outputs from multiple parallel blocks

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      Block Chaining System                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                  Connection Manager                       │   │
│  │  • Create/update/delete connections                       │   │
│  │  • Validate connection compatibility                      │   │
│  │  • Manage connection lifecycle                            │   │
│  └──────────────────────────────────────────────────────────┘   │
│                              │                                   │
│         ┌────────────────────┼────────────────────┐             │
│         ▼                    ▼                    ▼             │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐       │
│  │    Data     │     │  Control    │     │ Conditional │       │
│  │ Connections │     │ Connections │     │ Connections │       │
│  └─────────────┘     └─────────────┘     └─────────────┘       │
│         │                    │                    │             │
│         └────────────────────┼────────────────────┘             │
│                              ▼                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                  Data Flow Router                         │   │
│  │  • Route data between blocks                              │   │
│  │  • Apply output mappings                                  │   │
│  │  • Handle merge operations                                │   │
│  └──────────────────────────────────────────────────────────┘   │
│                              │                                   │
│                              ▼                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                  Visual Renderer                          │   │
│  │  • Draw connection lines                                  │   │
│  │  • Animate data flow                                      │   │
│  │  • Handle interaction (select, delete)                    │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Connection Types

### Data Connection

Passes output data from source block to target block input.

```typescript
interface DataConnection extends BlockConnection {
  ConnectionType: 'DATA';
  OutputMapping: OutputMapping;
}

interface OutputMapping {
  mappings: VariableMapping[];
  mergeStrategy?: MergeStrategy;       // For multiple inputs
}

interface VariableMapping {
  sourceVariable: string;              // e.g., "output", "file", "metadata.tokens"
  targetVariable: string;              // e.g., "input.data", "context.previousResult"
  transform?: TransformChain;          // Optional transformation
  required: boolean;
}

enum MergeStrategy {
  REPLACE = 'REPLACE',                 // Last value wins
  APPEND = 'APPEND',                   // Append to array
  MERGE = 'MERGE',                     // Deep merge objects
  CONCAT = 'CONCAT',                   // Concatenate strings
  FIRST = 'FIRST',                     // First non-null
  CUSTOM = 'CUSTOM',                   // Custom merge function
}
```

### Control Connection

Defines execution order without data transfer.

```typescript
interface ControlConnection extends BlockConnection {
  ConnectionType: 'CONTROL';
  ExecutionDependency: ExecutionDependency;
}

interface ExecutionDependency {
  waitFor: WaitCondition;
  onFailure: FailureAction;
}

enum WaitCondition {
  COMPLETION = 'COMPLETION',           // Wait for block to complete
  SUCCESS = 'SUCCESS',                 // Wait for successful completion
  ANY_STATUS = 'ANY_STATUS',           // Continue regardless of status
}

enum FailureAction {
  STOP = 'STOP',                       // Stop pipeline
  SKIP = 'SKIP',                       // Skip target block
  CONTINUE = 'CONTINUE',               // Execute anyway
  BRANCH = 'BRANCH',                   // Take alternate path
}
```

### Conditional Connection

Routes execution based on conditions.

```typescript
interface ConditionalConnection extends BlockConnection {
  ConnectionType: 'CONDITIONAL';
  Condition: ConnectionCondition;
}

interface ConnectionCondition {
  expression: string;                  // e.g., "{{source.status}} === 'SUCCESS'"
  type: ConditionType;
  parameters?: Record<string, unknown>;
}

enum ConditionType {
  EXPRESSION = 'EXPRESSION',           // JavaScript-like expression
  STATUS_CHECK = 'STATUS_CHECK',       // Check block status
  OUTPUT_MATCH = 'OUTPUT_MATCH',       // Match output value
  THRESHOLD = 'THRESHOLD',             // Numeric threshold
  REGEX = 'REGEX',                     // Regex match on output
  CUSTOM = 'CUSTOM',                   // Custom validator script
}
```

---

## Connection Manager

### Interface

```typescript
interface ConnectionManager {
  // CRUD
  create(connection: CreateConnectionRequest): Promise<BlockConnection>;
  update(id: string, updates: UpdateConnectionRequest): Promise<BlockConnection>;
  delete(id: string): Promise<void>;
  get(id: string): Promise<BlockConnection>;
  
  // Queries
  getByPipeline(pipelineId: string): Promise<BlockConnection[]>;
  getBySource(blockId: string): Promise<BlockConnection[]>;
  getByTarget(blockId: string): Promise<BlockConnection[]>;
  
  // Validation
  validate(connection: BlockConnection): Promise<ConnectionValidationResult>;
  validatePipeline(pipelineId: string): Promise<PipelineConnectionValidation>;
  
  // Graph operations
  getConnectionGraph(pipelineId: string): Promise<ConnectionGraph>;
  findPath(fromBlockId: string, toBlockId: string): Promise<string[]>;
  detectCycles(pipelineId: string): Promise<CycleDetectionResult>;
}

interface CreateConnectionRequest {
  pipelineId: string;
  sourceBlockId: string;
  targetBlockId: string;
  connectionType: ConnectionType;
  condition?: ConnectionCondition;
  outputMapping?: OutputMapping;
  sourceHandle?: string;               // Canvas anchor point
  targetHandle?: string;
}

interface ConnectionGraph {
  nodes: Map<string, GraphNode>;
  edges: GraphEdge[];
  adjacencyList: Map<string, string[]>;
  reverseAdjacencyList: Map<string, string[]>;
}

interface GraphNode {
  blockId: string;
  inDegree: number;
  outDegree: number;
  isSource: boolean;                   // No incoming connections
  isSink: boolean;                     // No outgoing connections
}

interface GraphEdge {
  id: string;
  source: string;
  target: string;
  type: ConnectionType;
  weight?: number;                     // For path finding
}
```

### Implementation

```typescript
class ConnectionManagerImpl implements ConnectionManager {
  async create(request: CreateConnectionRequest): Promise<BlockConnection> {
    // 1. Validate blocks exist and are in same pipeline
    await this.validateBlocks(request);
    
    // 2. Check for cycles
    const wouldCreateCycle = await this.wouldCreateCycle(
      request.pipelineId,
      request.sourceBlockId,
      request.targetBlockId
    );
    
    if (wouldCreateCycle) {
      throw new ConnectionError({
        code: 'CYCLE_DETECTED',
        message: 'This connection would create a circular dependency',
      });
    }
    
    // 3. Validate connection compatibility
    if (request.connectionType === 'DATA') {
      await this.validateDataCompatibility(request);
    }
    
    // 4. Create connection
    const connection: BlockConnection = {
      Id: crypto.randomUUID(),
      PipelineId: request.pipelineId,
      SourceBlockId: request.sourceBlockId,
      TargetBlockId: request.targetBlockId,
      ConnectionType: request.connectionType,
      Condition: request.condition ? JSON.stringify(request.condition) : null,
      OutputMapping: request.outputMapping ? JSON.stringify(request.outputMapping) : null,
      SourceHandle: request.sourceHandle ?? 'bottom',
      TargetHandle: request.targetHandle ?? 'top',
      CreatedAt: new Date().toISOString(),
    };
    
    await this.db.insert('BlockConnection', connection);
    
    return connection;
  }
  
  async detectCycles(pipelineId: string): Promise<CycleDetectionResult> {
    const graph = await this.getConnectionGraph(pipelineId);
    const visited = new Set<string>();
    const recursionStack = new Set<string>();
    const cycles: string[][] = [];
    
    const dfs = (nodeId: string, path: string[]): boolean => {
      visited.add(nodeId);
      recursionStack.add(nodeId);
      path.push(nodeId);
      
      const neighbors = graph.adjacencyList.get(nodeId) ?? [];
      
      for (const neighbor of neighbors) {
        if (!visited.has(neighbor)) {
          if (dfs(neighbor, [...path])) {
            return true;
          }
        } else if (recursionStack.has(neighbor)) {
          // Found cycle
          const cycleStart = path.indexOf(neighbor);
          cycles.push([...path.slice(cycleStart), neighbor]);
          return true;
        }
      }
      
      recursionStack.delete(nodeId);
      return false;
    };
    
    for (const nodeId of graph.nodes.keys()) {
      if (!visited.has(nodeId)) {
        dfs(nodeId, []);
      }
    }
    
    return {
      hasCycles: cycles.length > 0,
      cycles,
      affectedBlocks: [...new Set(cycles.flat())],
    };
  }
  
  async validate(connection: BlockConnection): Promise<ConnectionValidationResult> {
    const errors: ValidationError[] = [];
    const warnings: ValidationWarning[] = [];
    
    // 1. Check source and target exist
    const sourceBlock = await this.blockManager.get(connection.SourceBlockId);
    const targetBlock = await this.blockManager.get(connection.TargetBlockId);
    
    if (!sourceBlock) {
      errors.push({ code: 'INVALID_SOURCE', message: 'Source block not found' });
    }
    
    if (!targetBlock) {
      errors.push({ code: 'INVALID_TARGET', message: 'Target block not found' });
    }
    
    // 2. Validate data connection mappings
    if (connection.ConnectionType === 'DATA' && connection.OutputMapping) {
      const mapping = JSON.parse(connection.OutputMapping) as OutputMapping;
      
      for (const varMapping of mapping.mappings) {
        // Check source variable exists
        const sourceOutputs = await this.getBlockOutputVariables(connection.SourceBlockId);
        if (!sourceOutputs.includes(varMapping.sourceVariable)) {
          warnings.push({
            code: 'UNKNOWN_SOURCE_VAR',
            message: `Source variable "${varMapping.sourceVariable}" may not exist`,
          });
        }
        
        // Check target variable is valid
        const targetInputs = await this.getBlockInputVariables(connection.TargetBlockId);
        if (!targetInputs.includes(varMapping.targetVariable)) {
          warnings.push({
            code: 'UNKNOWN_TARGET_VAR',
            message: `Target variable "${varMapping.targetVariable}" may not be used`,
          });
        }
      }
    }
    
    // 3. Validate conditional expression
    if (connection.ConnectionType === 'CONDITIONAL' && connection.Condition) {
      const condition = JSON.parse(connection.Condition) as ConnectionCondition;
      const exprValid = this.validateExpression(condition.expression);
      
      if (!exprValid.valid) {
        errors.push({
          code: 'INVALID_CONDITION',
          message: `Invalid condition: ${exprValid.error}`,
        });
      }
    }
    
    return {
      valid: errors.length === 0,
      errors,
      warnings,
    };
  }
}
```

---

## Data Flow Router

### Interface

```typescript
interface DataFlowRouter {
  route(
    sourceBlockId: string,
    sourceResult: BlockResult,
    connections: BlockConnection[],
    context: VariableContext
  ): Promise<RoutingResult>;
  
  merge(
    targetBlockId: string,
    incomingData: IncomingData[],
    mergeStrategy: MergeStrategy
  ): Promise<MergedData>;
}

interface RoutingResult {
  routes: RouteTarget[];
  skipped: SkippedRoute[];
}

interface RouteTarget {
  targetBlockId: string;
  connectionId: string;
  data: Record<string, unknown>;
}

interface SkippedRoute {
  targetBlockId: string;
  connectionId: string;
  reason: string;
}

interface IncomingData {
  sourceBlockId: string;
  connectionId: string;
  data: Record<string, unknown>;
  timestamp: string;
}

interface MergedData {
  result: Record<string, unknown>;
  sources: string[];
  mergeLog: MergeLogEntry[];
}

interface MergeLogEntry {
  variable: string;
  sources: string[];
  strategy: MergeStrategy;
  result: unknown;
}
```

### Implementation

```typescript
class DataFlowRouterImpl implements DataFlowRouter {
  async route(
    sourceBlockId: string,
    sourceResult: BlockResult,
    connections: BlockConnection[],
    context: VariableContext
  ): Promise<RoutingResult> {
    const routes: RouteTarget[] = [];
    const skipped: SkippedRoute[] = [];
    
    for (const connection of connections) {
      // Skip if not a data or conditional connection from this source
      if (connection.SourceBlockId !== sourceBlockId) continue;
      
      // Evaluate condition for conditional connections
      if (connection.ConnectionType === 'CONDITIONAL') {
        const condition = JSON.parse(connection.Condition) as ConnectionCondition;
        const shouldRoute = await this.evaluateCondition(
          condition,
          sourceResult,
          context
        );
        
        if (!shouldRoute) {
          skipped.push({
            targetBlockId: connection.TargetBlockId,
            connectionId: connection.Id,
            reason: 'Condition not met',
          });
          continue;
        }
      }
      
      // Apply output mapping
      if (connection.ConnectionType === 'DATA' || 
          connection.ConnectionType === 'CONDITIONAL') {
        const mapping = connection.OutputMapping 
          ? JSON.parse(connection.OutputMapping) as OutputMapping
          : this.createDefaultMapping();
        
        const mappedData = await this.applyMapping(
          mapping,
          sourceResult,
          context
        );
        
        routes.push({
          targetBlockId: connection.TargetBlockId,
          connectionId: connection.Id,
          data: mappedData,
        });
      }
    }
    
    return { routes, skipped };
  }
  
  async merge(
    targetBlockId: string,
    incomingData: IncomingData[],
    mergeStrategy: MergeStrategy
  ): Promise<MergedData> {
    if (incomingData.length === 0) {
      return { result: {}, sources: [], mergeLog: [] };
    }
    
    if (incomingData.length === 1) {
      return {
        result: incomingData[0].data,
        sources: [incomingData[0].sourceBlockId],
        mergeLog: [],
      };
    }
    
    const mergeLog: MergeLogEntry[] = [];
    let result: Record<string, unknown> = {};
    
    // Sort by timestamp for deterministic merging
    incomingData.sort((a, b) => a.timestamp.localeCompare(b.timestamp));
    
    switch (mergeStrategy) {
      case MergeStrategy.REPLACE:
        result = incomingData[incomingData.length - 1].data;
        break;
      
      case MergeStrategy.MERGE:
        for (const incoming of incomingData) {
          result = this.deepMerge(result, incoming.data);
        }
        break;
      
      case MergeStrategy.APPEND:
        // Collect all values for each key into arrays
        for (const incoming of incomingData) {
          for (const [key, value] of Object.entries(incoming.data)) {
            if (!result[key]) {
              result[key] = [];
            }
            (result[key] as unknown[]).push(value);
          }
        }
        break;
      
      case MergeStrategy.CONCAT:
        // Concatenate string values
        for (const incoming of incomingData) {
          for (const [key, value] of Object.entries(incoming.data)) {
            if (typeof value === 'string') {
              result[key] = ((result[key] as string) ?? '') + value;
            } else {
              result[key] = value;
            }
          }
        }
        break;
      
      case MergeStrategy.FIRST:
        // Take first non-null value for each key
        for (const incoming of incomingData) {
          for (const [key, value] of Object.entries(incoming.data)) {
            if (result[key] === undefined && value !== null) {
              result[key] = value;
            }
          }
        }
        break;
      
      default:
        result = incomingData[0].data;
    }
    
    return {
      result,
      sources: incomingData.map(d => d.sourceBlockId),
      mergeLog,
    };
  }
  
  private async evaluateCondition(
    condition: ConnectionCondition,
    sourceResult: BlockResult,
    context: VariableContext
  ): Promise<boolean> {
    switch (condition.type) {
      case ConditionType.STATUS_CHECK:
        const expectedStatus = condition.parameters?.status;
        return sourceResult.status === expectedStatus;
      
      case ConditionType.OUTPUT_MATCH:
        const expectedValue = condition.parameters?.value;
        const outputPath = condition.parameters?.path as string;
        const actualValue = this.getByPath(sourceResult.outputVariables, outputPath);
        return actualValue === expectedValue;
      
      case ConditionType.THRESHOLD:
        const threshold = condition.parameters?.threshold as number;
        const valuePath = condition.parameters?.path as string;
        const numValue = this.getByPath(sourceResult.outputVariables, valuePath) as number;
        const operator = condition.parameters?.operator as string || '>=';
        return this.compareThreshold(numValue, threshold, operator);
      
      case ConditionType.EXPRESSION:
        // Substitute variables and evaluate expression
        const resolvedExpr = substituteVariables(condition.expression, {
          ...context,
          source: sourceResult,
        });
        return this.safeEvaluate(resolvedExpr);
      
      case ConditionType.REGEX:
        const pattern = new RegExp(condition.parameters?.pattern as string);
        const testPath = condition.parameters?.path as string;
        const testValue = String(this.getByPath(sourceResult.outputVariables, testPath));
        return pattern.test(testValue);
      
      default:
        return true;
    }
  }
  
  private async applyMapping(
    mapping: OutputMapping,
    sourceResult: BlockResult,
    context: VariableContext
  ): Promise<Record<string, unknown>> {
    const result: Record<string, unknown> = {};
    
    for (const varMapping of mapping.mappings) {
      // Get source value
      let value = this.getOutputValue(sourceResult, varMapping.sourceVariable);
      
      // Apply transform if specified
      if (varMapping.transform) {
        value = await this.transformEngine.apply(value, varMapping.transform, context);
      }
      
      // Skip if required and null
      if (varMapping.required && (value === null || value === undefined)) {
        throw new DataRoutingError({
          code: 'REQUIRED_VALUE_NULL',
          message: `Required mapping "${varMapping.sourceVariable}" is null`,
        });
      }
      
      // Set target value
      this.setByPath(result, varMapping.targetVariable, value);
    }
    
    return result;
  }
  
  private getOutputValue(result: BlockResult, path: string): unknown {
    // Handle special paths
    if (path === 'output') {
      return result.outputVariables;
    }
    if (path === 'status') {
      return result.status;
    }
    if (path === 'error') {
      return result.error?.message;
    }
    
    // Navigate path in output variables
    return this.getByPath(result.outputVariables, path);
  }
}
```

---

## Visual Connection Rendering

### Connection Path Calculation

```typescript
interface ConnectionRenderer {
  calculatePath(
    sourceBlock: ExecutionBlock,
    targetBlock: ExecutionBlock,
    sourceHandle: string,
    targetHandle: string
  ): ConnectionPath;
  
  getControlPoints(path: ConnectionPath): ControlPoint[];
}

interface ConnectionPath {
  type: PathType;
  points: Point[];
  bounds: BoundingBox;
}

enum PathType {
  STRAIGHT = 'STRAIGHT',
  BEZIER = 'BEZIER',
  STEP = 'STEP',
  SMOOTH_STEP = 'SMOOTH_STEP',
}

interface Point {
  x: number;
  y: number;
}

interface ControlPoint {
  position: Point;
  handleIn?: Point;
  handleOut?: Point;
}

class ConnectionRendererImpl implements ConnectionRenderer {
  calculatePath(
    sourceBlock: ExecutionBlock,
    targetBlock: ExecutionBlock,
    sourceHandle: string,
    targetHandle: string
  ): ConnectionPath {
    // Get handle positions
    const sourcePos = this.getHandlePosition(sourceBlock, sourceHandle);
    const targetPos = this.getHandlePosition(targetBlock, targetHandle);
    
    // Calculate path based on relative positions
    const dx = targetPos.x - sourcePos.x;
    const dy = targetPos.y - sourcePos.y;
    
    // Use bezier for standard connections
    if (dy > 0 && Math.abs(dx) < sourceBlock.CanvasWidth * 2) {
      return this.createBezierPath(sourcePos, targetPos);
    }
    
    // Use step path for complex layouts
    return this.createSmoothStepPath(sourcePos, targetPos);
  }
  
  private createBezierPath(source: Point, target: Point): ConnectionPath {
    const midY = (source.y + target.y) / 2;
    
    return {
      type: PathType.BEZIER,
      points: [
        source,
        { x: source.x, y: midY },
        { x: target.x, y: midY },
        target,
      ],
      bounds: this.calculateBounds([source, target]),
    };
  }
  
  private createSmoothStepPath(source: Point, target: Point): ConnectionPath {
    const offset = 50;
    const midX = (source.x + target.x) / 2;
    
    return {
      type: PathType.SMOOTH_STEP,
      points: [
        source,
        { x: source.x, y: source.y + offset },
        { x: midX, y: source.y + offset },
        { x: midX, y: target.y - offset },
        { x: target.x, y: target.y - offset },
        target,
      ],
      bounds: this.calculateBounds([source, target]),
    };
  }
  
  private getHandlePosition(block: ExecutionBlock, handle: string): Point {
    const x = block.CanvasX ?? 0;
    const y = block.CanvasY ?? 0;
    const width = block.CanvasWidth ?? 200;
    const height = block.CanvasHeight ?? 150;
    
    switch (handle) {
      case 'top':
        return { x: x + width / 2, y };
      case 'bottom':
        return { x: x + width / 2, y: y + height };
      case 'left':
        return { x, y: y + height / 2 };
      case 'right':
        return { x: x + width, y: y + height / 2 };
      default:
        return { x: x + width / 2, y: y + height };
    }
  }
}
```

---

## UI Components

### Connection Line

```typescript
interface ConnectionLineProps {
  connection: BlockConnection;
  sourceBlock: ExecutionBlock;
  targetBlock: ExecutionBlock;
  isSelected: boolean;
  isAnimating: boolean;
  dataFlowing: boolean;
  onClick: () => void;
  onDelete: () => void;
}
```

**Visual States:**
- Default: Solid line with arrow
- Selected: Highlighted with thicker stroke
- Data flow: Animated particles along path
- Conditional: Dashed line with condition icon
- Error: Red color with warning indicator

### Connection Editor Modal

```typescript
interface ConnectionEditorProps {
  connection?: BlockConnection;
  sourceBlock: ExecutionBlock;
  targetBlock: ExecutionBlock;
  onSave: (connection: BlockConnection) => void;
  onCancel: () => void;
}
```

**Sections:**
- Connection Type selector
- Output Mapping builder (for DATA)
- Condition editor (for CONDITIONAL)
- Merge strategy (for multiple inputs)
- Handle position selectors

### Mapping Builder

```typescript
interface MappingBuilderProps {
  sourceOutputs: VariableInfo[];
  targetInputs: VariableInfo[];
  mapping: OutputMapping;
  onChange: (mapping: OutputMapping) => void;
}

interface VariableInfo {
  name: string;
  type: DataType;
  description?: string;
  path: string;
}
```

**Features:**
- Drag-drop variable connections
- Type compatibility indicators
- Transform chain builder
- Preview with sample data

---

## Connection Validation

```typescript
interface ConnectionValidation {
  validate(pipelineId: string): Promise<PipelineConnectionValidation>;
}

interface PipelineConnectionValidation {
  valid: boolean;
  connections: Map<string, ConnectionValidationResult>;
  graphErrors: GraphError[];
  suggestions: ConnectionSuggestion[];
}

interface GraphError {
  type: GraphErrorType;
  message: string;
  affectedConnections: string[];
  affectedBlocks: string[];
}

enum GraphErrorType {
  CYCLE = 'CYCLE',
  ORPHAN_BLOCK = 'ORPHAN_BLOCK',       // Block with no connections
  DEAD_END = 'DEAD_END',               // Output never used
  UNREACHABLE = 'UNREACHABLE',         // Block can't be reached
  MULTIPLE_PATHS = 'MULTIPLE_PATHS',   // Ambiguous routing
}

interface ConnectionSuggestion {
  type: 'add' | 'remove' | 'modify';
  sourceBlockId?: string;
  targetBlockId?: string;
  reason: string;
}
```

---

## Performance Targets

| Metric | Target |
|--------|--------|
| Connection creation | < 20ms |
| Path calculation | < 5ms |
| Cycle detection (100 blocks) | < 50ms |
| Data routing (per connection) | < 10ms |
| Merge operation | < 20ms |
| Validation (pipeline) | < 100ms |

---

## Related Specs

- [Execution Blocks](./07-execution-blocks.md)
- [Parallel/Sequential Control](./08-parallel-control.md)
- [Input/Output Binding](./06-io-binding.md)
- [Variable Registry](./03-variable-registry.md)
