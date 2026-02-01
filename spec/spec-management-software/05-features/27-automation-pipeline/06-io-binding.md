# Component: Input/Output Binding

**Parent:** [Automation Pipeline](./00-overview.md)  
**Version:** 1.0.0  
**Status:** Planned  
**Phase:** 2 - Stage Engine  

---

## Summary

System for connecting stage inputs and outputs through the variable registry. Manages data flow between stages, blocks, and pipeline boundaries with type coercion, validation, and transformation capabilities.

---

## User Stories

- As a user, I want to connect one stage's output to another stage's input
- As a user, I want to transform data between stages automatically
- As a user, I want to validate data types at binding points
- As a user, I want to see the data flow visually in the pipeline editor
- As a user, I want to debug binding issues with clear error messages

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    I/O Binding System                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    Binding Registry                       │   │
│  │  • Stage output registrations                             │   │
│  │  • Input requirement declarations                         │   │
│  │  • Type compatibility matrix                              │   │
│  └──────────────────────────────────────────────────────────┘   │
│                              │                                   │
│         ┌────────────────────┼────────────────────┐             │
│         ▼                    ▼                    ▼             │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐       │
│  │   Binding   │     │    Type     │     │   Transform │       │
│  │   Resolver  │────▶│   Coercer   │────▶│    Engine   │       │
│  └─────────────┘     └─────────────┘     └─────────────┘       │
│         │                    │                    │             │
│         └────────────────────┼────────────────────┘             │
│                              ▼                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                  Execution Context                        │   │
│  │  • Resolved input values                                  │   │
│  │  • Output storage                                         │   │
│  │  • Binding metadata                                       │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Binding Definition

### Input Binding Schema

Stored in the `Stage.InputBindings` field as JSON:

```typescript
interface InputBindings {
  [parameterName: string]: BindingExpression;
}

type BindingExpression = 
  | string                    // Simple variable reference: "{{prev.output}}"
  | BindingConfig;            // Complex binding with transforms

interface BindingConfig {
  source: string;             // Variable path: "{{searchBlock.webSearch.results}}"
  transform?: TransformChain;
  default?: unknown;          // Fallback value
  required?: boolean;         // Default: true
  type?: DataType;            // Expected type (for validation)
}

interface TransformChain {
  operations: TransformOperation[];
}

interface TransformOperation {
  type: TransformOperationType;
  args?: Record<string, unknown>;
}

enum TransformOperationType {
  // String operations
  TRIM = 'TRIM',
  UPPER = 'UPPER',
  LOWER = 'LOWER',
  SPLIT = 'SPLIT',
  JOIN = 'JOIN',
  REPLACE = 'REPLACE',
  SUBSTRING = 'SUBSTRING',
  TEMPLATE = 'TEMPLATE',
  
  // JSON operations
  PARSE = 'PARSE',
  STRINGIFY = 'STRINGIFY',
  JMESPATH = 'JMESPATH',
  GET = 'GET',           // Get property by path
  
  // Array operations
  FIRST = 'FIRST',
  LAST = 'LAST',
  INDEX = 'INDEX',
  MAP = 'MAP',
  FILTER = 'FILTER',
  FLATTEN = 'FLATTEN',
  UNIQUE = 'UNIQUE',
  SORT = 'SORT',
  
  // Type conversions
  TO_STRING = 'TO_STRING',
  TO_NUMBER = 'TO_NUMBER',
  TO_BOOLEAN = 'TO_BOOLEAN',
  TO_ARRAY = 'TO_ARRAY',
  
  // Conditional
  DEFAULT = 'DEFAULT',
  COALESCE = 'COALESCE',
}
```

### Examples

**Simple binding:**
```json
{
  "prompt": "{{prev.output}}",
  "maxResults": "{{pipeline.config.searchLimit}}"
}
```

**Complex binding with transforms:**
```json
{
  "items": {
    "source": "{{searchBlock.webSearch.results}}",
    "transform": {
      "operations": [
        { "type": "JMESPATH", "args": { "expression": "[*].{title: title, url: url}" } },
        { "type": "FILTER", "args": { "condition": "item.title != null" } },
        { "type": "FIRST", "args": { "count": 5 } }
      ]
    },
    "default": [],
    "type": "ARRAY"
  },
  "query": {
    "source": "{{input.searchQuery}}",
    "transform": {
      "operations": [
        { "type": "TRIM" },
        { "type": "LOWER" }
      ]
    },
    "required": true
  }
}
```

---

## Output Registration

### Output Variable Declaration

Each stage declares its output variable in `Stage.OutputVariable`:

