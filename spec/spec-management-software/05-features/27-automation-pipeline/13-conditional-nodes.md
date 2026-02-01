# Conditional Nodes

**Version:** 2.0.0  
**Status:** Complete  
**Created:** 2026-01-30  
**Updated:** 2026-01-31  

---

## Overview

Conditional nodes enable branching logic within pipelines, routing execution based on evaluated expressions.

**Cross-References:**
- [Execution Blocks](./07-execution-blocks.md)
- [Block Chaining](./09-block-chaining.md)
- [I/O Binding System](./06-io-binding.md)

---

## 1. Conditional Block Types

### 1.1 Type Definitions

```typescript
enum ConditionalType {
  IF_ELSE = 'IF_ELSE',
  SWITCH = 'SWITCH',
  GUARD = 'GUARD',
  GATE = 'GATE',
}

interface ConditionalNode {
  readonly id: string;
  readonly type: ConditionalType;
  readonly condition: ConditionExpression;
  readonly branches: readonly Branch[];
  readonly defaultBranch: string | null;
  readonly metadata: ConditionalMetadata;
}

interface Branch {
  readonly id: string;
  readonly label: string;
  readonly condition: ConditionExpression | null; // null for default/else
  readonly targetBlockId: string;
  readonly priority: number;
}

interface ConditionalMetadata {
  readonly evaluationMode: EvaluationMode;
  readonly shortCircuit: boolean;
  readonly cacheResult: boolean;
  readonly timeoutMs: number;
}

enum EvaluationMode {
  FIRST_MATCH = 'FIRST_MATCH',   // Stop at first true condition
  ALL_MATCHES = 'ALL_MATCHES',   // Execute all matching branches
  EXCLUSIVE = 'EXCLUSIVE',       // Error if multiple match
}
```

### 1.2 Conditional Type Behaviors

| Type | Branches | Default Required | Use Case |
|------|----------|------------------|----------|
| `IF_ELSE` | 2 | Yes (else) | Binary decisions |
| `SWITCH` | 2-N | Optional | Multi-way routing |
| `GUARD` | 1 | No | Early exit conditions |
| `GATE` | 1 | No | Validation checkpoint |

---

## 2. Condition Expression System

### 2.1 Expression Types

```typescript
type ConditionExpression = 
  | ComparisonExpression
  | LogicalExpression
  | ExistenceExpression
  | PatternExpression
  | FunctionExpression;

interface ComparisonExpression {
  readonly type: 'COMPARISON';
  readonly left: ValueReference;
  readonly operator: ComparisonOperator;
  readonly right: ValueReference;
}

enum ComparisonOperator {
  EQUALS = 'EQUALS',
  NOT_EQUALS = 'NOT_EQUALS',
  GREATER_THAN = 'GREATER_THAN',
  GREATER_EQUAL = 'GREATER_EQUAL',
  LESS_THAN = 'LESS_THAN',
  LESS_EQUAL = 'LESS_EQUAL',
  CONTAINS = 'CONTAINS',
  STARTS_WITH = 'STARTS_WITH',
  ENDS_WITH = 'ENDS_WITH',
  MATCHES = 'MATCHES',          // Regex
  IN = 'IN',                    // Array membership
  NOT_IN = 'NOT_IN',
}

interface LogicalExpression {
  readonly type: 'LOGICAL';
  readonly operator: LogicalOperator;
  readonly operands: readonly ConditionExpression[];
}

enum LogicalOperator {
  AND = 'AND',
  OR = 'OR',
  NOT = 'NOT',
  XOR = 'XOR',
  NAND = 'NAND',
}

interface ExistenceExpression {
  readonly type: 'EXISTENCE';
  readonly reference: ValueReference;
  readonly check: ExistenceCheck;
}

enum ExistenceCheck {
  EXISTS = 'EXISTS',
  NOT_EXISTS = 'NOT_EXISTS',
  IS_NULL = 'IS_NULL',
  IS_NOT_NULL = 'IS_NOT_NULL',
  IS_EMPTY = 'IS_EMPTY',
  IS_NOT_EMPTY = 'IS_NOT_EMPTY',
  IS_TRUTHY = 'IS_TRUTHY',
  IS_FALSY = 'IS_FALSY',
}

interface PatternExpression {
  readonly type: 'PATTERN';
  readonly reference: ValueReference;
  readonly pattern: string;
  readonly flags: string;
}

interface FunctionExpression {
  readonly type: 'FUNCTION';
  readonly functionName: ConditionFunction;
  readonly arguments: readonly ValueReference[];
}

enum ConditionFunction {
  IS_TYPE = 'IS_TYPE',           // isType(value, 'string')
  LENGTH_EQUALS = 'LENGTH_EQUALS',
  LENGTH_BETWEEN = 'LENGTH_BETWEEN',
  DATE_BEFORE = 'DATE_BEFORE',
  DATE_AFTER = 'DATE_AFTER',
  JSON_PATH_EXISTS = 'JSON_PATH_EXISTS',
  ARRAY_INCLUDES = 'ARRAY_INCLUDES',
  ARRAY_ALL = 'ARRAY_ALL',
  ARRAY_SOME = 'ARRAY_SOME',
}
```

