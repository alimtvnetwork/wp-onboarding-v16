# Build Verification

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Build Verification integrates with the `brun` CLI to validate generated code after the writing phase. It executes language-specific build checks and triggers an AI fix loop when errors are detected.

**Cross-References:**
- [Architecture](./01-architecture.md)
- [Parallel Code Generation](./03-parallel-code-generation.md)
- [Build Runner CLI](../23-build-runner-cli/00-overview.md)

---

## Verification Workflow

```
┌─────────────────────────────────────────────────────────────────────┐
│                    BUILD VERIFICATION FLOW                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────┐                                                   │
│  │ Code Writing │                                                   │
│  │   Complete   │                                                   │
│  └──────┬───────┘                                                   │
│         │                                                           │
│         ▼                                                           │
│  ┌──────────────┐     ┌──────────────┐                             │
│  │ Consistency  │────▶│ All Checks   │──── Yes ───┐                │
│  │    Check     │     │   Passed?    │            │                │
│  └──────────────┘     └──────┬───────┘            │                │
│                              │ No                  │                │
│                              ▼                     │                │
│                       ┌──────────────┐            │                │
│                       │ AI Fix Loop  │            │                │
│                       │ (max 3 tries)│            │                │
│                       └──────┬───────┘            │                │
│                              │                     │                │
│         ┌────────────────────┴────────────────────┘                │
│         ▼                                                           │
│  ┌──────────────┐                                                   │
│  │  For Each    │                                                   │
│  │  Language    │◄─────────────────────────────┐                   │
│  └──────┬───────┘                              │                   │
│         │                                       │                   │
│         ▼                                       │                   │
│  ┌──────────────┐     ┌──────────────┐         │                   │
│  │ brun check   │────▶│   Build      │── No ───┤                   │
│  │ --lang {x}   │     │  Success?    │         │                   │
│  └──────────────┘     └──────┬───────┘         │                   │
│                              │ Yes              │                   │
│                              ▼                  │                   │
│                       ┌──────────────┐         │                   │
│                       │ More langs?  │── Yes ──┘                   │
│                       └──────┬───────┘                             │
│                              │ No                                   │
│                              ▼                                      │
│                       ┌──────────────┐                             │
│                       │  Git Commit  │                             │
│                       └──────────────┘                             │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Build Verifier Service

### Core Implementation

```go
type BuildVerifier struct {
    brunRunner    *BrunRunner
    errorParser   *BuildErrorParser
    aiFixService  *AIFixService
    creditTracker *CreditTracker
    config        *BuildVerifyConfig
}

type BuildVerifyConfig struct {
    MaxFixAttempts     int           // Default: 3
    FixTimeout         time.Duration // Default: 5m per attempt
    FailOnWarnings     bool          // Default: false
    ParallelLanguages  bool          // Default: false (sequential safer)
}

type BuildVerificationResult struct {
    Success         bool
    LanguageResults map[string]*LanguageBuildResult
    TotalErrors     int
    TotalWarnings   int
    FixAttempts     int
    Duration        time.Duration
}

type LanguageBuildResult struct {
    Language    string
    Success     bool
    ExitCode    int
    Errors      []BuildError
    Warnings    []BuildWarning
    Command     string
    Duration    time.Duration
}

type BuildError struct {
    File        string
    Line        int
    Column      int
    Code        string
    Message     string
    Severity    string
    Suggestion  string
}

func NewBuildVerifier(
    brunRunner *BrunRunner,
    aiFixService *AIFixService,
    creditTracker *CreditTracker,
    config *BuildVerifyConfig,
) *BuildVerifier {
    if config == nil {
        config = &BuildVerifyConfig{
            MaxFixAttempts:    3,
            FixTimeout:        5 * time.Minute,
            FailOnWarnings:    false,
            ParallelLanguages: false,
        }
    }
    return &BuildVerifier{
        brunRunner:    brunRunner,
        aiFixService:  aiFixService,
        creditTracker: creditTracker,
        config:        config,
    }
}
```

### Verification Execution

```go
func (v *BuildVerifier) Verify(
    ctx context.Context,
    repoPath string,
    languages []string,
    runID string,
) (*BuildVerificationResult, error) {
    
    result := &BuildVerificationResult{
        LanguageResults: make(map[string]*LanguageBuildResult),
    }
    
    startTime := time.Now()
    
    for _, lang := range languages {
        langResult := v.verifyLanguage(ctx, repoPath, lang)
        result.LanguageResults[lang] = langResult
        
        if !langResult.Success {
            result.TotalErrors += len(langResult.Errors)
            
            // Attempt AI fix loop
            fixed := v.attemptFix(ctx, repoPath, lang, langResult, runID)
            if !fixed {
                result.Success = false
                result.Duration = time.Since(startTime)
                return result, nil
            }
            
            // Update result after successful fix
            result.LanguageResults[lang] = v.verifyLanguage(ctx, repoPath, lang)
        }
        
        result.TotalWarnings += len(langResult.Warnings)
    }
    
    result.Success = true
    result.Duration = time.Since(startTime)
    
    // Track credits for build cycle
    v.creditTracker.Consume(CreditConsumption{
        Type:   CreditTypeBuildCycle,
        Amount: v.config.BuildCycleCredit,
        Metadata: map[string]interface{}{
            "run_id":    runID,
            "languages": languages,
            "attempts":  result.FixAttempts,
        },
    })
    
    return result, nil
}

