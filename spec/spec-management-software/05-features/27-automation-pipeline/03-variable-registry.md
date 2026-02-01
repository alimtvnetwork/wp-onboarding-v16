# Component: Variable Registry

**Parent:** [Automation Pipeline](./00-overview.md)  
**Version:** 1.0.0  
**Status:** Planned  
**Phase:** 1 - Foundation  

---

## Summary

Extensible variable templating system for the Automation Pipeline. Provides variable definition, resolution, and binding across pipeline scopes with type validation and runtime substitution.

---

## User Stories

- As a user, I want to define variables at pipeline, block, and stage levels
- As a user, I want to reference previous stage outputs in subsequent stages
- As a user, I want built-in variables for common values (timestamp, IDs)
- As a user, I want type-safe variable validation
- As a user, I want to see available variables when configuring stages

---

## Variable Syntax

### Template Syntax

```
{{scope.path.to.value}}
```

**Examples:**
```
{{pipeline.name}}              // Pipeline-level variable
{{block.current.id}}           // Current block info
{{stage.prev.output}}          // Previous stage output
{{input.userText}}             // User-provided input
{{myBlock.generateHtml.output}}  // Specific stage output
{{env.API_KEY}}                // Environment variable
```

### Alternative Syntaxes (Supported)

| Syntax | Example | Use Case |
|--------|---------|----------|
| Double brace | `{{var}}` | Primary (recommended) |
| Dollar brace | `${var}` | Shell-style compatibility |
| Angle bracket | `<var>` | Template markers |

---

## Variable Scopes

### Scope Hierarchy

