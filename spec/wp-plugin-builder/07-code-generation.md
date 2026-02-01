# Code Generation

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-02-01  

---

## Overview

AI-powered PHP code generation for WordPress plugins, using RAG context and coding guidelines for consistent, high-quality output.

**Cross-References:**
- [RAG System](./05-rag-system.md)
- [Spec Processing](./08-spec-processing.md)
- [Coding Guidelines](./12-coding-guidelines.md)
- [AI Bridge](../ai-bridge/00-overview.md)

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                      CODE GENERATION PIPELINE                            │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                │
│   │    Spec     │───▶│   Parse &   │───▶│   Build     │                │
│   │   Input     │    │   Analyze   │    │   Prompt    │                │
│   └─────────────┘    └─────────────┘    └──────┬──────┘                │
│                                                 │                        │
│                                                 ▼                        │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                │
│   │    RAG      │───▶│   Inject    │───▶│  AI Bridge  │                │
│   │   Query     │    │   Context   │    │   Request   │                │
│   └─────────────┘    └─────────────┘    └──────┬──────┘                │
│                                                 │                        │
│                                                 ▼                        │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                │
│   │   Write     │◀───│   Validate  │◀───│   Parse     │                │
│   │   Files     │    │   Output    │    │  Response   │                │
│   └─────────────┘    └─────────────┘    └─────────────┘                │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Code Generator

```go
type CodeGenerator struct {
    ragService   *RAGService
    aiBridge     *AIBridgeClient
    validator    *CodeValidator
    guidelines   *CodingGuidelines
    config       GenerationConfig
}

type GenerationConfig struct {
    OutputDir     string
    OverwriteMode string // skip, overwrite, backup
    Validate      bool
    DryRun        bool
    Streaming     bool
}

type GenerationRequest struct {
    Project     *Project
    ProjectDB   *gorm.DB
    SpecPath    string
    Component   string // optional: specific component
    Options     GenerationConfig
}

type GenerationResult struct {
    Files     []GeneratedFile
    Errors    []GenerationError
    Warnings  []string
    Stats     GenerationStats
}

type GeneratedFile struct {
    Path         string
    RelativePath string
    Content      string
    Size         int
    Action       string // created, updated, skipped
    BackupPath   string // if backed up
}
```

---

## Generation Flow

### Main Entry Point

```go
func (g *CodeGenerator) Generate(req GenerationRequest) (*GenerationResult, error) {
    result := &GenerationResult{
        Stats: GenerationStats{StartedAt: time.Now()},
    }
    
    // 1. Parse specification
    spec, err := g.specParser.Parse(req.SpecPath)
    if err != nil {
        return nil, errors.Wrap(err, 10501, "spec parsing failed")
    }
    
    // 2. Analyze requirements
    components := g.analyzeSpec(spec)
    
    // 3. Filter by component if specified
    if req.Component != "" {
        components = filterComponents(components, req.Component)
    }
    
    // 4. Generate each component
    for _, component := range components {
        files, err := g.generateComponent(req, component)
        if err != nil {
            result.Errors = append(result.Errors, GenerationError{
                Component: component.Name,
                Error:     err,
            })
            continue
        }
        result.Files = append(result.Files, files...)
    }
    
    // 5. Validate if enabled
    if req.Options.Validate {
        for i, file := range result.Files {
            validation := g.validator.Validate(file.Content, file.Path)
            if !validation.Valid {
                result.Files[i].Warnings = validation.Warnings
                result.Warnings = append(result.Warnings, validation.Warnings...)
            }
        }
    }
    
    // 6. Write files (unless dry run)
    if !req.Options.DryRun {
        for i, file := range result.Files {
            written, err := g.writeFile(file, req.Options)
            if err != nil {
                result.Errors = append(result.Errors, GenerationError{
                    File:  file.Path,
                    Error: err,
                })
            } else {
                result.Files[i] = written
            }
        }
    }
    
    result.Stats.CompletedAt = time.Now()
    return result, nil
}
```