func (v *BuildVerifier) verifyLanguage(
    ctx context.Context,
    repoPath string,
    lang string,
) *LanguageBuildResult {
    
    langDir := v.getLanguageDir(repoPath, lang)
    
    checkOpts := CheckOptions{
        WorkDir:  langDir,
        Language: lang,
        JSON:     true,
        Timeout:  2 * time.Minute,
    }
    
    startTime := time.Now()
    output, err := v.brunRunner.Check(ctx, checkOpts)
    duration := time.Since(startTime)
    
    result := &LanguageBuildResult{
        Language: lang,
        Duration: duration,
        Command:  fmt.Sprintf("brun check --lang %s --json", lang),
    }
    
    if err != nil {
        result.Success = false
        result.ExitCode = getExitCode(err)
        result.Errors = v.errorParser.Parse(output, lang)
        return result
    }
    
    // Parse JSON output
    var checkResult BrunCheckResult
    if err := json.Unmarshal([]byte(output), &checkResult); err != nil {
        result.Success = false
        result.Errors = []BuildError{{Message: "Failed to parse brun output"}}
        return result
    }
    
    result.Success = checkResult.ExitCode == 0
    result.ExitCode = checkResult.ExitCode
    result.Errors = v.convertErrors(checkResult.Errors)
    result.Warnings = v.convertWarnings(checkResult.Warnings)
    
    return result
}

func (v *BuildVerifier) getLanguageDir(repoPath, lang string) string {
    switch lang {
    case "go", "golang":
        return filepath.Join(repoPath, "BE")
    case "react", "typescript", "javascript":
        return filepath.Join(repoPath, "FE")
    default:
        return repoPath
    }
}
```

---

## AI Fix Loop

### Fix Service

```go
type AIFixService struct {
    modelSelector *ModelSelector
    promptBuilder *FixPromptBuilder
    fileWriter    *FileWriter
}

type FixAttempt struct {
    AttemptNumber int
    Errors        []BuildError
    FilesFixed    []string
    TokensUsed    int
    Success       bool
    Duration      time.Duration
}

func (s *AIFixService) AttemptFix(
    ctx context.Context,
    repoPath string,
    lang string,
    errors []BuildError,
    guidelines *ResolvedGuidelines,
) (*FixAttempt, error) {
    
    attempt := &FixAttempt{
        Errors: errors,
    }
    startTime := time.Now()
    
    // Group errors by file
    errorsByFile := s.groupErrorsByFile(errors)
    
    for filePath, fileErrors := range errorsByFile {
        // Read current file content
        fullPath := filepath.Join(repoPath, filePath)
        content, err := os.ReadFile(fullPath)
        if err != nil {
            continue
        }
        
        // Build fix prompt
        prompt := s.promptBuilder.BuildFixPrompt(
            filePath,
            string(content),
            fileErrors,
            guidelines,
        )
        
        // Get fix from AI
        model, _ := s.modelSelector.SelectModel(lang, "fix")
        response, err := model.Generate(ctx, prompt)
        if err != nil {
            continue
        }
        
        // Extract fixed code
        fixedCode := extractCodeFromResponse(response, lang)
        
        // Write fixed file
        if err := s.fileWriter.Write(fullPath, fixedCode); err != nil {
            continue
        }
        
        attempt.FilesFixed = append(attempt.FilesFixed, filePath)
        attempt.TokensUsed += response.TokensUsed
    }
    
    attempt.Success = len(attempt.FilesFixed) > 0
    attempt.Duration = time.Since(startTime)
    
    return attempt, nil
}

