# Component: Validation Runtime

**Parent:** [Automation Pipeline](./00-overview.md)  
**Version:** 1.0.0  
**Status:** Planned  
**Phase:** 2 - Stage Engine  

---

## Summary

Multi-language validation runtime for executing custom Golang, Python, and TypeScript validation scripts. Provides direct execution (no sandboxing) with standardized input/output contracts and integration with the pipeline variable system.

---

## User Stories

- As a user, I want to write validation scripts in my preferred language
- As a user, I want scripts to access the current output and variables
- As a user, I want clear pass/fail results with detailed error messages
- As a user, I want to reuse validation scripts across multiple stages
- As a user, I want scripts to execute quickly with minimal overhead

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     Validation Runtime                           │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                    Script Loader                             ││
│  │  • Load from database (ValidationScript table)              ││
│  │  • Write to temp directory                                  ││
│  │  • Manage script lifecycle                                  ││
│  └─────────────────────────────────────────────────────────────┘│
│                              │                                   │
│  ┌───────────────────────────┼───────────────────────────┐      │
│  │                           ▼                           │      │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐   │      │
│  │  │   Golang    │  │   Python    │  │ TypeScript  │   │      │
│  │  │   Executor  │  │   Executor  │  │   Executor  │   │      │
│  │  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘   │      │
│  │         │                │                │          │      │
│  │         ▼                ▼                ▼          │      │
│  │    go run          python3           bun/node        │      │
│  │    main.go         main.py           main.ts         │      │
│  └──────────────────────────────────────────────────────┘      │
│                              │                                   │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                  Result Parser                               ││
│  │  • Parse stdout JSON                                        ││
│  │  • Capture stderr for errors                                ││
│  │  • Enforce timeout limits                                   ││
│  │  • Validate result schema                                   ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
```

---

## Execution Model

### Direct Execution

Scripts run directly on the system **without sandboxing**:

| Language | Executor | Command |
|----------|----------|---------|
| Golang | go | `go run {file}.go` |
| Python | python3 | `python3 {file}.py` |
| TypeScript | bun | `bun run {file}.ts` |

### Security Considerations

⚠️ **Trust Model:** Direct execution means scripts have full system access. This is intentional for power users who need:
- File system access for validation
- Network access for external checks
- System command execution

**Mitigations:**
- Scripts are user-authored and stored in project database
- Execution limited to project owner's context
- Process timeout enforcement (default 30s)
- Memory limits via process flags where possible

---

## Input/Output Contract

### Input Format

Scripts receive a JSON file as the first argument:

```json
{
  "input": "<value to validate>",
  "args": {
    "customArg1": "value1",
    "maxLength": 1000
  },
  "context": {
    "pipeline": { "id": "...", "name": "..." },
    "block": { "id": "...", "name": "..." },
    "stage": { "id": "...", "name": "..." },
    "variables": {
      "prev.output": "...",
      "search.results": [...]
    }
  },
  "metadata": {
    "executionId": "...",
    "timestamp": "2026-01-30T14:30:00Z"
  }
}
```

### Output Format

Scripts must output JSON to stdout:

```json
{
  "passed": true,
  "errors": [],
  "warnings": ["Consider adding alt text to images"],
  "score": 0.95,
  "details": {
    "checksRun": 5,
    "checksPassed": 5,
    "suggestions": []
  }
}
```

### Output Schema

```typescript
interface ValidationResult {
  passed: boolean;              // Required: overall pass/fail
  errors: string[];             // Required: error messages (empty if passed)
  warnings?: string[];          // Optional: non-blocking warnings
  score?: number;               // Optional: 0.0 - 1.0 score
  details?: Record<string, unknown>;  // Optional: additional data
}
```

---

## Language Templates

### Golang Template

```go
// validators/html-check/main.go
package main

import (
    "encoding/json"
    "fmt"
    "os"
    "strings"
)

type Input struct {
    Input    string                 `json:"input"`
    Args     map[string]interface{} `json:"args"`
    Context  map[string]interface{} `json:"context"`
    Metadata map[string]interface{} `json:"metadata"`
}

