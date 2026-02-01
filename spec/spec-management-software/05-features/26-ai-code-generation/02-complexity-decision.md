# 02. Complexity Decision

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  

---

## Purpose

Define the decision logic for determining when AI should generate Golang code versus using direct execution or existing tools.

---

## Decision Matrix

| Task Type | Complexity Indicators | Action |
|-----------|----------------------|--------|
| Single file edit | One file, <10 lines changed | Direct execution |
| Multiple file ops | 2+ files, pattern matching | Generate Go code |
| Batch operations | Loops, conditionals, transformations | Generate Go code |
| Data processing | Parse, transform, validate data | Generate Go code |
| Index operations | Read/write index files | Generate Go code |
| Complex logic | Multi-step workflows | Generate Go code |

---

## Complexity Score Formula

```go
type ComplexityFactors struct {
    FileCount           int  // Number of files affected
    HasPatternMatching  bool // Regex or glob patterns
    HasConditionalLogic bool // If/else, filters
    RequiresParsing     bool // JSON, YAML, MD parsing
    AffectsIndex        bool // Modifies index.json or overview files
    HasDependencies     bool // Multi-step with dependencies
    RequiresRollback    bool // Needs undo capability
}

func CalculateComplexityScore(factors ComplexityFactors) int {
    score := 0
    
    score += factors.FileCount * 2
    
    if factors.HasPatternMatching {
        score += 3
    }
    if factors.HasConditionalLogic {
        score += 2
    }
    if factors.RequiresParsing {
        score += 2
    }
    if factors.AffectsIndex {
        score += 3
    }
    if factors.HasDependencies {
        score += 2
    }
    if factors.RequiresRollback {
        score += 2
    }
    
    return score
}

const (
    ThresholdSimple  = 5  // Score < 5: Direct execution
    ThresholdComplex = 5  // Score >= 5: Generate Go code
)

func DetermineExecutionPath(score int) ExecutionPath {
    if score < ThresholdSimple {
        return ExecutionPathDirect
    }
    return ExecutionPathCodeGeneration
}
```

---

## Task Classification Examples

### Simple Tasks (Direct Execution)

| Task | Score | Reason |
|------|-------|--------|
| Create single file | 2 | 1 file × 2 |
| Delete single file | 2 | 1 file × 2 |
| Read file contents | 2 | 1 file × 2 |
| Rename single file | 2 | 1 file × 2 |

### Complex Tasks (Code Generation)

| Task | Score | Breakdown |
|------|-------|-----------|
| Lowercase all filenames | 7 | 2 files × 2 + pattern(3) |
| Rename by pattern | 9 | 3 files × 2 + pattern(3) |
| Update all index files | 11 | 3 files × 2 + index(3) + parsing(2) |
| Refactor imports | 13 | 4 files × 2 + pattern(3) + parsing(2) |
| Batch metadata update | 10 | 2 files × 2 + parsing(2) + conditional(2) + index(3) |

---

## Decision Flow

```
User Request Received
        │
        ▼
┌───────────────────────┐
│  Parse Request Intent │
└───────────┬───────────┘
            │
            ▼
┌───────────────────────┐
│ Extract Complexity    │
│      Factors          │
└───────────┬───────────┘
            │
            ▼
┌───────────────────────┐
│ Calculate Complexity  │
│      Score            │
└───────────┬───────────┘
            │
            ▼
    ┌───────┴───────┐
    │  Score >= 5?  │
    └───────┬───────┘
            │
     ┌──────┴──────┐
     │             │
    YES           NO
     │             │
     ▼             ▼
┌─────────┐  ┌───────────┐
│Generate │  │  Direct   │
│Go Code  │  │ Execution │
└─────────┘  └───────────┘
```

---

## Factor Detection Patterns

### FileCount Detection

```go
func DetectFileCount(request string) int {
    patterns := []string{
        `all files`,      // Multiple
        `every file`,     // Multiple
        `files in`,       // Multiple
        `directory`,      // Multiple
        `folder`,         // Multiple
        `*.md`,           // Glob pattern
        `**/*.go`,        // Recursive glob
    }
    
    for _, pattern := range patterns {
        if strings.Contains(strings.ToLower(request), pattern) {
            return 3 // Estimate multiple files
        }
    }
    
    return 1 // Single file assumed
}
```

### Pattern Matching Detection

```go
func HasPatternMatching(request string) bool {
    indicators := []string{
        `pattern`,
        `matching`,
        `regex`,
        `glob`,
        `*.`,
        `**`,
        `starts with`,
        `ends with`,
        `contains`,
        `by name`,
    }
    
    for _, indicator := range indicators {
        if strings.Contains(strings.ToLower(request), indicator) {
            return true
        }
    }
    
    return false
}
```

### Index Modification Detection

```go
func AffectsIndex(request string) bool {
    indicators := []string{
        `index`,
        `overview`,
        `master`,
        `update references`,
        `cross-reference`,
        `table of contents`,
        `00-overview`,
        `00-master`,
    }
    
    for _, indicator := range indicators {
        if strings.Contains(strings.ToLower(request), indicator) {
            return true
        }
    }
    
    return false
}
```

---

## Override Mechanisms

### Force Code Generation

User can explicitly request code generation:

```
"Generate Golang code to rename this file"
"Create a CLI to process these files"
"Write a script to..."
```

### Force Direct Execution

User can explicitly request direct execution:

```
"Just rename this file directly"
"Simply delete without code generation"
"Quick edit: change X to Y"
```

---

## TypeScript Types

```typescript
enum ExecutionPath {
  Direct = "direct",
  CodeGeneration = "code_generation",
}

interface ComplexityFactors {
  readonly fileCount: number;
  readonly hasPatternMatching: boolean;
  readonly hasConditionalLogic: boolean;
  readonly requiresParsing: boolean;
  readonly affectsIndex: boolean;
  readonly hasDependencies: boolean;
  readonly requiresRollback: boolean;
}

interface ComplexityResult {
  readonly score: number;
  readonly factors: ComplexityFactors;
  readonly executionPath: ExecutionPath;
  readonly reasoning: string;
}
```

---

## Related Specs

- [01-system-overview.md](./01-system-overview.md) — Architecture context
- [03-code-generator.md](./03-code-generator.md) — Generation engine
- [06-execution-engine.md](./06-execution-engine.md) — Execution paths