```typescript
interface StageOutputDeclaration {
  variableName: string;       // e.g., "htmlContent"
  type: DataType;             // Expected output type
  description?: string;
  schema?: JSONSchema;        // Optional JSON schema for validation
}
```

### Automatic Variables

Outputs are automatically registered under:

```
{{blockName.stageName.output}}     // Primary output
{{blockName.stageName.file}}       // File path if FILE output
{{blockName.stageName.status}}     // Execution status
{{blockName.stageName.error}}      // Error message if failed
{{blockName.stageName.duration}}   // Execution time (ms)
{{blockName.stageName.metadata}}   // Stage metadata
```

---

## Binding Resolution

### Resolution Algorithm

```typescript
interface BindingResolver {
  resolve(
    bindings: InputBindings,
    context: VariableContext
  ): Promise<ResolvedBindings>;
}

interface ResolvedBindings {
  values: Record<string, unknown>;
  errors: BindingError[];
  warnings: BindingWarning[];
}

class BindingResolverImpl implements BindingResolver {
  private readonly typeCoercer: TypeCoercer;
  private readonly transformEngine: TransformEngine;
  
  async resolve(
    bindings: InputBindings,
    context: VariableContext
  ): Promise<ResolvedBindings> {
    const values: Record<string, unknown> = {};
    const errors: BindingError[] = [];
    const warnings: BindingWarning[] = [];
    
    for (const [paramName, binding] of Object.entries(bindings)) {
      try {
        // 1. Parse binding (string or config)
        const config = this.parseBinding(binding);
        
        // 2. Resolve source variable
        let value = resolveVariable(config.source, context);
        
        // 3. Check if value exists
        if (value === undefined || value === null) {
          if (config.required !== false) {
            errors.push({
              parameter: paramName,
              source: config.source,
              error: `Required binding not found: ${config.source}`,
            });
            continue;
          }
          value = config.default;
        }
        
        // 4. Apply transforms
        if (config.transform) {
          value = await this.transformEngine.apply(
            value,
            config.transform,
            context
          );
        }
        
        // 5. Coerce type if specified
        if (config.type) {
          value = this.typeCoercer.coerce(value, config.type);
        }
        
        values[paramName] = value;
        
      } catch (error) {
        errors.push({
          parameter: paramName,
          source: typeof binding === 'string' ? binding : binding.source,
          error: error.message,
        });
      }
    }
    
    return { values, errors, warnings };
  }
  
  private parseBinding(binding: string | BindingConfig): BindingConfig {
    if (typeof binding === 'string') {
      // Extract variable path from template syntax
      const match = binding.match(/\{\{([^}]+)\}\}/);
      return {
        source: match ? match[1].trim() : binding,
        required: true,
      };
    }
    return binding;
  }
}
```

---

## Type Coercion

### Type Compatibility Matrix

| Source → Target | STRING | NUMBER | BOOLEAN | JSON | ARRAY | FILE |
|-----------------|--------|--------|---------|------|-------|------|
| STRING | ✓ | parse | truthy | parse | split | path |
| NUMBER | str | ✓ | !=0 | wrap | wrap | ✗ |
| BOOLEAN | str | 0/1 | ✓ | wrap | wrap | ✗ |
| JSON | stringify | ✗ | ✗ | ✓ | extract | ✗ |
| ARRAY | join | length | !empty | wrap | ✓ | ✗ |
| FILE | read | ✗ | exists | read+parse | ✗ | ✓ |

### Coercer Implementation