type Result struct {
    Passed   bool                   `json:"passed"`
    Errors   []string               `json:"errors"`
    Warnings []string               `json:"warnings,omitempty"`
    Score    float64                `json:"score,omitempty"`
    Details  map[string]interface{} `json:"details,omitempty"`
}

func validate(input Input) Result {
    errors := []string{}
    warnings := []string{}
    
    // Example: Validate HTML structure
    html := input.Input
    
    if !strings.Contains(html, "<!DOCTYPE html>") {
        errors = append(errors, "Missing DOCTYPE declaration")
    }
    
    if !strings.Contains(html, "<html") {
        errors = append(errors, "Missing <html> tag")
    }
    
    if strings.Contains(html, "<img") && !strings.Contains(html, "alt=") {
        warnings = append(warnings, "Images should have alt attributes")
    }
    
    return Result{
        Passed:   len(errors) == 0,
        Errors:   errors,
        Warnings: warnings,
        Score:    1.0 - (float64(len(errors)) * 0.2),
        Details: map[string]interface{}{
            "htmlLength": len(html),
            "hasDoctype": strings.Contains(html, "<!DOCTYPE"),
        },
    }
}

func main() {
    if len(os.Args) < 2 {
        fmt.Fprintln(os.Stderr, "Usage: go run main.go <input.json>")
        os.Exit(1)
    }
    
    // Read input file
    data, err := os.ReadFile(os.Args[1])
    if err != nil {
        fmt.Fprintf(os.Stderr, "Error reading input: %v\n", err)
        os.Exit(1)
    }
    
    var input Input
    if err := json.Unmarshal(data, &input); err != nil {
        fmt.Fprintf(os.Stderr, "Error parsing input: %v\n", err)
        os.Exit(1)
    }
    
    // Run validation
    result := validate(input)
    
    // Output result as JSON
    output, _ := json.Marshal(result)
    fmt.Println(string(output))
}
```

### Python Template

```python
#!/usr/bin/env python3
# validators/json-schema/main.py

import json
import sys
from typing import Any

def validate(input_data: str, args: dict, context: dict) -> dict:
    """
    Validate the input data.
    
    Args:
        input_data: The value to validate (from previous stage)
        args: Custom arguments from stage config
        context: Pipeline context including variables
    
    Returns:
        dict with passed, errors, warnings, score, details
    """
    errors = []
    warnings = []
    
    # Example: Validate JSON structure
    try:
        parsed = json.loads(input_data) if isinstance(input_data, str) else input_data
    except json.JSONDecodeError as e:
        return {
            "passed": False,
            "errors": [f"Invalid JSON: {str(e)}"],
            "score": 0.0
        }
    
    # Check required fields from args
    required_fields = args.get("requiredFields", [])
    for field in required_fields:
        if field not in parsed:
            errors.append(f"Missing required field: {field}")
    
    # Check max depth
    max_depth = args.get("maxDepth", 10)
    actual_depth = calculate_depth(parsed)
    if actual_depth > max_depth:
        warnings.append(f"JSON depth ({actual_depth}) exceeds recommended max ({max_depth})")
    
    return {
        "passed": len(errors) == 0,
        "errors": errors,
        "warnings": warnings,
        "score": 1.0 - (len(errors) * 0.25),
        "details": {
            "fieldCount": len(parsed) if isinstance(parsed, dict) else None,
            "depth": actual_depth,
            "type": type(parsed).__name__
        }
    }

def calculate_depth(obj: Any, current: int = 0) -> int:
    if isinstance(obj, dict):
        return max((calculate_depth(v, current + 1) for v in obj.values()), default=current)
    elif isinstance(obj, list):
        return max((calculate_depth(item, current + 1) for item in obj), default=current)
    return current

def main():
    if len(sys.argv) < 2:
        print("Usage: python main.py <input.json>", file=sys.stderr)
        sys.exit(1)
    
    # Read input file
    with open(sys.argv[1], 'r') as f:
        data = json.load(f)
    
    # Run validation
    result = validate(
        input_data=data["input"],
        args=data.get("args", {}),
        context=data.get("context", {})
    )
    
    # Output result as JSON
    print(json.dumps(result))

