# Project Management

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-02-01  

---

## Overview

Project lifecycle management including creation, cloning, import/export, and database handling.

**Cross-References:**
- [CLI Interface](./02-cli-interface.md)
- [Database Schema](./04-database-schema.md)
- [Core Architecture](./01-core-architecture.md)

---

## Project Structure

Each project generates the following structure:

```
~/.wpb/
├── wpb.sqlite                    # Root database
├── projects/
│   └── exam-manager.sqlite       # Project database
└── ...

./plugins/exam-manager/           # Generated plugin (configurable)
├── exam-manager.php              # Main plugin file
├── includes/
│   ├── class-exam-manager.php
│   ├── class-exam-manager-admin.php
│   └── class-exam-manager-public.php
├── admin/
│   ├── css/
│   ├── js/
│   └── partials/
├── public/
│   ├── css/
│   ├── js/
│   └── partials/
├── languages/
├── uninstall.php
└── README.md
```

---

## Project Manager

```go
type ProjectManager struct {
    rootDB     *gorm.DB
    projectDir string
    config     *Config
}

func NewProjectManager(rootDB *gorm.DB, projectDir string, cfg *Config) *ProjectManager {
    return &ProjectManager{
        rootDB:     rootDB,
        projectDir: projectDir,
        config:     cfg,
    }
}
```

---

## Operations

### Create Project

```go
type CreateProjectOptions struct {
    Name        string
    Author      string
    AuthorEmail string
    Website     string
    Description string
    Version     string
    TextDomain  string
    Namespace   string
    OutputDir   string
}

func (pm *ProjectManager) Create(opts CreateProjectOptions) (*Project, error) {
    // 1. Validate name
    if opts.Name == "" {
        return nil, errors.New(10301, "project name required")
    }
    
    // 2. Generate slug
    slug := slugify(opts.Name)
    
    // 3. Check for duplicate
    var existing Project
    if err := pm.rootDB.Where("slug = ?", slug).First(&existing).Error; err == nil {
        return nil, errors.New(10302, "project already exists").
            WithField("slug", slug)
    }
    
    // 4. Create project database
    dbPath := filepath.Join(pm.projectDir, slug+".sqlite")
    projectDB, err := pm.initProjectDB(dbPath)
    if err != nil {
        return nil, err
    }
    
    // 5. Set defaults
    if opts.TextDomain == "" {
        opts.TextDomain = slug
    }
    if opts.Namespace == "" {
        opts.Namespace = toPascalCase(opts.Name)
    }
    if opts.Version == "" {
        opts.Version = "1.0.0"
    }
    if opts.OutputDir == "" {
        opts.OutputDir = filepath.Join(pm.config.Generation.OutputDir, slug)
    }
    
    // 6. Create project record
    project := &Project{
        Name:        opts.Name,
        Slug:        slug,
        Author:      opts.Author,
        AuthorEmail: opts.AuthorEmail,
        Website:     opts.Website,
        Description: opts.Description,
        TextDomain:  opts.TextDomain,
        Namespace:   opts.Namespace,
        Version:     opts.Version,
        DBPath:      dbPath,
        OutputPath:  opts.OutputDir,
    }
    
    if err := pm.rootDB.Create(project).Error; err != nil {
        return nil, errors.Wrap(err, 10303, "project creation failed")
    }
    
    // 7. Copy global presets to project (if configured)
    if err := pm.applyAutoLoadPresets(projectDB, project); err != nil {
        // Log warning but don't fail
        log.Warn("preset application failed", "error", err)
    }
    
    return project, nil
}
```

---

### List Projects

```go
type ListProjectsOptions struct {
    Format  string // table, json, csv
    SortBy  string // name, created, updated
    Order   string // asc, desc
}

func (pm *ProjectManager) List(opts ListProjectsOptions) ([]Project, error) {
    var projects []Project
    
    query := pm.rootDB.Model(&Project{})
    
    // Apply sorting
    orderClause := fmt.Sprintf("%s %s", opts.SortBy, opts.Order)
    query = query.Order(orderClause)
    
    if err := query.Find(&projects).Error; err != nil {
        return nil, errors.Wrap(err, 10304, "project list failed")
    }
    
    return projects, nil
}
```

