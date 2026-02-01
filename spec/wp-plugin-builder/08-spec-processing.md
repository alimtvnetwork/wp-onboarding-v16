# Spec Processing

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-02-01  

---

## Overview

Specification import and parsing for WordPress plugin requirements. Supports markdown files, folders, zip archives, and PRD documents.

**Cross-References:**
- [CLI Interface](./02-cli-interface.md)
- [Code Generation](./07-code-generation.md)
- [Database Schema](./04-database-schema.md)

---

## Supported Formats

| Format | Extension | Description |
|--------|-----------|-------------|
| Markdown | `.md` | Single specification file |
| Folder | (directory) | Multiple markdown files |
| Zip Archive | `.zip` | Compressed spec folder |
| PRD | `.md`, `.pdf` | Product Requirements Document |

---

## Spec Parser

```go
type SpecParser struct {
    ragService *RAGService
    projectDB  *gorm.DB
}

type ParsedSpec struct {
    Name        string
    Version     string
    Description string
    Components  []Component
    Features    []Feature
    APIs        []APIEndpoint
    DataModels  []DataModel
    Metadata    map[string]any
}

type Component struct {
    Name           string
    Type           string
    Description    string
    Requirements   []string
    ExpectedFiles  []string
    Dependencies   []string
}

type Feature struct {
    Name        string
    Description string
    UserStories []string
    Priority    string
}
```

---

## Import Flow

```go
func (sp *SpecParser) Import(path string) (*ParsedSpec, error) {
    // 1. Detect format
    format := detectFormat(path)
    
    var files []SpecFile
    var err error
    
    switch format {
    case "markdown":
        files, err = sp.readMarkdownFile(path)
    case "folder":
        files, err = sp.readFolder(path)
    case "zip":
        files, err = sp.extractAndReadZip(path)
    default:
        return nil, errors.New(10601, "unsupported spec format")
    }
    
    if err != nil {
        return nil, err
    }
    
    // 2. Parse all files
    spec, err := sp.parseFiles(files)
    if err != nil {
        return nil, err
    }
    
    // 3. Store in database
    for _, file := range files {
        dbSpec := &Specification{
            Name:        file.Name,
            Path:        file.Path,
            Content:     file.Content,
            ContentHash: hash(file.Content),
            Format:      format,
        }
        if err := sp.projectDB.Create(dbSpec).Error; err != nil {
            return nil, errors.Wrap(err, 10602, "spec storage failed")
        }
        
        // 4. Index for RAG
        if err := sp.ragService.IndexDocument(SourceInfo{
            Type: "spec",
            ID:   fmt.Sprintf("%d", dbSpec.ID),
            Name: file.Name,
        }, file.Content); err != nil {
            log.Warn("spec indexing failed", "error", err)
        }
    }
    
    return spec, nil
}
```

---

## Markdown Parsing

### Structure Recognition

```go
func (sp *SpecParser) parseMarkdown(content string) (*ParsedSpec, error) {
    spec := &ParsedSpec{
        Metadata: make(map[string]any),
    }
    
    // 1. Extract frontmatter
    if strings.HasPrefix(content, "---") {
        meta, body := extractFrontmatter(content)
        spec.Metadata = meta
        spec.Name = meta["name"].(string)
        spec.Version = meta["version"].(string)
        content = body
    }
    
    // 2. Parse sections
    sections := sp.splitSections(content)
    
    for _, section := range sections {
        switch section.Type {
        case "overview":
            spec.Description = section.Content
            
        case "features":
            spec.Features = sp.parseFeatures(section.Content)
            
        case "components":
            spec.Components = sp.parseComponents(section.Content)
            
        case "api":
            spec.APIs = sp.parseAPIs(section.Content)
            
        case "data-models":
            spec.DataModels = sp.parseDataModels(section.Content)
        }
    }
    
    return spec, nil
}
```

### Section Detection