if __name__ == "__main__":
    main()
```

### TypeScript Template

```typescript
#!/usr/bin/env bun
// validators/api-response/main.ts

interface ValidationInput {
  input: unknown;
  args: Record<string, unknown>;
  context: {
    pipeline: { id: string; name: string };
    block: { id: string; name: string };
    stage: { id: string; name: string };
    variables: Record<string, unknown>;
  };
  metadata: {
    executionId: string;
    timestamp: string;
  };
}

interface ValidationResult {
  passed: boolean;
  errors: string[];
  warnings?: string[];
  score?: number;
  details?: Record<string, unknown>;
}

function validate(data: ValidationInput): ValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];
  
  const input = data.input as Record<string, unknown>;
  const args = data.args;
  
  // Example: Validate API response structure
  if (typeof input !== 'object' || input === null) {
    return {
      passed: false,
      errors: ['Input must be an object'],
      score: 0,
    };
  }
  
  // Check for required status field
  if (!('status' in input)) {
    errors.push('Missing "status" field in response');
  } else if (typeof input.status !== 'number') {
    errors.push('"status" must be a number');
  }
  
  // Check for data field
  if (!('data' in input)) {
    warnings.push('Response has no "data" field');
  }
  
  // Check expected status codes
  const expectedStatus = args.expectedStatus as number[] || [200];
  if ('status' in input && !expectedStatus.includes(input.status as number)) {
    errors.push(`Unexpected status: ${input.status}, expected one of: ${expectedStatus.join(', ')}`);
  }
  
  return {
    passed: errors.length === 0,
    errors,
    warnings,
    score: 1.0 - (errors.length * 0.3),
    details: {
      hasStatus: 'status' in input,
      hasData: 'data' in input,
      statusValue: input.status,
      fieldCount: Object.keys(input).length,
    },
  };
}

async function main() {
  const inputPath = process.argv[2];
  
  if (!inputPath) {
    console.error('Usage: bun run main.ts <input.json>');
    process.exit(1);
  }
  
  // Read input file
  const file = Bun.file(inputPath);
  const data: ValidationInput = await file.json();
  
  // Run validation
  const result = validate(data);
  
  // Output result as JSON
  console.log(JSON.stringify(result));
}

main().catch((err) => {
  console.error('Validation error:', err);
  process.exit(1);
});
```

---

## Runtime Implementation

### Core Interfaces

```typescript
interface ValidationRuntime {
  execute(
    script: ValidationScript,
    input: ValidationInput
  ): Promise<ValidationResult>;
  
  validateScript(script: ValidationScript): ScriptValidation;
  
  getExecutor(language: ValidationLanguage): LanguageExecutor;
}

interface LanguageExecutor {
  readonly language: ValidationLanguage;
  readonly command: string;
  readonly fileExtension: string;
  
  execute(
    scriptPath: string,
    inputPath: string,
    timeout: number
  ): Promise<ExecutionResult>;
  
  isAvailable(): Promise<boolean>;
}

interface ExecutionResult {
  exitCode: number;
  stdout: string;
  stderr: string;
  durationMs: number;
  timedOut: boolean;
}
```

### Runtime Implementation

```typescript
class ValidationRuntimeImpl implements ValidationRuntime {
  private readonly executors: Map<ValidationLanguage, LanguageExecutor>;
  private readonly tempDir: string;
  private readonly defaultTimeout: number = 30000; // 30 seconds
  
  constructor() {
    this.executors = new Map([
      [ValidationLanguage.GOLANG, new GolangExecutor()],
      [ValidationLanguage.PYTHON, new PythonExecutor()],
      [ValidationLanguage.TYPESCRIPT, new TypeScriptExecutor()],
    ]);
    this.tempDir = path.join(os.tmpdir(), 'pipeline-validators');
  }
  