---

## Component Generation

### Prompt Building

```go
func (g *CodeGenerator) generateComponent(req GenerationRequest, comp Component) ([]GeneratedFile, error) {
    // 1. Build RAG context
    context, err := g.ragService.BuildContext(comp.Description, ContextOptions{
        TopK: 5,
    })
    if err != nil {
        return nil, err
    }
    
    // 2. Get coding guidelines
    guidelines := g.guidelines.ForComponent(comp.Type)
    
    // 3. Build system prompt
    systemPrompt := g.buildSystemPrompt(req.Project, guidelines)
    
    // 4. Build user prompt
    userPrompt := g.buildUserPrompt(comp, context)
    
    // 5. Call AI Bridge
    response, err := g.aiBridge.Generate(AIRequest{
        SystemRole:  systemPrompt,
        Prompt:      userPrompt,
        Temperature: 0.2, // Low for code generation
    })
    if err != nil {
        return nil, errors.Wrap(err, 10502, "AI generation failed")
    }
    
    // 6. Parse response into files
    return g.parseResponse(response, comp)
}
```

---

## Prompt Templates

### System Prompt

```go
func (g *CodeGenerator) buildSystemPrompt(project *Project, guidelines string) string {
    return fmt.Sprintf(`You are an expert WordPress plugin developer.
    
## Project Information
- Plugin Name: %s
- Text Domain: %s
- PHP Namespace: %s
- WordPress Minimum: %s
- PHP Minimum: %s

## Coding Guidelines
%s

## Output Format
Respond with PHP code blocks. Each file should be wrapped in:

\`\`\`php:path/to/file.php
<?php
// code here
\`\`\`

Always include:
- File header with plugin information
- Proper security checks (ABSPATH)
- Correct namespace declarations
- PHPDoc comments for all functions and classes
- WordPress coding standards compliance
- Proper escaping and sanitization
- Internationalization using the text domain
`,
        project.Name,
        project.TextDomain,
        project.Namespace,
        g.config.WordPress.MinVersion,
        g.config.WordPress.RequiresPHP,
        guidelines,
    )
}
```

### User Prompt

```go
func (g *CodeGenerator) buildUserPrompt(comp Component, context string) string {
    return fmt.Sprintf(`## Task
Generate the %s component for the WordPress plugin.

## Requirements
%s

## Relevant Context
%s

## Files to Generate
%s

Generate complete, production-ready code following all WordPress and PHP best practices.`,
        comp.Name,
        comp.Description,
        context,
        strings.Join(comp.ExpectedFiles, "\n"),
    )
}
```

---

## Response Parsing

```go
func (g *CodeGenerator) parseResponse(response string, comp Component) ([]GeneratedFile, error) {
    var files []GeneratedFile
    
    // Match code blocks with file paths
    // Format: ```php:path/to/file.php
    pattern := regexp.MustCompile("```php:([^\n]+)\n([\\s\\S]*?)```")
    matches := pattern.FindAllStringSubmatch(response, -1)
    
    for _, match := range matches {
        if len(match) < 3 {
            continue
        }
        
        path := strings.TrimSpace(match[1])
        content := strings.TrimSpace(match[2])
        
        // Validate PHP syntax
        if err := g.validator.ValidateSyntax(content); err != nil {
            return nil, errors.Wrap(err, 10503, "generated code has syntax errors").
                WithField("file", path)
        }
        
        files = append(files, GeneratedFile{
            Path:         filepath.Join(g.config.OutputDir, path),
            RelativePath: path,
            Content:      content,
            Size:         len(content),
        })
    }
    
    if len(files) == 0 {
        return nil, errors.New(10504, "no files extracted from response")
    }
    
    return files, nil
}
```

---

## File Writing

```go
func (g *CodeGenerator) writeFile(file GeneratedFile, opts GenerationConfig) (GeneratedFile, error) {
    // Check if file exists
    exists := fileExists(file.Path)
    
    if exists {
        switch opts.OverwriteMode {
        case "skip":
            file.Action = "skipped"
            return file, nil
            
        case "backup":
            backupPath := file.Path + ".bak." + time.Now().Format("20060102150405")
            if err := copyFile(file.Path, backupPath); err != nil {
                return file, errors.Wrap(err, 10505, "backup failed")
            }
            file.BackupPath = backupPath
            file.Action = "updated"
            
        case "overwrite":
            file.Action = "updated"
        }
    } else {
        file.Action = "created"
    }
    
    // Ensure directory exists
    dir := filepath.Dir(file.Path)
    if err := os.MkdirAll(dir, 0755); err != nil {
        return file, errors.Wrap(err, 10506, "directory creation failed")
    }
    
    // Write file
    if err := os.WriteFile(file.Path, []byte(file.Content), 0644); err != nil {
        return file, errors.Wrap(err, 10507, "file write failed")
    }
    
    return file, nil
}
```

---

## Component Types

| Type | Description | Generated Files |
|------|-------------|-----------------|
| `core` | Main plugin class | `includes/class-{name}.php` |
| `admin` | Admin functionality | `admin/class-{name}-admin.php` |
| `public` | Public-facing | `public/class-{name}-public.php` |
| `api` | REST API endpoints | `includes/class-{name}-api.php` |
| `shortcode` | Shortcode handlers | `includes/class-{name}-shortcode.php` |
| `block` | Gutenberg blocks | `blocks/{block-name}/` |
| `widget` | WordPress widgets | `includes/class-{name}-widget.php` |
| `cpt` | Custom post types | `includes/class-{name}-cpt.php` |
| `taxonomy` | Custom taxonomies | `includes/class-{name}-taxonomy.php` |
| `settings` | Settings pages | `admin/class-{name}-settings.php` |

---

## Streaming Generation

```go
func (g *CodeGenerator) GenerateStream(req GenerationRequest, handler StreamHandler) error {
    // Build prompts
    systemPrompt := g.buildSystemPrompt(req.Project, g.guidelines.Default())
    userPrompt := g.buildUserPromptFromSpec(req.SpecPath)
    
    // Stream from AI Bridge
    return g.aiBridge.Stream(AIRequest{
        SystemRole: systemPrompt,
        Prompt:     userPrompt,
    }, func(chunk string) {
        handler.OnChunk(chunk)
    })
}