func (s *AIFixService) groupErrorsByFile(errors []BuildError) map[string][]BuildError {
    result := make(map[string][]BuildError)
    for _, err := range errors {
        result[err.File] = append(result[err.File], err)
    }
    return result
}
```

### Fix Prompt Builder

```go
type FixPromptBuilder struct{}

const fixPromptTemplate = `
# Build Error Fix Task

## Current File
**Path:** {{.FilePath}}

` + "```" + `{{.Language}}
{{.CurrentContent}}
` + "```" + `

## Build Errors to Fix

{{range .Errors}}
- **Line {{.Line}}**: {{.Message}}
  {{if .Code}}Error Code: {{.Code}}{{end}}
  {{if .Suggestion}}Suggestion: {{.Suggestion}}{{end}}
{{end}}

## Guidelines
{{.Guidelines}}

## Instructions

1. Fix ALL the build errors listed above
2. Do not change any functionality - only fix the errors
3. Maintain the same code style and formatting
4. Return the COMPLETE fixed file

## Output Format
Respond with ONLY the fixed code wrapped in a code block. No explanations.
`

func (b *FixPromptBuilder) BuildFixPrompt(
    filePath string,
    content string,
    errors []BuildError,
    guidelines *ResolvedGuidelines,
) string {
    
    data := struct {
        FilePath       string
        Language       string
        CurrentContent string
        Errors         []BuildError
        Guidelines     string
    }{
        FilePath:       filePath,
        Language:       getLanguageFromPath(filePath),
        CurrentContent: content,
        Errors:         errors,
        Guidelines:     guidelines.MergedContent,
    }
    
    var buf bytes.Buffer
    tmpl := template.Must(template.New("fix").Parse(fixPromptTemplate))
    tmpl.Execute(&buf, data)
    return buf.String()
}
```

### Fix Loop with Retry

```go
func (v *BuildVerifier) attemptFix(
    ctx context.Context,
    repoPath string,
    lang string,
    result *LanguageBuildResult,
    runID string,
) bool {
    
    for attempt := 1; attempt <= v.config.MaxFixAttempts; attempt++ {
        log.Printf("Fix attempt %d/%d for %s", attempt, v.config.MaxFixAttempts, lang)
        
        // Create fix context with timeout
        fixCtx, cancel := context.WithTimeout(ctx, v.config.FixTimeout)
        
        // Attempt fix
        fixResult, err := v.aiFixService.AttemptFix(
            fixCtx,
            repoPath,
            lang,
            result.Errors,
            v.guidelines,
        )
        cancel()
        
        if err != nil {
            log.Printf("Fix attempt failed: %v", err)
            continue
        }
        
        // Track credits for AI fix request
        v.creditTracker.Consume(CreditConsumption{
            Type:   CreditTypeAIRequest,
            Amount: calculateTokenCredit(fixResult.TokensUsed),
            Metadata: map[string]interface{}{
                "run_id":  runID,
                "attempt": attempt,
                "type":    "build_fix",
            },
        })
        
        // Re-verify
        newResult := v.verifyLanguage(ctx, repoPath, lang)
        if newResult.Success {
            log.Printf("Build fixed after %d attempts", attempt)
            return true
        }
        
        // Update errors for next attempt
        result = newResult
    }
    
    log.Printf("Build fix failed after %d attempts", v.config.MaxFixAttempts)
    return false
}
```

---

## brun CLI Integration

### BrunRunner Wrapper

```go
type BrunRunner struct {
    binaryPath string
    timeout    time.Duration
}

type CheckOptions struct {
    WorkDir   string
    Language  string
    JSON      bool
    Timeout   time.Duration
}

type BrunCheckResult struct {
    ExitCode int              `json:"exit_code"`
    Success  bool             `json:"success"`
    Language string           `json:"language"`
    Errors   []BrunError      `json:"errors"`
    Warnings []BrunWarning    `json:"warnings"`
    Duration float64          `json:"duration_ms"`
}

type BrunError struct {
    Code       string `json:"code"`
    Message    string `json:"message"`
    File       string `json:"file"`
    Line       int    `json:"line"`
    Column     int    `json:"column"`
    Severity   string `json:"severity"`
    Suggestion string `json:"suggestion,omitempty"`
}