---

### Open Project

```go
func (pm *ProjectManager) Open(name string) (*Project, *gorm.DB, error) {
    // 1. Find project
    var project Project
    if err := pm.rootDB.Where("name = ? OR slug = ?", name, name).
        First(&project).Error; err != nil {
        return nil, nil, errors.New(10305, "project not found").
            WithField("name", name)
    }
    
    // 2. Open project database
    projectDB, err := gorm.Open(sqlite.Open(project.DBPath), &gorm.Config{})
    if err != nil {
        return nil, nil, errors.Wrap(err, 10306, "project database open failed")
    }
    
    return &project, projectDB, nil
}
```

---

### Delete Project

```go
type DeleteProjectOptions struct {
    Force     bool
    KeepFiles bool
}

func (pm *ProjectManager) Delete(name string, opts DeleteProjectOptions) error {
    // 1. Find project
    project, _, err := pm.Open(name)
    if err != nil {
        return err
    }
    
    // 2. Confirm if not forced
    if !opts.Force {
        confirmed, err := pm.confirmDelete(project)
        if err != nil || !confirmed {
            return errors.New(10307, "deletion cancelled")
        }
    }
    
    // 3. Delete project database
    if err := os.Remove(project.DBPath); err != nil && !os.IsNotExist(err) {
        return errors.Wrap(err, 10308, "database deletion failed")
    }
    
    // 4. Delete generated files (unless kept)
    if !opts.KeepFiles && project.OutputPath != "" {
        if err := os.RemoveAll(project.OutputPath); err != nil {
            log.Warn("file deletion failed", "error", err)
        }
    }
    
    // 5. Remove from root database
    return pm.rootDB.Delete(project).Error
}
```

---

### Clone Project

```go
type CloneProjectOptions struct {
    IncludeHistory bool
}

func (pm *ProjectManager) Clone(sourceName, targetName string, opts CloneProjectOptions) (*Project, error) {
    // 1. Open source project
    source, sourceDB, err := pm.Open(sourceName)
    if err != nil {
        return nil, err
    }
    
    // 2. Create target project
    target, err := pm.Create(CreateProjectOptions{
        Name:        targetName,
        Author:      source.Author,
        AuthorEmail: source.AuthorEmail,
        Website:     source.Website,
        Description: source.Description + " (cloned)",
    })
    if err != nil {
        return nil, err
    }
    
    // 3. Copy project database content
    targetDB, err := gorm.Open(sqlite.Open(target.DBPath), &gorm.Config{})
    if err != nil {
        return nil, errors.Wrap(err, 10309, "target database open failed")
    }
    
    // Copy RAG vectors
    if err := pm.copyTable(sourceDB, targetDB, &RAGVector{}); err != nil {
        return nil, err
    }
    
    // Copy specifications
    if err := pm.copyTable(sourceDB, targetDB, &Specification{}); err != nil {
        return nil, err
    }
    
    // Copy history if requested
    if opts.IncludeHistory {
        if err := pm.copyTable(sourceDB, targetDB, &GenerationHistory{}); err != nil {
            return nil, err
        }
    }
    
    return target, nil
}
```

---

### Export Project

```go
type ExportProjectOptions struct {
    OutputPath   string
    Format       string // sqlite, zip
    IncludeFiles bool
}

func (pm *ProjectManager) Export(name string, opts ExportProjectOptions) (string, error) {
    // 1. Open project
    project, _, err := pm.Open(name)
    if err != nil {
        return "", err
    }
    
    // 2. Determine output path
    outputPath := opts.OutputPath
    if outputPath == "" {
        outputPath = fmt.Sprintf("./%s.%s", project.Slug, opts.Format)
    }
    
    switch opts.Format {
    case "sqlite":
        // Copy database file
        if err := copyFile(project.DBPath, outputPath); err != nil {
            return "", errors.Wrap(err, 10310, "database export failed")
        }
        
    case "zip":
        // Create zip with database and optionally files
        zw, err := zip.Create(outputPath)
        if err != nil {
            return "", errors.Wrap(err, 10311, "zip creation failed")
        }
        defer zw.Close()
        
        // Add database
        if err := zw.AddFile(project.DBPath, "project.sqlite"); err != nil {
            return "", err
        }
        
        // Add metadata
        meta := ExportMetadata{
            Name:       project.Name,
            Slug:       project.Slug,
            Version:    project.Version,
            ExportedAt: time.Now(),
        }
        if err := zw.AddJSON("metadata.json", meta); err != nil {
            return "", err
        }
        
        // Add generated files if requested
        if opts.IncludeFiles && project.OutputPath != "" {
            if err := zw.AddDir(project.OutputPath, "files/"); err != nil {
                return "", err
            }
        }
    }
    
    return outputPath, nil
}
```