```typescript
interface TypeCoercer {
  coerce(value: unknown, targetType: DataType): unknown;
  canCoerce(sourceType: DataType, targetType: DataType): boolean;
}

class TypeCoercerImpl implements TypeCoercer {
  coerce(value: unknown, targetType: DataType): unknown {
    const sourceType = this.detectType(value);
    
    if (sourceType === targetType) {
      return value;
    }
    
    const coercionKey = `${sourceType}_TO_${targetType}`;
    const coercer = this.coercers.get(coercionKey);
    
    if (!coercer) {
      throw new BindingError(
        `Cannot coerce ${sourceType} to ${targetType}`
      );
    }
    
    return coercer(value);
  }
  
  private readonly coercers = new Map<string, (v: unknown) => unknown>([
    ['STRING_TO_NUMBER', (v) => {
      const num = parseFloat(v as string);
      if (isNaN(num)) throw new Error('Invalid number');
      return num;
    }],
    
    ['STRING_TO_BOOLEAN', (v) => {
      const s = (v as string).toLowerCase();
      if (['true', '1', 'yes'].includes(s)) return true;
      if (['false', '0', 'no', ''].includes(s)) return false;
      throw new Error('Invalid boolean');
    }],
    
    ['STRING_TO_JSON', (v) => JSON.parse(v as string)],
    
    ['STRING_TO_ARRAY', (v) => (v as string).split(',').map(s => s.trim())],
    
    ['NUMBER_TO_STRING', (v) => String(v)],
    
    ['BOOLEAN_TO_STRING', (v) => String(v)],
    
    ['JSON_TO_STRING', (v) => JSON.stringify(v, null, 2)],
    
    ['ARRAY_TO_STRING', (v) => (v as unknown[]).join(', ')],
    
    ['ARRAY_TO_JSON', (v) => v],
    
    ['NUMBER_TO_BOOLEAN', (v) => (v as number) !== 0],
    
    ['ARRAY_TO_NUMBER', (v) => (v as unknown[]).length],
  ]);
  
  private detectType(value: unknown): DataType {
    if (value === null || value === undefined) return DataType.STRING;
    if (typeof value === 'string') return DataType.STRING;
    if (typeof value === 'number') return DataType.NUMBER;
    if (typeof value === 'boolean') return DataType.BOOLEAN;
    if (Array.isArray(value)) return DataType.ARRAY;
    if (typeof value === 'object') return DataType.JSON;
    return DataType.STRING;
  }
}
```

---

## Transform Engine

### Transform Implementation

```typescript
interface TransformEngine {
  apply(
    value: unknown,
    chain: TransformChain,
    context: VariableContext
  ): Promise<unknown>;
}

class TransformEngineImpl implements TransformEngine {
  async apply(
    value: unknown,
    chain: TransformChain,
    context: VariableContext
  ): Promise<unknown> {
    let result = value;
    
    for (const operation of chain.operations) {
      result = await this.applyOperation(result, operation, context);
    }
    
    return result;
  }
  
  private async applyOperation(
    value: unknown,
    op: TransformOperation,
    context: VariableContext
  ): Promise<unknown> {
    switch (op.type) {
      // String operations
      case TransformOperationType.TRIM:
        return String(value).trim();
      
      case TransformOperationType.UPPER:
        return String(value).toUpperCase();
      
      case TransformOperationType.LOWER:
        return String(value).toLowerCase();
      
      case TransformOperationType.SPLIT:
        return String(value).split(op.args?.delimiter as string || ',');
      
      case TransformOperationType.JOIN:
        return (value as unknown[]).join(op.args?.delimiter as string || ', ');
      
      case TransformOperationType.REPLACE:
        return String(value).replace(
          new RegExp(op.args?.pattern as string, op.args?.flags as string || 'g'),
          op.args?.replacement as string || ''
        );
      
      case TransformOperationType.SUBSTRING:
        return String(value).substring(
          op.args?.start as number || 0,
          op.args?.end as number
        );
      
      case TransformOperationType.TEMPLATE:
        return substituteVariables(
          op.args?.template as string,
          { ...context, value }
        );
      
      // JSON operations
      case TransformOperationType.PARSE:
        return JSON.parse(String(value));
      
      case TransformOperationType.STRINGIFY:
        return JSON.stringify(value, null, op.args?.indent as number);
      
      case TransformOperationType.JMESPATH:
        return jmespath.search(value, op.args?.expression as string);
      
      case TransformOperationType.GET:
        return this.getByPath(value, op.args?.path as string);
      
      // Array operations
      case TransformOperationType.FIRST:
        const firstCount = op.args?.count as number || 1;
        return firstCount === 1 
          ? (value as unknown[])[0]
          : (value as unknown[]).slice(0, firstCount);
      
      case TransformOperationType.LAST:
        const lastCount = op.args?.count as number || 1;
        return lastCount === 1
          ? (value as unknown[])[(value as unknown[]).length - 1]
          : (value as unknown[]).slice(-lastCount);
      
      case TransformOperationType.INDEX:
        return (value as unknown[])[op.args?.index as number];
      
      case TransformOperationType.MAP:
        const mapExpr = op.args?.expression as string;
        return (value as unknown[]).map((item, index) =>
          this.evaluateExpression(mapExpr, { item, index, context })
        );
      
      case TransformOperationType.FILTER:
        const filterExpr = op.args?.condition as string;
        return (value as unknown[]).filter((item, index) =>
          this.evaluateCondition(filterExpr, { item, index, context })
        );
      
      case TransformOperationType.FLATTEN:
        return (value as unknown[][]).flat(op.args?.depth as number || 1);
      
      case TransformOperationType.UNIQUE:
        return [...new Set(value as unknown[])];
      
      case TransformOperationType.SORT:
        const sortKey = op.args?.key as string;
        const sortDir = op.args?.direction as 'asc' | 'desc' || 'asc';
        return [...(value as unknown[])].sort((a, b) => {
          const aVal = sortKey ? this.getByPath(a, sortKey) : a;
          const bVal = sortKey ? this.getByPath(b, sortKey) : b;
          const cmp = aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
          return sortDir === 'desc' ? -cmp : cmp;
        });
      
      // Type conversions
      case TransformOperationType.TO_STRING:
        return String(value);
      
      case TransformOperationType.TO_NUMBER:
        return Number(value);
      
      case TransformOperationType.TO_BOOLEAN:
        return Boolean(value);
      
      case TransformOperationType.TO_ARRAY:
        return Array.isArray(value) ? value : [value];
      
      // Conditional
      case TransformOperationType.DEFAULT:
        return value ?? op.args?.value;
      
      case TransformOperationType.COALESCE:
        const sources = op.args?.sources as string[];
        for (const source of sources) {
          const resolved = resolveVariable(source, context);
          if (resolved !== undefined && resolved !== null) {
            return resolved;
          }
        }
        return value;
      
      default:
        throw exhaustiveCheck(op.type);
    }
  }
  
  private getByPath(obj: unknown, path: string): unknown {
    const parts = path.split('.');
    let current = obj;
    
    for (const part of parts) {
      if (current === null || current === undefined) return undefined;
      
      // Handle array index notation: items[0]
      const arrayMatch = part.match(/^(\w+)\[(\d+)\]$/);
      if (arrayMatch) {
        current = (current as Record<string, unknown>)[arrayMatch[1]];
        current = (current as unknown[])[parseInt(arrayMatch[2])];
      } else {
        current = (current as Record<string, unknown>)[part];
      }
    }
    
    return current;
  }
}
```