  async execute(
    script: ValidationScript,
    input: ValidationInput
  ): Promise<ValidationResult> {
    const executor = this.executors.get(script.Language);
    if (!executor) {
      throw new Error(`Unsupported language: ${script.Language}`);
    }
    
    // 1. Check executor availability
    const available = await executor.isAvailable();
    if (!available) {
      throw new Error(`${script.Language} runtime not available`);
    }
    
    // 2. Create temp directory for this execution
    const execId = crypto.randomUUID();
    const execDir = path.join(this.tempDir, execId);
    await fs.mkdir(execDir, { recursive: true });
    
    try {
      // 3. Write script to temp file
      const scriptPath = path.join(
        execDir,
        `main${executor.fileExtension}`
      );
      await fs.writeFile(scriptPath, script.SourceCode);
      
      // 4. Write input to temp file
      const inputPath = path.join(execDir, 'input.json');
      await fs.writeFile(inputPath, JSON.stringify(input));
      
      // 5. Execute script
      const result = await executor.execute(
        scriptPath,
        inputPath,
        this.defaultTimeout
      );
      
      // 6. Handle timeout
      if (result.timedOut) {
        return {
          passed: false,
          errors: [`Validation timed out after ${this.defaultTimeout}ms`],
          warnings: [],
          score: 0,
          details: { timedOut: true, stderr: result.stderr },
        };
      }
      
      // 7. Handle non-zero exit
      if (result.exitCode !== 0) {
        return {
          passed: false,
          errors: [`Script exited with code ${result.exitCode}: ${result.stderr}`],
          warnings: [],
          score: 0,
          details: { exitCode: result.exitCode, stderr: result.stderr },
        };
      }
      
      // 8. Parse stdout as JSON
      try {
        const output = JSON.parse(result.stdout);
        return this.normalizeResult(output, result.durationMs);
      } catch (parseError) {
        return {
          passed: false,
          errors: [`Failed to parse output: ${parseError.message}`],
          warnings: [],
          score: 0,
          details: { stdout: result.stdout, parseError: parseError.message },
        };
      }
      
    } finally {
      // 9. Cleanup temp directory
      await fs.rm(execDir, { recursive: true, force: true });
    }
  }
  
  private normalizeResult(
    output: unknown,
    durationMs: number
  ): ValidationResult {
    // Validate output schema
    if (typeof output !== 'object' || output === null) {
      return {
        passed: false,
        errors: ['Output must be an object'],
        durationMs,
      };
    }
    
    const result = output as Record<string, unknown>;
    
    return {
      passed: Boolean(result.passed),
      errors: Array.isArray(result.errors) ? result.errors : [],
      warnings: Array.isArray(result.warnings) ? result.warnings : [],
      score: typeof result.score === 'number' 
        ? Math.max(0, Math.min(1, result.score)) 
        : undefined,
      details: typeof result.details === 'object' ? result.details : undefined,
      durationMs,
    };
  }
}
```

### Language Executors

```typescript
class GolangExecutor implements LanguageExecutor {
  readonly language = ValidationLanguage.GOLANG;
  readonly command = 'go';
  readonly fileExtension = '.go';
  
  async execute(
    scriptPath: string,
    inputPath: string,
    timeout: number
  ): Promise<ExecutionResult> {
    const startTime = Date.now();
    
    return new Promise((resolve) => {
      const process = spawn('go', ['run', scriptPath, inputPath], {
        cwd: path.dirname(scriptPath),
        timeout,
      });
      
      let stdout = '';
      let stderr = '';
      let timedOut = false;
      
      process.stdout.on('data', (data) => { stdout += data; });
      process.stderr.on('data', (data) => { stderr += data; });
      
      process.on('close', (code) => {
        resolve({
          exitCode: code ?? 1,
          stdout: stdout.trim(),
          stderr: stderr.trim(),
          durationMs: Date.now() - startTime,
          timedOut,
        });
      });
      
      process.on('error', (err) => {
        if (err.message.includes('ETIMEDOUT')) {
          timedOut = true;
        }
        resolve({
          exitCode: 1,
          stdout: '',
          stderr: err.message,
          durationMs: Date.now() - startTime,
          timedOut,
        });
      });
    });
  }
  
  async isAvailable(): Promise<boolean> {
    try {
      const { stdout } = await execAsync('go version');
      return stdout.includes('go version');
    } catch {
      return false;
    }
  }
}