type StreamHandler interface {
    OnChunk(content string)
    OnComplete(result *GenerationResult)
    OnError(err error)
}
```

---

## Validation

```go
type CodeValidator struct {
    phpPath string
}

func (v *CodeValidator) ValidateSyntax(code string) error {
    // Write to temp file
    tmpFile, err := os.CreateTemp("", "wpb-*.php")
    if err != nil {
        return err
    }
    defer os.Remove(tmpFile.Name())
    
    tmpFile.WriteString(code)
    tmpFile.Close()
    
    // Run PHP linter
    cmd := exec.Command(v.phpPath, "-l", tmpFile.Name())
    output, err := cmd.CombinedOutput()
    if err != nil {
        return errors.New(10508, "PHP syntax error").
            WithField("output", string(output))
    }
    
    return nil
}

func (v *CodeValidator) Validate(code, path string) ValidationResult {
    result := ValidationResult{Valid: true}
    
    // Check for required elements
    checks := []struct {
        Pattern string
        Message string
    }{
        {`defined\s*\(\s*['"]ABSPATH['"]\s*\)`, "Missing ABSPATH security check"},
        {`@package`, "Missing @package in file header"},
        {`text_domain`, "Missing text domain in translations"},
    }
    
    for _, check := range checks {
        if !regexp.MustCompile(check.Pattern).MatchString(code) {
            result.Warnings = append(result.Warnings, check.Message)
        }
    }
    
    return result
}
```

---

## See Also

- [Spec Processing](./08-spec-processing.md)
- [Coding Guidelines](./12-coding-guidelines.md)
- [AI Bridge API](../ai-bridge/04-api-interface.md)