---

## Binding Validation

### Pre-execution Validation

```typescript
interface BindingValidator {
  validate(
    stage: Stage,
    availableOutputs: OutputRegistry
  ): BindingValidationResult;
}

interface BindingValidationResult {
  valid: boolean;
  errors: BindingValidationError[];
  warnings: BindingValidationWarning[];
  suggestions: BindingSuggestion[];
}

interface BindingValidationError {
  binding: string;
  parameter: string;
  error: string;
  code: BindingErrorCode;
}

enum BindingErrorCode {
  UNKNOWN_VARIABLE = 'UNKNOWN_VARIABLE',
  TYPE_MISMATCH = 'TYPE_MISMATCH',
  CIRCULAR_REFERENCE = 'CIRCULAR_REFERENCE',
  INVALID_TRANSFORM = 'INVALID_TRANSFORM',
  MISSING_REQUIRED = 'MISSING_REQUIRED',
}

interface BindingSuggestion {
  parameter: string;
  currentBinding: string;
  suggestedBinding: string;
  reason: string;
}

class BindingValidatorImpl implements BindingValidator {
  validate(
    stage: Stage,
    availableOutputs: OutputRegistry
  ): BindingValidationResult {
    const errors: BindingValidationError[] = [];
    const warnings: BindingValidationWarning[] = [];
    const suggestions: BindingSuggestion[] = [];
    
    const bindings = JSON.parse(stage.InputBindings || '{}');
    
    for (const [param, binding] of Object.entries(bindings)) {
      const config = this.parseBinding(binding);
      
      // 1. Check if source variable exists
      if (!this.variableExists(config.source, availableOutputs)) {
        errors.push({
          binding: config.source,
          parameter: param,
          error: `Variable not found: ${config.source}`,
          code: BindingErrorCode.UNKNOWN_VARIABLE,
        });
        
        // Suggest similar variables
        const similar = this.findSimilarVariables(config.source, availableOutputs);
        if (similar.length > 0) {
          suggestions.push({
            parameter: param,
            currentBinding: config.source,
            suggestedBinding: similar[0],
            reason: 'Did you mean this variable?',
          });
        }
      }
      
      // 2. Check for circular references
      if (this.hasCircularReference(stage, config.source)) {
        errors.push({
          binding: config.source,
          parameter: param,
          error: 'Circular reference detected',
          code: BindingErrorCode.CIRCULAR_REFERENCE,
        });
      }
      
      // 3. Validate transforms
      if (config.transform) {
        const transformErrors = this.validateTransforms(config.transform);
        errors.push(...transformErrors.map(e => ({
          binding: config.source,
          parameter: param,
          error: e,
          code: BindingErrorCode.INVALID_TRANSFORM,
        })));
      }
      
      // 4. Type compatibility check
      if (config.type) {
        const sourceType = this.getVariableType(config.source, availableOutputs);
        if (sourceType && !this.typesCompatible(sourceType, config.type)) {
          warnings.push({
            binding: config.source,
            parameter: param,
            warning: `Type ${sourceType} may not be compatible with expected ${config.type}`,
          });
        }
      }
    }
    
    return {
      valid: errors.length === 0,
      errors,
      warnings,
      suggestions,
    };
  }
}
```