```
┌─────────────────────────────────────────────────────────┐
│                    GLOBAL SCOPE                          │
│  • Built-in system variables                             │
│  • Environment variables                                 │
│  ┌─────────────────────────────────────────────────────┐ │
│  │              PIPELINE SCOPE                          │ │
│  │  • Pipeline-level user variables                     │ │
│  │  • Input parameters                                  │ │
│  │  ┌─────────────────────────────────────────────────┐ │ │
│  │  │            BLOCK SCOPE                           │ │ │
│  │  │  • Block-level variables                         │ │ │
│  │  │  • Accumulated stage outputs                     │ │ │
│  │  │  ┌─────────────────────────────────────────────┐ │ │ │
│  │  │  │          STAGE SCOPE                         │ │ │ │
│  │  │  │  • Stage input/output                        │ │ │ │
│  │  │  │  • Previous stage reference                  │ │ │ │
│  │  │  └─────────────────────────────────────────────┘ │ │ │
│  │  └─────────────────────────────────────────────────┘ │ │
│  └─────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

### Resolution Order

1. **Stage scope** — Current stage variables
2. **Block scope** — Current block variables + previous stage outputs
3. **Pipeline scope** — Pipeline-level variables
4. **Global scope** — Built-in + environment variables

First match wins. Inner scopes shadow outer scopes.

---

## Built-in Variables

### System Variables

| Variable | Type | Description | Example Value |
|----------|------|-------------|---------------|
| `{{system.timestamp}}` | STRING | Current ISO timestamp | `2026-01-30T14:30:00Z` |
| `{{system.date}}` | STRING | Current date | `2026-01-30` |
| `{{system.time}}` | STRING | Current time | `14:30:00` |
| `{{system.uuid}}` | STRING | Generate new UUID | `a1b2c3d4-...` |
| `{{system.random}}` | NUMBER | Random 0-1 | `0.7423` |
| `{{system.epoch}}` | NUMBER | Unix timestamp | `1769859000` |

### Pipeline Context

| Variable | Type | Description |
|----------|------|-------------|
| `{{pipeline.id}}` | STRING | Current pipeline UUID |
| `{{pipeline.name}}` | STRING | Pipeline name |
| `{{pipeline.executionId}}` | STRING | Current execution UUID |
| `{{pipeline.startedAt}}` | STRING | Execution start time |
| `{{pipeline.trigger}}` | STRING | Trigger type (MANUAL, SCHEDULED, etc.) |

### Block Context

| Variable | Type | Description |
|----------|------|-------------|
| `{{block.id}}` | STRING | Current block UUID |
| `{{block.name}}` | STRING | Block name |
| `{{block.index}}` | NUMBER | Block execution order (0-based) |
| `{{block.isParallel}}` | BOOLEAN | Running in parallel group |

### Stage Context

| Variable | Type | Description |
|----------|------|-------------|
| `{{stage.id}}` | STRING | Current stage UUID |
| `{{stage.name}}` | STRING | Stage name |
| `{{stage.type}}` | STRING | Stage type (PROMPT, SEARCH, etc.) |
| `{{stage.index}}` | NUMBER | Stage order in block (0-based) |
| `{{stage.attempt}}` | NUMBER | Current retry attempt |

### Previous Stage

| Variable | Type | Description |
|----------|------|-------------|
| `{{prev.output}}` | ANY | Previous stage output (text or JSON) |
| `{{prev.file}}` | STRING | Previous stage output file path |
| `{{prev.status}}` | STRING | Previous stage status |
| `{{prev.duration}}` | NUMBER | Previous stage duration (ms) |

### Input Variables

| Variable | Type | Description |
|----------|------|-------------|
| `{{input.text}}` | STRING | User text input |
| `{{input.file}}` | STRING | User file input path |
| `{{input.voice}}` | STRING | Voice transcription |
| `{{input.*}}` | ANY | Custom input parameters |

### Search Results

| Variable | Type | Description |
|----------|------|-------------|
| `{{search.results}}` | ARRAY | Search result array |
| `{{search.count}}` | NUMBER | Number of results |
| `{{search.query}}` | STRING | Executed query |
| `{{search.topResult}}` | OBJECT | Highest-ranked result |

### AI Response

| Variable | Type | Description |
|----------|------|-------------|
| `{{ai.response}}` | STRING | AI model response text |
| `{{ai.model}}` | STRING | Model used |
| `{{ai.tokens}}` | NUMBER | Tokens consumed |
| `{{ai.finishReason}}` | STRING | Completion reason |

### Validation

| Variable | Type | Description |
|----------|------|-------------|
| `{{validation.passed}}` | BOOLEAN | Validation result |
| `{{validation.errors}}` | ARRAY | Error messages |
| `{{validation.warnings}}` | ARRAY | Warning messages |
| `{{validation.score}}` | NUMBER | Validation score (0-1) |

### Loop Variables

| Variable | Type | Description |
|----------|------|-------------|
| `{{loop.item}}` | ANY | Current iteration item |
| `{{loop.index}}` | NUMBER | Current index (0-based) |
| `{{loop.count}}` | NUMBER | Total items |
| `{{loop.isFirst}}` | BOOLEAN | First iteration |
| `{{loop.isLast}}` | BOOLEAN | Last iteration |

---

## Cross-Block References

Reference outputs from specific blocks/stages:

```
{{blockName.stageName.output}}
{{blockName.stageName.file}}
{{blockName.stageName.status}}
```

**Examples:**
```
{{dataFetch.searchWeb.results}}
{{htmlGeneration.generatePage.output}}
{{validation.checkHtml.passed}}
```

### Naming Rules

- Block and stage names are normalized to camelCase
- Spaces → removed, first letter lowercase
- Special characters → removed
- Numbers → kept if not leading

| Original Name | Normalized |
|---------------|------------|
| `Data Fetch` | `dataFetch` |
| `Generate HTML Page` | `generateHtmlPage` |
| `Step 1 - Init` | `step1Init` |

---

## Variable Definition

### Database Schema

```typescript
interface PipelineVariable {
  id: string;
  pipelineId: string;
  name: string;                    // Variable name (unique per pipeline)
  scope: VariableScope;
  dataType: VariableDataType;
  defaultValue?: string;           // JSON-encoded default
  description?: string;
  isRequired: boolean;
  validationPattern?: string;      // Regex for validation
  createdAt: string;
}

enum VariableScope {
  GLOBAL = 'GLOBAL',
  BLOCK = 'BLOCK',
  STAGE = 'STAGE',
}

enum VariableDataType {
  STRING = 'STRING',
  NUMBER = 'NUMBER',
  BOOLEAN = 'BOOLEAN',
  JSON = 'JSON',
  FILE = 'FILE',
  ARRAY = 'ARRAY',
}
```

### UI Form

```typescript
interface VariableFormProps {
  pipelineId: string;
  variable?: PipelineVariable;     // Edit mode if provided
  onSave: (variable: PipelineVariable) => void;
  onCancel: () => void;
}
```

**Form Fields:**
- Name (text, required, validated for format)
- Scope (select: GLOBAL, BLOCK, STAGE)
- Data Type (select: STRING, NUMBER, etc.)
- Default Value (type-appropriate input)
- Required (checkbox)
- Description (textarea)
- Validation Pattern (text, regex)

---

## Variable Resolution Engine

### Resolution Algorithm

```typescript
interface VariableContext {
  system: SystemVariables;
  pipeline: PipelineContext;
  block: BlockContext;
  stage: StageContext;
  inputs: Record<string, unknown>;
  outputs: Record<string, Record<string, unknown>>;  // blockName.stageName -> output
  env: Record<string, string>;
}