---

### Import Project

```go
type ImportProjectOptions struct {
    Name      string
    Overwrite bool
}

func (pm *ProjectManager) Import(path string, opts ImportProjectOptions) (*Project, error) {
    // 1. Detect format
    format := detectFormat(path)
    
    var projectDB *gorm.DB
    var meta ExportMetadata
    
    switch format {
    case "sqlite":
        // Open directly
        var err error
        projectDB, err = gorm.Open(sqlite.Open(path), &gorm.Config{})
        if err != nil {
            return nil, errors.Wrap(err, 10312, "database import failed")
        }
        meta.Name = opts.Name
        if meta.Name == "" {
            meta.Name = strings.TrimSuffix(filepath.Base(path), ".sqlite")
        }
        
    case "zip":
        // Extract to temp, read metadata
        tempDir, err := extractZip(path)
        if err != nil {
            return nil, errors.Wrap(err, 10313, "zip extraction failed")
        }
        defer os.RemoveAll(tempDir)
        
        // Read metadata
        metaPath := filepath.Join(tempDir, "metadata.json")
        if err := readJSON(metaPath, &meta); err != nil {
            return nil, err
        }
        
        // Open database
        dbPath := filepath.Join(tempDir, "project.sqlite")
        projectDB, err = gorm.Open(sqlite.Open(dbPath), &gorm.Config{})
        if err != nil {
            return nil, errors.Wrap(err, 10312, "database import failed")
        }
    }
    
    // 2. Create project
    name := opts.Name
    if name == "" {
        name = meta.Name
    }
    
    project, err := pm.Create(CreateProjectOptions{
        Name: name,
    })
    if err != nil {
        if errors.Code(err) == 10302 && opts.Overwrite {
            // Delete and retry
            pm.Delete(name, DeleteProjectOptions{Force: true})
            project, err = pm.Create(CreateProjectOptions{Name: name})
        }
        if err != nil {
            return nil, err
        }
    }
    
    // 3. Copy data to new project database
    targetDB, err := gorm.Open(sqlite.Open(project.DBPath), &gorm.Config{})
    if err != nil {
        return nil, err
    }
    
    // Copy all tables
    for _, model := range []any{&RAGVector{}, &Specification{}, &GenerationHistory{}} {
        if err := pm.copyTable(projectDB, targetDB, model); err != nil {
            log.Warn("table copy failed", "model", model, "error", err)
        }
    }
    
    return project, nil
}
```

---

## Helper Functions

```go
func slugify(name string) string {
    // Convert to lowercase, replace spaces with hyphens
    slug := strings.ToLower(name)
    slug = regexp.MustCompile(`[^a-z0-9]+`).ReplaceAllString(slug, "-")
    slug = strings.Trim(slug, "-")
    return slug
}

func toPascalCase(name string) string {
    words := regexp.MustCompile(`[^a-zA-Z0-9]+`).Split(name, -1)
    for i, word := range words {
        words[i] = strings.Title(strings.ToLower(word))
    }
    return strings.Join(words, "")
}

func (pm *ProjectManager) copyTable(src, dst *gorm.DB, model any) error {
    var records []map[string]any
    if err := src.Model(model).Find(&records).Error; err != nil {
        return err
    }
    for _, record := range records {
        if err := dst.Model(model).Create(record).Error; err != nil {
            return err
        }
    }
    return nil
}
```

---

## See Also

- [CLI Interface](./02-cli-interface.md)
- [Database Schema](./04-database-schema.md)
- [Error Handling](./10-error-handling.md)