```go
func (sp *SpecParser) splitSections(content string) []Section {
    var sections []Section
    
    // Match H2 headers
    pattern := regexp.MustCompile(`(?m)^## (.+)$`)
    matches := pattern.FindAllStringSubmatchIndex(content, -1)
    
    for i, match := range matches {
        header := content[match[2]:match[3]]
        
        // Determine section end
        end := len(content)
        if i+1 < len(matches) {
            end = matches[i+1][0]
        }
        
        sectionContent := content[match[1]:end]
        
        sections = append(sections, Section{
            Type:    sp.detectSectionType(header),
            Header:  header,
            Content: strings.TrimSpace(sectionContent),
        })
    }
    
    return sections
}

func (sp *SpecParser) detectSectionType(header string) string {
    header = strings.ToLower(header)
    
    patterns := map[string][]string{
        "overview":    {"overview", "introduction", "summary"},
        "features":    {"features", "functionality", "capabilities"},
        "components":  {"components", "modules", "architecture"},
        "api":         {"api", "endpoints", "rest", "routes"},
        "data-models": {"data", "models", "database", "schema"},
    }
    
    for sectionType, keywords := range patterns {
        for _, keyword := range keywords {
            if strings.Contains(header, keyword) {
                return sectionType
            }
        }
    }
    
    return "general"
}
```

---

## Component Extraction

```go
func (sp *SpecParser) parseComponents(content string) []Component {
    var components []Component
    
    // Match component definitions
    // Format: ### Component Name
    //         Description...
    //         **Type:** admin
    //         **Files:** file1.php, file2.php
    
    pattern := regexp.MustCompile(`(?m)^### (.+)$`)
    matches := pattern.FindAllStringSubmatchIndex(content, -1)
    
    for i, match := range matches {
        name := content[match[2]:match[3]]
        
        end := len(content)
        if i+1 < len(matches) {
            end = matches[i+1][0]
        }
        
        body := content[match[1]:end]
        
        comp := Component{
            Name: strings.TrimSpace(name),
        }
        
        // Extract type
        if typeMatch := regexp.MustCompile(`\*\*Type:\*\*\s*(.+)`).FindStringSubmatch(body); len(typeMatch) > 1 {
            comp.Type = strings.TrimSpace(typeMatch[1])
        }
        
        // Extract expected files
        if filesMatch := regexp.MustCompile(`\*\*Files:\*\*\s*(.+)`).FindStringSubmatch(body); len(filesMatch) > 1 {
            comp.ExpectedFiles = strings.Split(filesMatch[1], ",")
            for i := range comp.ExpectedFiles {
                comp.ExpectedFiles[i] = strings.TrimSpace(comp.ExpectedFiles[i])
            }
        }
        
        // Extract requirements (bullet points)
        reqPattern := regexp.MustCompile(`(?m)^[-*]\s+(.+)$`)
        for _, req := range reqPattern.FindAllStringSubmatch(body, -1) {
            if len(req) > 1 {
                comp.Requirements = append(comp.Requirements, req[1])
            }
        }
        
        // Description is everything before the first metadata
        descEnd := strings.Index(body, "**Type:")
        if descEnd == -1 {
            descEnd = strings.Index(body, "**Files:")
        }
        if descEnd == -1 {
            descEnd = strings.Index(body, "-")
        }
        if descEnd > 0 {
            comp.Description = strings.TrimSpace(body[:descEnd])
        }
        
        components = append(components, comp)
    }
    
    return components
}
```

---

## PRD Processing

```go
func (sp *SpecParser) parsePRD(content string) (*ParsedSpec, error) {
    spec := &ParsedSpec{
        Metadata: make(map[string]any),
    }
    
    // PRD-specific sections
    sections := map[string]string{
        "problem":      "",
        "solution":     "",
        "user-stories": "",
        "requirements": "",
        "success":      "",
    }
    
    // Extract each section
    for sectionName := range sections {
        extracted := sp.extractPRDSection(content, sectionName)
        sections[sectionName] = extracted
    }
    
    // Convert to components
    spec.Description = sections["problem"] + "\n\n" + sections["solution"]
    spec.Features = sp.parseUserStoriesToFeatures(sections["user-stories"])
    spec.Components = sp.deriveComponentsFromRequirements(sections["requirements"])
    
    return spec, nil
}