function resolveVariable(
  path: string,
  context: VariableContext
): unknown {
  const parts = path.split('.');
  const scope = parts[0];
  
  // 1. Check scope prefix
  switch (scope) {
    case 'system':
      return resolveSystemVariable(parts.slice(1));
    case 'pipeline':
      return resolvePipelineVariable(parts.slice(1), context.pipeline);
    case 'block':
      return resolveBlockVariable(parts.slice(1), context.block);
    case 'stage':
      return resolveStageVariable(parts.slice(1), context.stage);
    case 'prev':
      return resolvePrevVariable(parts.slice(1), context);
    case 'input':
      return resolveInputVariable(parts.slice(1), context.inputs);
    case 'env':
      return context.env[parts[1]];
    case 'search':
    case 'ai':
    case 'validation':
    case 'loop':
      return resolveSpecialVariable(scope, parts.slice(1), context);
    default:
      // Cross-block reference: blockName.stageName.property
      return resolveCrossBlockReference(parts, context.outputs);
  }
}
```

### Template Substitution

```typescript
function substituteVariables(
  template: string,
  context: VariableContext
): string {
  // Pattern for all supported syntaxes
  const patterns = [
    /\{\{([^}]+)\}\}/g,     // {{var}}
    /\$\{([^}]+)\}/g,       // ${var}
    /<([a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)*)>/g,  // <var>
  ];
  
  let result = template;
  
  for (const pattern of patterns) {
    result = result.replace(pattern, (match, path) => {
      try {
        const value = resolveVariable(path.trim(), context);
        return formatValue(value);
      } catch (error) {
        // Return original if unresolved (or throw based on config)
        return match;
      }
    });
  }
  
  return result;
}

function formatValue(value: unknown): string {
  if (value === null || value === undefined) return '';
  if (typeof value === 'object') return JSON.stringify(value);
  return String(value);
}
```

---

## Type Coercion

### Automatic Coercion

| Source Type | Target Type | Coercion |
|-------------|-------------|----------|
| STRING | NUMBER | parseFloat() |
| STRING | BOOLEAN | 'true'/'1' → true |
| NUMBER | STRING | String() |
| BOOLEAN | STRING | 'true'/'false' |
| JSON | STRING | JSON.stringify() |
| STRING | JSON | JSON.parse() |
| ARRAY | STRING | JSON.stringify() |
| ANY | FILE | Write to temp file |

### Explicit Coercion Functions

```
{{toString(variable)}}
{{toNumber(variable)}}
{{toBoolean(variable)}}
{{toJson(variable)}}
{{toArray(variable)}}
```

---

## Property Access

### Object Properties

```
{{result.data.items[0].name}}
{{response.headers['content-type']}}
{{array[{{loop.index}}]}}
```

### Array Operations

```
{{array.length}}
{{array.first}}
{{array.last}}
{{array[0]}}
{{array[-1]}}           // Last element
```

### String Operations

```
{{text.length}}
{{text.upper}}
{{text.lower}}
{{text.trim}}
{{text.split(',')}}
```

---

## Conditional Expressions

Basic conditionals in variable syntax:

```
{{variable || 'default'}}           // Default if empty
{{variable ?? 'default'}}           // Default if null/undefined
{{condition ? 'yes' : 'no'}}        // Ternary
```

**Examples:**
```
{{input.name || 'Anonymous'}}
{{prev.output ?? 'No output'}}
{{validation.passed ? 'Continue' : 'Retry'}}
```

---

## Validation

### Pre-execution Validation

```typescript
interface ValidationResult {
  valid: boolean;
  errors: VariableError[];
  warnings: VariableWarning[];
  unresolvedVariables: string[];
}

interface VariableError {
  variable: string;
  message: string;
  location: {
    blockId: string;
    stageId: string;
    field: string;
  };
}