### 2.2 Value References

```typescript
interface ValueReference {
  readonly type: ValueReferenceType;
  readonly path: string;
  readonly defaultValue?: unknown;
}

enum ValueReferenceType {
  VARIABLE = 'VARIABLE',       // {{block.stage.output}}
  LITERAL = 'LITERAL',         // Static value
  CONTEXT = 'CONTEXT',         // {{context.user.role}}
  COMPUTED = 'COMPUTED',       // Result of transform
}
```

---

## 3. Condition Evaluator

### 3.1 Evaluator Architecture

```typescript
interface ConditionEvaluator {
  evaluate(
    expression: ConditionExpression,
    context: EvaluationContext
  ): Promise<EvaluationResult>;
  
  validateExpression(
    expression: ConditionExpression
  ): readonly ValidationError[];
  
  explainEvaluation(
    expression: ConditionExpression,
    context: EvaluationContext
  ): EvaluationExplanation;
}

interface EvaluationContext {
  readonly variables: VariableRegistry;
  readonly contextData: Record<string, unknown>;
  readonly functions: FunctionRegistry;
}

interface EvaluationResult {
  readonly value: boolean;
  readonly confidence: number;        // 0-1 for fuzzy matching
  readonly evaluationPath: readonly EvaluationStep[];
  readonly durationMs: number;
}

interface EvaluationStep {
  readonly expression: ConditionExpression;
  readonly inputValues: Record<string, unknown>;
  readonly result: boolean;
  readonly shortCircuited: boolean;
}

interface EvaluationExplanation {
  readonly summary: string;           // "name equals 'admin' AND role in ['owner', 'admin']"
  readonly steps: readonly ExplanationStep[];
  readonly visualization: string;     // Mermaid diagram
}
```

### 3.2 Evaluation Flow

```
┌─────────────────┐
│ Parse Expression│
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Resolve Values  │◄── Variable Registry
└────────┬────────┘
         │
         ▼
┌─────────────────┐     ┌──────────────┐
│ Type Coercion   │────►│ Type Coercer │
└────────┬────────┘     └──────────────┘
         │
         ▼
┌─────────────────┐
│ Apply Operator  │
└────────┬────────┘
         │
    ┌────┴────┐
    │         │
    ▼         ▼
┌───────┐ ┌───────┐
│ true  │ │ false │
└───┬───┘ └───┬───┘
    │         │
    ▼         ▼
┌─────────────────┐
│ Route to Branch │
└─────────────────┘
```

---

## 4. Branch Router

### 4.1 Router Interface

```typescript
interface BranchRouter {
  route(
    node: ConditionalNode,
    context: EvaluationContext
  ): Promise<RoutingDecision>;
  
  getReachableBranches(
    node: ConditionalNode
  ): readonly Branch[];
  
  validateRouting(
    node: ConditionalNode
  ): readonly RoutingValidationError[];
}

interface RoutingDecision {
  readonly selectedBranches: readonly SelectedBranch[];
  readonly skippedBranches: readonly SkippedBranch[];
  readonly usedDefault: boolean;
  readonly evaluationLog: EvaluationResult[];
}

interface SelectedBranch {
  readonly branch: Branch;
  readonly matchReason: string;
  readonly evaluationResult: EvaluationResult;
}

interface SkippedBranch {
  readonly branch: Branch;
  readonly skipReason: SkipReason;
}

enum SkipReason {
  CONDITION_FALSE = 'CONDITION_FALSE',
  SHORT_CIRCUITED = 'SHORT_CIRCUITED',
  LOWER_PRIORITY = 'LOWER_PRIORITY',
  DISABLED = 'DISABLED',
}
```

### 4.2 Routing Strategies

```typescript
enum RoutingStrategy {
  PRIORITY = 'PRIORITY',           // Evaluate by priority order
  PARALLEL = 'PARALLEL',           // Evaluate all simultaneously
  WEIGHTED = 'WEIGHTED',           // Random weighted selection
  ROUND_ROBIN = 'ROUND_ROBIN',     // Cycle through branches
}

interface RoutingConfig {
  readonly strategy: RoutingStrategy;
  readonly maxBranches: number;        // Max concurrent branches
  readonly continueOnError: boolean;
  readonly timeoutMs: number;
}
```