class PythonExecutor implements LanguageExecutor {
  readonly language = ValidationLanguage.PYTHON;
  readonly command = 'python3';
  readonly fileExtension = '.py';
  
  async execute(
    scriptPath: string,
    inputPath: string,
    timeout: number
  ): Promise<ExecutionResult> {
    // Similar implementation with python3 command
    const startTime = Date.now();
    
    return new Promise((resolve) => {
      const process = spawn('python3', [scriptPath, inputPath], {
        cwd: path.dirname(scriptPath),
        timeout,
      });
      
      // ... same pattern as GolangExecutor
    });
  }
  
  async isAvailable(): Promise<boolean> {
    try {
      const { stdout } = await execAsync('python3 --version');
      return stdout.includes('Python 3');
    } catch {
      return false;
    }
  }
}

class TypeScriptExecutor implements LanguageExecutor {
  readonly language = ValidationLanguage.TYPESCRIPT;
  readonly command = 'bun';
  readonly fileExtension = '.ts';
  
  async execute(
    scriptPath: string,
    inputPath: string,
    timeout: number
  ): Promise<ExecutionResult> {
    const startTime = Date.now();
    
    return new Promise((resolve) => {
      // Try bun first, fall back to npx ts-node
      const process = spawn('bun', ['run', scriptPath, inputPath], {
        cwd: path.dirname(scriptPath),
        timeout,
      });
      
      // ... same pattern
    });
  }
  
  async isAvailable(): Promise<boolean> {
    try {
      // Check for bun first
      const { stdout: bunVersion } = await execAsync('bun --version');
      if (bunVersion) return true;
    } catch {}
    
    try {
      // Fall back to node + tsx
      const { stdout: nodeVersion } = await execAsync('node --version');
      return nodeVersion.includes('v');
    } catch {
      return false;
    }
  }
}
```

---

## Script Management

### Script Storage

```typescript
interface ScriptManager {
  create(script: CreateScriptRequest): Promise<ValidationScript>;
  update(id: string, updates: UpdateScriptRequest): Promise<ValidationScript>;
  delete(id: string): Promise<void>;
  get(id: string): Promise<ValidationScript>;
  list(projectId: string, filters?: ScriptFilters): Promise<ValidationScript[]>;
  duplicate(id: string, newName: string): Promise<ValidationScript>;
  export(ids: string[]): Promise<Buffer>;  // ZIP export
  import(projectId: string, zipFile: Buffer): Promise<ValidationScript[]>;
}

interface CreateScriptRequest {
  projectId: string;
  name: string;
  description?: string;
  language: ValidationLanguage;
  sourceCode: string;
  entryFunction?: string;
  inputSchema?: JSONSchema;
  outputSchema?: JSONSchema;
}
```

### Script Validation

```typescript
interface ScriptValidation {
  valid: boolean;
  errors: ScriptValidationError[];
  warnings: ScriptValidationWarning[];
}

interface ScriptValidationError {
  line?: number;
  column?: number;
  message: string;
  code: string;
}