---

## Output Registry

### Registry Implementation

```typescript
interface OutputRegistry {
  register(
    blockName: string,
    stageName: string,
    output: StageOutput
  ): void;
  
  get(path: string): unknown | undefined;
  
  has(path: string): boolean;
  
  getType(path: string): DataType | undefined;
  
  getAllPaths(): string[];
  
  toContext(): Record<string, unknown>;
}

class OutputRegistryImpl implements OutputRegistry {
  private readonly outputs = new Map<string, RegisteredOutput>();
  
  register(
    blockName: string,
    stageName: string,
    output: StageOutput
  ): void {
    const basePath = `${blockName}.${stageName}`;
    
    // Register main output
    this.outputs.set(`${basePath}.output`, {
      value: output.text ?? output.json ?? output.filePath,
      type: this.inferType(output),
    });
    
    // Register individual properties
    if (output.type === OutputType.FILE) {
      this.outputs.set(`${basePath}.file`, {
        value: output.filePath,
        type: DataType.STRING,
      });
    }
    
    if (output.metadata) {
      for (const [key, value] of Object.entries(output.metadata)) {
        this.outputs.set(`${basePath}.metadata.${key}`, {
          value,
          type: this.inferType({ json: value } as StageOutput),
        });
      }
    }
  }
  
  get(path: string): unknown | undefined {
    // Handle nested property access
    const parts = path.split('.');
    
    // Try exact match first
    const registered = this.outputs.get(path);
    if (registered) return registered.value;
    
    // Try prefix match with property access
    for (let i = parts.length - 1; i >= 2; i--) {
      const prefix = parts.slice(0, i).join('.');
      const suffix = parts.slice(i).join('.');
      
      const registered = this.outputs.get(prefix);
      if (registered && typeof registered.value === 'object') {
        return this.getProperty(registered.value, suffix);
      }
    }
    
    return undefined;
  }
  
  toContext(): Record<string, unknown> {
    const context: Record<string, unknown> = {};
    
    for (const [path, registered] of this.outputs) {
      this.setByPath(context, path, registered.value);
    }
    
    return context;
  }
}
```

---

## UI Components

### Binding Editor

```typescript
interface BindingEditorProps {
  stage: Stage;
  availableOutputs: OutputRegistry;
  onChange: (bindings: InputBindings) => void;
}
```

**Features:**
- Visual parameter list with binding inputs
- Variable autocomplete dropdown
- Transform builder (drag-drop operations)
- Type indicator badges
- Validation error display
- Preview resolved values

### Data Flow Visualizer

```typescript
interface DataFlowVisualizerProps {
  pipeline: Pipeline;
  selectedStage?: string;
}
```

**Features:**
- Visual graph of data flow
- Highlight active bindings
- Show types at each connection
- Animate data during execution
- Click to inspect binding details

### Transform Builder

```typescript
interface TransformBuilderProps {
  value: TransformChain;
  inputType: DataType;
  onChange: (chain: TransformChain) => void;
}
```

**Features:**
- Drag-drop transform operations
- Preview transform result
- Type checking between operations
- Reorder operations
- Add/remove operations

---

## Error Messages

### User-Friendly Errors

| Error Code | User Message |
|------------|--------------|
| UNKNOWN_VARIABLE | "Variable '{{path}}' not found. It may be from a stage that hasn't run yet or doesn't exist." |
| TYPE_MISMATCH | "Expected {{expected}} but got {{actual}}. Add a transform to convert the data." |
| CIRCULAR_REFERENCE | "This binding creates a circular reference. Stage cannot depend on its own output." |
| INVALID_TRANSFORM | "Transform '{{type}}' is not valid for {{dataType}} data." |
| MISSING_REQUIRED | "Required parameter '{{param}}' has no value. Set a default or ensure the source stage produces output." |

---

## Performance Targets

| Metric | Target |
|--------|--------|
| Binding resolution (per parameter) | < 5ms |
| Transform chain (5 operations) | < 20ms |
| Type coercion | < 1ms |
| Validation (10 bindings) | < 50ms |
| Autocomplete suggestions | < 100ms |

---

## Related Specs

- [Database Schema](./01-database-schema.md)
- [Variable Registry](./03-variable-registry.md)
- [Stage Executor](./04-stage-executor.md)