func (r *BrunRunner) Check(ctx context.Context, opts CheckOptions) (string, error) {
    args := []string{"check"}
    
    if opts.Language != "" {
        args = append(args, "--lang", opts.Language)
    }
    if opts.JSON {
        args = append(args, "--json")
    }
    
    cmd := exec.CommandContext(ctx, r.binaryPath, args...)
    cmd.Dir = opts.WorkDir
    
    output, err := cmd.CombinedOutput()
    return string(output), err
}
```

### Language-Specific Commands

| Language | brun Command | Build Tool |
|----------|--------------|------------|
| Go | `brun check --lang go` | `go build ./...` |
| React/TS | `brun check --lang react` | `npm run build` / `tsc --noEmit` |
| Node.js | `brun check --lang node` | `npm run build` |

---

## Error Parser

```go
type BuildErrorParser struct {
    patterns map[string][]*ErrorPattern
}

type ErrorPattern struct {
    Regex       *regexp.Regexp
    FileGroup   int
    LineGroup   int
    ColumnGroup int
    MessageGroup int
    CodeGroup   int
}

// Go error patterns
var goErrorPatterns = []*ErrorPattern{
    {
        // Standard Go compiler error: file.go:10:5: error message
        Regex:        regexp.MustCompile(`^(.+\.go):(\d+):(\d+):\s*(.+)$`),
        FileGroup:    1,
        LineGroup:    2,
        ColumnGroup:  3,
        MessageGroup: 4,
    },
    {
        // Go vet error
        Regex:        regexp.MustCompile(`^(.+\.go):(\d+):\s*(.+)$`),
        FileGroup:    1,
        LineGroup:    2,
        MessageGroup: 3,
    },
}

// TypeScript error patterns
var tsErrorPatterns = []*ErrorPattern{
    {
        // TSC error: file.ts(10,5): error TS2322: message
        Regex:        regexp.MustCompile(`^(.+\.tsx?)\((\d+),(\d+)\):\s*error\s+(TS\d+):\s*(.+)$`),
        FileGroup:    1,
        LineGroup:    2,
        ColumnGroup:  3,
        CodeGroup:    4,
        MessageGroup: 5,
    },
}

func (p *BuildErrorParser) Parse(output string, lang string) []BuildError {
    patterns := p.patterns[lang]
    if patterns == nil {
        return []BuildError{{Message: output}}
    }
    
    var errors []BuildError
    lines := strings.Split(output, "\n")
    
    for _, line := range lines {
        for _, pattern := range patterns {
            matches := pattern.Regex.FindStringSubmatch(line)
            if matches != nil {
                err := BuildError{
                    File:    safeGet(matches, pattern.FileGroup),
                    Message: safeGet(matches, pattern.MessageGroup),
                    Code:    safeGet(matches, pattern.CodeGroup),
                }
                if pattern.LineGroup > 0 {
                    err.Line, _ = strconv.Atoi(safeGet(matches, pattern.LineGroup))
                }
                if pattern.ColumnGroup > 0 {
                    err.Column, _ = strconv.Atoi(safeGet(matches, pattern.ColumnGroup))
                }
                errors = append(errors, err)
                break
            }
        }
    }
    
    return errors
}
```

---

## Configuration

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `codegen.build.verifyAfterGeneration` | bool | true | Run verification after generation |
| `codegen.build.maxFixAttempts` | int | 3 | Maximum AI fix attempts |
| `codegen.build.fixTimeout` | duration | 5m | Timeout per fix attempt |
| `codegen.build.failOnWarnings` | bool | false | Fail build on warnings |
| `codegen.build.brunPath` | string | `brun` | Path to brun binary |

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 8500 | ERR_BUILD_VERIFICATION_FAILED | Build verification failed |
| 8501 | ERR_BUILD_BRUN_NOT_FOUND | brun CLI not found |
| 8502 | ERR_BUILD_BRUN_TIMEOUT | brun execution timeout |
| 8503 | ERR_BUILD_PARSE_FAILED | Failed to parse build output |
| 8504 | ERR_BUILD_FIX_FAILED | AI fix loop exhausted |
| 8505 | ERR_BUILD_LANGUAGE_UNSUPPORTED | Language not supported by brun |

---

## Related Specs

- [Architecture](./01-architecture.md)
- [Parallel Code Generation](./03-parallel-code-generation.md)
- [Build Runner CLI](../23-build-runner-cli/00-overview.md)