func (sp *SpecParser) extractPRDSection(content, sectionName string) string {
    // Common PRD section headers
    patterns := map[string][]string{
        "problem":      {"problem statement", "problem", "background", "context"},
        "solution":     {"proposed solution", "solution", "approach"},
        "user-stories": {"user stories", "use cases", "scenarios"},
        "requirements": {"requirements", "functional requirements", "features"},
        "success":      {"success criteria", "acceptance criteria", "kpis"},
    }
    
    for _, pattern := range patterns[sectionName] {
        // Search for header (case insensitive)
        regex := regexp.MustCompile(`(?im)^##?\s*` + regexp.QuoteMeta(pattern) + `\s*$`)
        if loc := regex.FindStringIndex(content); loc != nil {
            // Extract until next header
            rest := content[loc[1]:]
            nextHeader := regexp.MustCompile(`(?m)^##?\s+`).FindStringIndex(rest)
            if nextHeader != nil {
                return strings.TrimSpace(rest[:nextHeader[0]])
            }
            return strings.TrimSpace(rest)
        }
    }
    
    return ""
}
```

---

## Zip Processing

```go
func (sp *SpecParser) extractAndReadZip(path string) ([]SpecFile, error) {
    // Create temp directory
    tempDir, err := os.MkdirTemp("", "wpb-spec-*")
    if err != nil {
        return nil, errors.Wrap(err, 10603, "temp directory creation failed")
    }
    defer os.RemoveAll(tempDir)
    
    // Extract zip
    if err := extractZip(path, tempDir); err != nil {
        return nil, errors.Wrap(err, 10604, "zip extraction failed")
    }
    
    // Read as folder
    return sp.readFolder(tempDir)
}

func (sp *SpecParser) readFolder(path string) ([]SpecFile, error) {
    var files []SpecFile
    
    err := filepath.Walk(path, func(filePath string, info os.FileInfo, err error) error {
        if err != nil {
            return err
        }
        
        // Skip non-markdown files
        if info.IsDir() || !isMarkdown(filePath) {
            return nil
        }
        
        content, err := os.ReadFile(filePath)
        if err != nil {
            return err
        }
        
        relPath, _ := filepath.Rel(path, filePath)
        
        files = append(files, SpecFile{
            Name:    filepath.Base(filePath),
            Path:    relPath,
            Content: string(content),
        })
        
        return nil
    })
    
    if err != nil {
        return nil, errors.Wrap(err, 10605, "folder reading failed")
    }
    
    return files, nil
}

func isMarkdown(path string) bool {
    ext := strings.ToLower(filepath.Ext(path))
    return ext == ".md" || ext == ".markdown"
}
```

---

## Validation

```go
func (sp *SpecParser) Validate(spec *ParsedSpec) ValidationResult {
    result := ValidationResult{Valid: true}
    
    // Required fields
    if spec.Name == "" {
        result.Errors = append(result.Errors, "Specification name is required")
        result.Valid = false
    }
    
    // Must have at least one component or feature
    if len(spec.Components) == 0 && len(spec.Features) == 0 {
        result.Warnings = append(result.Warnings, 
            "No components or features found - generation may be limited")
    }
    
    // Check component requirements
    for _, comp := range spec.Components {
        if comp.Type == "" {
            result.Warnings = append(result.Warnings,
                fmt.Sprintf("Component '%s' has no type specified", comp.Name))
        }
    }
    
    return result
}
```

---

## See Also

- [Code Generation](./07-code-generation.md)
- [RAG System](./05-rag-system.md)
- [Database Schema](./04-database-schema.md)