---

## 5. Switch Node Specifics

### 5.1 Switch Configuration

```typescript
interface SwitchNode extends ConditionalNode {
  readonly type: ConditionalType.SWITCH;
  readonly switchValue: ValueReference;
  readonly cases: readonly SwitchCase[];
  readonly defaultCase: SwitchCase | null;
}

interface SwitchCase {
  readonly id: string;
  readonly label: string;
  readonly matchValues: readonly MatchValue[];
  readonly targetBlockId: string;
  readonly fallthrough: boolean;
}

type MatchValue = 
  | { readonly type: 'EXACT'; readonly value: unknown }
  | { readonly type: 'RANGE'; readonly min: number; readonly max: number }
  | { readonly type: 'PATTERN'; readonly regex: string }
  | { readonly type: 'TYPE'; readonly typeName: string };
```

### 5.2 Switch Visualization

```
                    ┌──────────────┐
                    │  SWITCH      │
                    │  {{status}}  │
                    └──────┬───────┘
           ┌───────────────┼───────────────┐
           ▼               ▼               ▼
    ┌────────────┐  ┌────────────┐  ┌────────────┐
    │ "pending"  │  │ "active"   │  │ "error"    │
    │   Case 1   │  │   Case 2   │  │   Case 3   │
    └──────┬─────┘  └──────┬─────┘  └──────┬─────┘
           │               │               │
           ▼               ▼               ▼
    ┌────────────┐  ┌────────────┐  ┌────────────┐
    │ Process    │  │ Notify     │  │ Escalate   │
    │ Pending    │  │ Active     │  │ Error      │
    └────────────┘  └────────────┘  └────────────┘
```

---

## 6. Guard and Gate Nodes

### 6.1 Guard Node

Guards exit the pipeline early if condition fails.

```typescript
interface GuardNode extends ConditionalNode {
  readonly type: ConditionalType.GUARD;
  readonly guardCondition: ConditionExpression;
  readonly failureAction: GuardFailureAction;
  readonly failureMessage: string;
}

enum GuardFailureAction {
  ABORT = 'ABORT',             // Stop pipeline with failure
  SKIP = 'SKIP',               // Skip remaining blocks
  RETURN = 'RETURN',           // Return early with value
  ESCALATE = 'ESCALATE',       // Trigger human review
}

// Usage Example
const authGuard: GuardNode = {
  id: 'auth-guard',
  type: ConditionalType.GUARD,
  guardCondition: {
    type: 'EXISTENCE',
    reference: { type: 'CONTEXT', path: 'user.id' },
    check: ExistenceCheck.EXISTS,
  },
  failureAction: GuardFailureAction.ABORT,
  failureMessage: 'Authentication required',
};
```

### 6.2 Gate Node

Gates validate data quality before proceeding.

```typescript
interface GateNode extends ConditionalNode {
  readonly type: ConditionalType.GATE;
  readonly validations: readonly GateValidation[];
  readonly passThreshold: number;          // 0-1, percentage that must pass
  readonly reportMode: GateReportMode;
}

interface GateValidation {
  readonly id: string;
  readonly name: string;
  readonly condition: ConditionExpression;
  readonly weight: number;
  readonly critical: boolean;              // Fail gate if this fails
}

enum GateReportMode {
  FIRST_FAILURE = 'FIRST_FAILURE',
  ALL_FAILURES = 'ALL_FAILURES',
  SUMMARY = 'SUMMARY',
}
```

---

## 7. Visual Components

### 7.1 Conditional Node Component

```typescript
interface ConditionalNodeProps {
  readonly node: ConditionalNode;
  readonly executionState: ConditionalExecutionState;
  readonly onConditionEdit: (expression: ConditionExpression) => void;
  readonly onBranchAdd: () => void;
  readonly onBranchRemove: (branchId: string) => void;
}

interface ConditionalExecutionState {
  readonly status: NodeExecutionStatus;
  readonly evaluatedBranches: readonly EvaluatedBranch[];
  readonly selectedBranch: string | null;
  readonly evaluationDurationMs: number;
}

interface EvaluatedBranch {
  readonly branchId: string;
  readonly result: boolean;
  readonly evaluated: boolean;
}
```

### 7.2 Branch Handle Styling

| Branch State | Color | Icon |
|--------------|-------|------|
| Selected | `--accent` | CheckCircle |
| Evaluated True | `--success` | Check |
| Evaluated False | `--muted` | X |
| Pending | `--muted-foreground` | Circle |
| Default | `--secondary` | CornerDownRight |