function validateScript(script: ValidationScript): ScriptValidation {
  const errors: ScriptValidationError[] = [];
  const warnings: ScriptValidationWarning[] = [];
  
  // 1. Check for entry function
  const hasEntryPoint = checkEntryPoint(script);
  if (!hasEntryPoint) {
    errors.push({
      message: `Missing entry function: ${script.EntryFunction || 'validate'}`,
      code: 'MISSING_ENTRY',
    });
  }
  
  // 2. Check for required imports/packages
  const missingImports = checkRequiredImports(script);
  for (const imp of missingImports) {
    warnings.push({
      message: `Recommended import missing: ${imp}`,
      code: 'MISSING_IMPORT',
    });
  }
  
  // 3. Language-specific syntax check
  const syntaxErrors = checkSyntax(script);
  errors.push(...syntaxErrors);
  
  // 4. Check output format compliance
  const outputCompliance = checkOutputCompliance(script);
  if (!outputCompliance.valid) {
    warnings.push({
      message: 'Script may not produce valid output format',
      code: 'OUTPUT_FORMAT',
    });
  }
  
  return {
    valid: errors.length === 0,
    errors,
    warnings,
  };
}
```

---

## Built-in Validators

Pre-packaged validators available by default:

### HTML Validator

```typescript
const HTML_VALIDATOR: ValidationScript = {
  Id: 'builtin-html-validator',
  Name: 'HTML Structure Validator',
  Language: ValidationLanguage.TYPESCRIPT,
  SourceCode: `/* built-in HTML validator */`,
  EntryFunction: 'validate',
  InputSchema: { type: 'string', description: 'HTML content to validate' },
};
```

### JSON Schema Validator

```typescript
const JSON_SCHEMA_VALIDATOR: ValidationScript = {
  Id: 'builtin-json-schema',
  Name: 'JSON Schema Validator',
  Language: ValidationLanguage.PYTHON,
  SourceCode: `/* built-in JSON schema validator */`,
  EntryFunction: 'validate',
};
```

### Code Syntax Validator

```typescript
const CODE_SYNTAX_VALIDATOR: ValidationScript = {
  Id: 'builtin-code-syntax',
  Name: 'Code Syntax Validator',
  Language: ValidationLanguage.GOLANG,
  SourceCode: `/* built-in syntax validator */`,
  EntryFunction: 'validate',
};
```

| Built-in | Language | Validates |
|----------|----------|-----------|
| HTML Validator | TypeScript | HTML structure, accessibility |
| JSON Schema | Python | JSON against schema |
| Code Syntax | Golang | Multi-language syntax |
| URL Validator | TypeScript | URL format, reachability |
| Markdown Lint | Python | Markdown structure |
| API Response | TypeScript | HTTP response format |

---

## UI Components

### Script Editor

```typescript
interface ScriptEditorProps {
  script?: ValidationScript;
  projectId: string;
  onSave: (script: ValidationScript) => void;
  onCancel: () => void;
}
```

**Features:**
- Monaco editor with language-specific syntax highlighting
- Template insertion (boilerplate code)
- Input/output schema definition
- Test execution with sample input
- Validation error display

### Script Library

```typescript
interface ScriptLibraryProps {
  projectId: string;
  onSelectScript: (script: ValidationScript) => void;
  selectedScriptId?: string;
}
```

**Features:**
- List all project validators
- Filter by language
- Search by name/description
- Preview script content
- Import/export functionality

### Test Runner Panel

```typescript
interface TestRunnerProps {
  script: ValidationScript;
  defaultInput?: unknown;
}
```

**Features:**
- JSON input editor
- Run button with loading state
- Result display (pass/fail)
- Stdout/stderr tabs
- Duration and metrics
- Re-run with modified input

---

## Error Handling

### Error Categories

```typescript
enum ValidationErrorCategory {
  RUNTIME_UNAVAILABLE = 'RUNTIME_UNAVAILABLE',
  SCRIPT_ERROR = 'SCRIPT_ERROR',
  TIMEOUT = 'TIMEOUT',
  OUTPUT_PARSE = 'OUTPUT_PARSE',
  SCHEMA_VIOLATION = 'SCHEMA_VIOLATION',
}

interface ValidationExecutionError {
  category: ValidationErrorCategory;
  message: string;
  details: {
    language?: ValidationLanguage;
    exitCode?: number;
    stderr?: string;
    stdout?: string;
    line?: number;
  };
  recoverable: boolean;
}
```

### Recovery Strategies

| Error | Strategy |
|-------|----------|
| Runtime unavailable | Return error, suggest install |
| Script syntax error | Return with line number |
| Timeout | Return partial result if available |
| Output parse error | Return raw stdout in details |
| Non-zero exit | Return stderr as error |

---

## Performance Targets

| Metric | Target |
|--------|--------|
| Script startup (Golang) | < 500ms |
| Script startup (Python) | < 300ms |
| Script startup (TypeScript/Bun) | < 200ms |
| Small validation (< 1KB input) | < 1s |
| Medium validation (< 100KB input) | < 5s |
| Large validation (< 1MB input) | < 15s |
| Temp file cleanup | < 50ms |

---

## Related Specs

- [Database Schema](./01-database-schema.md)
- [Stage Executor](./04-stage-executor.md)
- [Variable Registry](./03-variable-registry.md)