function validatePipelineVariables(
  pipeline: Pipeline,
  inputValues: Record<string, unknown>
): ValidationResult {
  // 1. Check all required variables have values
  // 2. Validate types match expectations
  // 3. Check regex patterns pass
  // 4. Identify unresolved references
  // 5. Warn about potentially unused variables
}
```

### Runtime Validation

```typescript
function validateVariableValue(
  value: unknown,
  variable: PipelineVariable
): boolean {
  // 1. Check type matches dataType
  // 2. Apply validation pattern if exists
  // 3. Check required constraint
  return isValid;
}
```

---

## Variable Browser UI

### Component

```typescript
interface VariableBrowserProps {
  pipelineId: string;
  currentBlockId?: string;
  currentStageId?: string;
  onInsertVariable: (path: string) => void;
}
```

### Features

- **Tree View:** Expandable tree of all available variables
- **Scope Filtering:** Filter by scope (global, pipeline, block, stage)
- **Type Badges:** Visual indicators for data types
- **Search:** Filter variables by name
- **Preview:** Show current/default values
- **Insert:** Click to insert variable reference
- **Documentation:** Hover for description

### UI Layout

```
┌─────────────────────────────────────┐
│ 🔍 Search variables...              │
├─────────────────────────────────────┤
│ ▼ System                            │
│   ├── timestamp     STRING          │
│   ├── uuid          STRING          │
│   └── date          STRING          │
│ ▼ Pipeline                          │
│   ├── id            STRING          │
│   └── name          STRING          │
│ ▼ Block: dataFetch                  │
│   └── Stage: searchWeb              │
│       ├── output    JSON   [Insert] │
│       └── status    STRING [Insert] │
│ ▼ Previous Stage                    │
│   ├── output        ANY    [Insert] │
│   └── file          STRING [Insert] │
│ ▼ User Variables                    │
│   ├── apiKey *      STRING [Insert] │
│   └── maxResults    NUMBER [Insert] │
└─────────────────────────────────────┘
```

---

## Autocomplete

### In-Editor Autocomplete

When typing `{{` in any text field:

1. Show dropdown of available variables
2. Filter as user types
3. Show type and description
4. Insert on selection with closing `}}`

```typescript
interface AutocompleteItem {
  label: string;           // Display name
  insertText: string;      // Text to insert
  detail: string;          // Type info
  documentation: string;   // Description
  kind: 'variable' | 'function' | 'property';
}

function getVariableCompletions(
  context: VariableContext,
  prefix: string
): AutocompleteItem[] {
  // Return filtered list based on prefix
}
```

---

## Error Handling

### Error Types

```typescript
enum VariableErrorType {
  UNDEFINED = 'UNDEFINED',           // Variable not found
  TYPE_MISMATCH = 'TYPE_MISMATCH',   // Wrong type
  CIRCULAR = 'CIRCULAR',             // Circular reference
  INVALID_PATH = 'INVALID_PATH',     // Bad property access
  VALIDATION = 'VALIDATION',         // Pattern validation failed
}

interface VariableResolutionError {
  type: VariableErrorType;
  variable: string;
  message: string;
  suggestion?: string;
}
```

### Error Strategies

| Strategy | Behavior |
|----------|----------|
| STRICT | Throw on any error |
| LENIENT | Return empty string, log warning |
| DEFAULT | Return default value if defined |
| PRESERVE | Keep original `{{variable}}` text |

---

## Performance

### Caching

```typescript
interface VariableCache {
  // Cache resolved values during execution
  get(key: string): unknown | undefined;
  set(key: string, value: unknown, ttl?: number): void;
  invalidate(pattern: string): void;
  clear(): void;
}
```

### Optimization

- **Lazy Resolution:** Only resolve when accessed
- **Memoization:** Cache repeated resolutions
- **Batch Resolution:** Resolve all variables in a template at once
- **Pre-compilation:** Parse templates once, reuse pattern

### Targets

| Metric | Target |
|--------|--------|
| Single variable resolution | < 1ms |
| Template substitution (100 vars) | < 10ms |
| Variable browser load | < 50ms |
| Autocomplete response | < 100ms |

---

## Related Specs

- [Database Schema](./01-database-schema.md)
- [Prompt Import System](./02-prompt-import-system.md)
- [Stage Executor](./04-stage-executor.md)
- [Input Output Binding](./06-io-binding.md)