### 7.3 Condition Builder UI

```typescript
interface ConditionBuilderProps {
  readonly expression: ConditionExpression;
  readonly availableVariables: readonly VariableInfo[];
  readonly onChange: (expression: ConditionExpression) => void;
  readonly mode: BuilderMode;
}

enum BuilderMode {
  VISUAL = 'VISUAL',           // Drag-drop blocks
  EXPRESSION = 'EXPRESSION',   // Text-based
  HYBRID = 'HYBRID',           // Both available
}
```

---

## 8. Nested Conditionals

### 8.1 Nesting Rules

```typescript
interface NestingConstraints {
  readonly maxDepth: number;           // Default: 5
  readonly allowedNestedTypes: readonly ConditionalType[];
  readonly requireExplicitDefault: boolean;
}

const NESTING_RULES: NestingConstraints = {
  maxDepth: 5,
  allowedNestedTypes: [
    ConditionalType.IF_ELSE,
    ConditionalType.SWITCH,
    ConditionalType.GUARD,
  ],
  requireExplicitDefault: true,
};
```

### 8.2 Nested Evaluation

```typescript
interface NestedEvaluator {
  evaluateNested(
    rootNode: ConditionalNode,
    context: EvaluationContext
  ): Promise<NestedEvaluationResult>;
}

interface NestedEvaluationResult {
  readonly path: readonly ConditionalNode[];
  readonly finalBranch: Branch;
  readonly evaluationTree: EvaluationTreeNode;
}

interface EvaluationTreeNode {
  readonly node: ConditionalNode;
  readonly result: boolean;
  readonly selectedBranch: Branch | null;
  readonly children: readonly EvaluationTreeNode[];
}
```

---

## 9. Database Schema

### 9.1 Conditional Tables

```sql
-- Conditional Nodes
CREATE TABLE ConditionalNode (
  Id TEXT PRIMARY KEY,
  BlockId TEXT NOT NULL REFERENCES ExecutionBlock(Id),
  Type TEXT NOT NULL CHECK (Type IN ('IF_ELSE', 'SWITCH', 'GUARD', 'GATE')),
  ConditionJson TEXT NOT NULL,          -- ConditionExpression JSON
  EvaluationMode TEXT NOT NULL DEFAULT 'FIRST_MATCH',
  ShortCircuit INTEGER NOT NULL DEFAULT 1,
  TimeoutMs INTEGER NOT NULL DEFAULT 5000,
  CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
  UpdatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Branches
CREATE TABLE ConditionalBranch (
  Id TEXT PRIMARY KEY,
  NodeId TEXT NOT NULL REFERENCES ConditionalNode(Id),
  Label TEXT NOT NULL,
  ConditionJson TEXT,                   -- null for default
  TargetBlockId TEXT NOT NULL REFERENCES ExecutionBlock(Id),
  Priority INTEGER NOT NULL DEFAULT 0,
  IsDefault INTEGER NOT NULL DEFAULT 0,
  CreatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Evaluation History
CREATE TABLE ConditionEvaluation (
  Id TEXT PRIMARY KEY,
  ExecutionId TEXT NOT NULL REFERENCES PipelineExecution(Id),
  NodeId TEXT NOT NULL REFERENCES ConditionalNode(Id),
  SelectedBranchId TEXT REFERENCES ConditionalBranch(Id),
  EvaluationLogJson TEXT NOT NULL,
  DurationMs INTEGER NOT NULL,
  UsedDefault INTEGER NOT NULL DEFAULT 0,
  EvaluatedAt TEXT NOT NULL DEFAULT (datetime('now'))
);
```

---

## 10. Error Handling

### 10.1 Conditional Errors

```typescript
enum ConditionalErrorCode {
  EVALUATION_FAILED = 'COND_001',
  NO_MATCHING_BRANCH = 'COND_002',
  MULTIPLE_MATCHES_EXCLUSIVE = 'COND_003',
  TIMEOUT = 'COND_004',
  INVALID_EXPRESSION = 'COND_005',
  CYCLE_DETECTED = 'COND_006',
  GUARD_FAILED = 'COND_007',
  GATE_THRESHOLD_NOT_MET = 'COND_008',
}

interface ConditionalError {
  readonly code: ConditionalErrorCode;
  readonly nodeId: string;
  readonly expression: ConditionExpression;
  readonly context: Record<string, unknown>;
  readonly suggestion: string;
}
```

---

## Related Specs

- [Loop Constructs](./14-loop-constructs.md)
- [Error Handlers](./15-error-handlers.md)
- [Stage Nodes](./11-stage-nodes.md)
