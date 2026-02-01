# Import/Export System

> **Version:** 1.0.0  
> **Last Updated:** 2026-01-28  
> **Status:** Draft  

---

## 29.1 Overview

Comprehensive data import and export functionality for project portability, backups, and migration. Supports importing from single PRD/Markdown files, ZIP archives with folder structures, and exporting projects as portable packages.

**Key Capabilities:**
- Import single PRD/Markdown file → auto-creates project structure
- Import ZIP archive with existing folder structure
- Export project as ZIP with all files + metadata
- Auto-generate `spec.project.json` when missing but structure is valid
- Preserve folder hierarchy and file ordering

**Cross-References:**
- [Database Schema](../../07-database-design/01-schema.md) - Project and File models
- [File Operations](../02-file-management/01-file-operations.md) - Path validation and CRUD
- [Path Manager](../02-file-management/02-path-manager.md) - Relative path handling

---

## 29.2 Export System

### 29.2.1 Export Types

| Export Type | Contents | Use Case |
|-------------|----------|----------|
| **Full Project Export** | All files, metadata, history | Complete backup/migration |
| **Selective Export** | User-selected files only | Partial sharing |
| **Metadata Only** | `spec.project.json` only | Configuration sync |

### 29.2.2 ZIP Structure

Exported ZIP files follow this structure:

```
{project-slug}-export-YYYY-MM-DD.zip
├── spec.project.json        # Required: Project metadata
├── export-manifest.json     # Export metadata (version, timestamp, checksums)
├── spec/
│   ├── 00-overview.md
│   ├── 01-backend/
│   │   ├── 00-overview.md
│   │   ├── 01-database.md
│   │   └── ...
│   └── 02-frontend/
│       └── ...
├── ideas/
│   ├── README.md
│   ├── 01-idea-feature.md
│   └── ...
├── instructions/
│   ├── README.md
│   └── ...
├── diagrams/
│   └── ...
└── .history/                 # Optional: Snapshot history
    └── snapshots/
```

### 29.2.3 spec.project.json Schema

```json
{
  "$schema": "https://spec-manager.dev/schemas/project-v1.json",
  "version": "1.0.0",
  "name": "Exam Questions Manager",
  "slug": "exam-questions-manager",
  "description": "WordPress plugin for exam management",
  "category": "WordPress Plugins",
  "visibility": "user",
  "author": {
    "name": "John Doe",
    "email": "john@example.com"
  },
  "tags": ["wordpress", "exam", "education"],
  "framework": "PHP/WordPress",
  "language": "PHP",
  "aiSettings": {
    "thinkingModelId": "model-uuid",
    "writingModelId": "model-uuid",
    "voiceModelId": "model-uuid",
    "codingModelId": "model-uuid",
    "instructionMode": "approval"
  },
  "createdAt": "2026-01-15T08:00:00Z",
  "updatedAt": "2026-01-28T10:00:00Z"
}
```

### 29.2.4 export-manifest.json Schema

```json
{
  "manifestVersion": "1.0.0",
  "exportedAt": "2026-01-28T14:30:00Z",
  "exportedBy": "user-uuid",
  "sourceProjectId": "project-uuid",
  "specManagerVersion": "0.3.0",
  "fileCount": 45,
  "totalSizeBytes": 256000,
  "checksums": {
    "algorithm": "sha256",
    "files": {
      "spec/00-overview.md": "abc123...",
      "spec/01-backend/01-database.md": "def456..."
    }
  },
  "exportOptions": {
    "includeHistory": true,
    "includeIdeas": true,
    "includeInstructions": true
  }
}
```

### 29.2.5 Export Service

```go
package export

import (
    "archive/zip"
    "context"
    "crypto/sha256"
    "encoding/hex"
    "encoding/json"
    "io"
    "os"
    "path/filepath"
    "time"
    
    "spec-manager/internal/models"
)

// ExportOptions configures what to include in export
type ExportOptions struct {
    IncludeHistory      bool `json:"includeHistory"`
    IncludeIdeas        bool `json:"includeIdeas"`
    IncludeInstructions bool `json:"includeInstructions"`
    SelectedFiles       []string `json:"selectedFiles,omitempty"` // Empty = all
}

// ExportResult contains export operation results
type ExportResult struct {
    ZipPath       string            `json:"zipPath"`
    FileName      string            `json:"fileName"`
    FileCount     int               `json:"fileCount"`
    TotalBytes    int64             `json:"totalBytes"`
    Checksums     map[string]string `json:"checksums"`
    ExportedAt    time.Time         `json:"exportedAt"`
}

// ExportService handles project exports
type ExportService struct {
    db          *gorm.DB
    pathManager *PathManager
    workDir     string
}

func NewExportService(db *gorm.DB, pm *PathManager, workDir string) *ExportService {
    return &ExportService{db: db, pathManager: pm, workDir: workDir}
}

// ExportProject creates a ZIP export of the project
func (s *ExportService) ExportProject(
    ctx context.Context, 
    projectId string, 
    userId string,
    options ExportOptions,
) (*ExportResult, error) {
    // 1. Load project with metadata
    var project models.Project
    if err := s.db.Preload("Metadata").First(&project, "id = ?", projectId).Error; err != nil {
        return nil, NewError(ERR_PROJECT_NOT_FOUND, "Project not found")
    }
    
    // 2. Verify ownership or global access
    if project.OwnerId != userId && project.Visibility != models.VisibilityGlobal {
        return nil, NewError(ERR_ACCESS_DENIED, "Cannot export this project")
    }
    
    // 3. Generate export filename
    timestamp := time.Now().Format("2006-01-02")
    zipFileName := fmt.Sprintf("%s-export-%s.zip", project.Slug, timestamp)
    zipPath := filepath.Join(s.workDir, ".exports", zipFileName)
    
    // 4. Ensure export directory exists
    if err := os.MkdirAll(filepath.Dir(zipPath), 0755); err != nil {
        return nil, NewError(ERR_DIR_CREATE, "Failed to create export directory")
    }
    
    // 5. Create ZIP file
    zipFile, err := os.Create(zipPath)
    if err != nil {
        return nil, NewError(ERR_FILE_WRITE, "Failed to create export file")
    }
    defer zipFile.Close()
    
    zipWriter := zip.NewWriter(zipFile)
    defer zipWriter.Close()
    
    checksums := make(map[string]string)
    fileCount := 0
    var totalBytes int64
    
    // 6. Add spec.project.json
    projectJson := s.generateProjectJson(project)
    if err := s.addToZip(zipWriter, "spec.project.json", projectJson, checksums); err != nil {
        return nil, err
    }
    fileCount++
    totalBytes += int64(len(projectJson))
    
    // 7. Walk project directory and add files
    projectPath := s.pathManager.ToAbsolute(projectId, "")
    err = filepath.WalkDir(projectPath, func(path string, d os.DirEntry, err error) error {
        if err != nil {
            return err
        }
        
        relPath, _ := filepath.Rel(projectPath, path)
        
        // Skip based on options
        if !options.IncludeHistory && isHistoryPath(relPath) {
            if d.IsDir() {
                return filepath.SkipDir
            }
            return nil
        }
        if !options.IncludeIdeas && isIdeasPath(relPath) {
            if d.IsDir() {
                return filepath.SkipDir
            }
            return nil
        }
        if !options.IncludeInstructions && isInstructionsPath(relPath) {
            if d.IsDir() {
                return filepath.SkipDir
            }
            return nil
        }
        
        // Skip selected files filter
        if len(options.SelectedFiles) > 0 && !isInSelectedFiles(relPath, options.SelectedFiles) {
            return nil
        }
        
        if d.IsDir() {
            return nil
        }
        
        // Read and add file
        content, err := os.ReadFile(path)
        if err != nil {
            return err
        }
        
        if err := s.addToZip(zipWriter, relPath, content, checksums); err != nil {
            return err
        }
        
        fileCount++
        totalBytes += int64(len(content))
        return nil
    })
    
    if err != nil {
        os.Remove(zipPath) // Cleanup on error
        return nil, NewError(ERR_FILE_READ, "Failed to read project files")
    }
    
    // 8. Add export manifest
    manifest := ExportManifest{
        ManifestVersion:      "1.0.0",
        ExportedAt:          time.Now(),
        ExportedBy:          userId,
        SourceProjectId:     projectId,
        SpecManagerVersion:  "0.3.0",
        FileCount:           fileCount,
        TotalSizeBytes:      totalBytes,
        Checksums:           ChecksumInfo{Algorithm: "sha256", Files: checksums},
        ExportOptions:       options,
    }
    manifestJson, _ := json.MarshalIndent(manifest, "", "  ")
    if err := s.addToZip(zipWriter, "export-manifest.json", manifestJson, checksums); err != nil {
        return nil, err
    }
    
    return &ExportResult{
        ZipPath:    zipPath,
        FileName:   zipFileName,
        FileCount:  fileCount,
        TotalBytes: totalBytes,
        Checksums:  checksums,
        ExportedAt: time.Now(),
    }, nil
}

func (s *ExportService) addToZip(w *zip.Writer, path string, content []byte, checksums map[string]string) error {
    f, err := w.Create(path)
    if err != nil {
        return err
    }
    if _, err := f.Write(content); err != nil {
        return err
    }
    
    hash := sha256.Sum256(content)
    checksums[path] = hex.EncodeToString(hash[:])
    return nil
}

func (s *ExportService) generateProjectJson(p models.Project) []byte {
    pj := ProjectJson{
        Schema:      "https://spec-manager.dev/schemas/project-v1.json",
        Version:     "1.0.0",
        Name:        p.Name,
        Slug:        p.Slug,
        Description: deref(p.Description),
        Visibility:  string(p.Visibility),
        CreatedAt:   p.CreatedAt,
        UpdatedAt:   p.UpdatedAt,
    }
    
    if p.Metadata != nil {
        pj.Category = deref(p.Metadata.Category)
        pj.Language = deref(p.Metadata.Language)
        pj.Framework = deref(p.Metadata.Framework)
        pj.Author = AuthorInfo{
            Name:  deref(p.Metadata.AuthorName),
            Email: deref(p.Metadata.AuthorEmail),
        }
        // Parse tags and aiSettings from JSON
        json.Unmarshal(p.Metadata.Tags, &pj.Tags)
        json.Unmarshal(p.Metadata.AiSettings, &pj.AiSettings)
    }
    
    result, _ := json.MarshalIndent(pj, "", "  ")
    return result
}
```

### 29.2.6 Export API Endpoints

**POST /api/v1/projects/:id/export**

Create export package for download.

```json
// Request
{
  "options": {
    "includeHistory": false,
    "includeIdeas": true,
    "includeInstructions": true,
    "selectedFiles": []
  }
}

// Response (202 Accepted - Background job)
{
  "success": true,
  "data": {
    "exportId": "export-uuid",
    "status": "processing",
    "estimatedSize": 256000,
    "estimatedFiles": 45
  }
}
```

**GET /api/v1/exports/:exportId/status**

Check export job status.

```json
{
  "success": true,
  "data": {
    "exportId": "export-uuid",
    "status": "completed",
    "progress": 100,
    "downloadUrl": "/api/v1/exports/export-uuid/download",
    "expiresAt": "2026-01-29T14:30:00Z"
  }
}
```

**GET /api/v1/exports/:exportId/download**

Download completed export (returns ZIP file stream).

---

## 29.3 Import System

### 29.3.1 Import Types

| Import Type | Source | Detection Method |
|-------------|--------|------------------|
| **ZIP Archive** | `.zip` file | File extension |
| **Single Markdown** | `.md` file | File extension |
| **Single PRD** | `.md` with PRD structure | Content analysis |
| **Folder Path** | Local directory path | Directory exists |

### 29.3.2 Import Detection Algorithm

```go
// ImportSourceType identifies the import source
type ImportSourceType string

const (
    ImportSourceZip      ImportSourceType = "zip"
    ImportSourceMarkdown ImportSourceType = "markdown"
    ImportSourcePRD      ImportSourceType = "prd"
    ImportSourceFolder   ImportSourceType = "folder"
)

// DetectImportSource analyzes input to determine import type
func DetectImportSource(filePath string) (ImportSourceType, error) {
    ext := strings.ToLower(filepath.Ext(filePath))
    
    // Check if it's a directory
    info, err := os.Stat(filePath)
    if err == nil && info.IsDir() {
        return ImportSourceFolder, nil
    }
    
    switch ext {
    case ".zip":
        return ImportSourceZip, nil
    case ".md":
        content, err := os.ReadFile(filePath)
        if err != nil {
            return "", err
        }
        if isPRDDocument(content) {
            return ImportSourcePRD, nil
        }
        return ImportSourceMarkdown, nil
    default:
        return "", NewError(ERR_INVALID_FORMAT, "Unsupported file format")
    }
}

// isPRDDocument checks if markdown content follows PRD structure
func isPRDDocument(content []byte) bool {
    text := string(content)
    
    // PRD indicators
    prdIndicators := []string{
        "# Product Requirements Document",
        "# PRD:",
        "## Overview",
        "## Requirements",
        "## User Stories",
        "## Acceptance Criteria",
        "## Technical Specification",
    }
    
    matchCount := 0
    for _, indicator := range prdIndicators {
        if strings.Contains(text, indicator) {
            matchCount++
        }
    }
    
    // Consider it a PRD if 3+ indicators are present
    return matchCount >= 3
}
```

### 29.3.3 Import Service

```go
package importpkg

import (
    "archive/zip"
    "context"
    "encoding/json"
    "io"
    "os"
    "path/filepath"
    "regexp"
    "strings"
    
    "spec-manager/internal/models"
)

// ImportOptions configures import behavior
type ImportOptions struct {
    ProjectName       string            `json:"projectName"`
    Category          *string           `json:"category,omitempty"`
    Visibility        models.Visibility `json:"visibility"`
    ConflictStrategy  ConflictStrategy  `json:"conflictStrategy"`
    GenerateMetadata  bool              `json:"generateMetadata"`
    ParsePRDSections  bool              `json:"parsePrdSections"`
}

type ConflictStrategy string

const (
    ConflictSkip      ConflictStrategy = "skip"
    ConflictRename    ConflictStrategy = "rename"
    ConflictOverwrite ConflictStrategy = "overwrite"
)

// ImportPreview shows what will be imported before executing
type ImportPreview struct {
    DetectedType      ImportSourceType   `json:"detectedType"`
    ProjectName       string             `json:"projectName"`
    FileCount         int                `json:"fileCount"`
    FolderCount       int                `json:"folderCount"`
    TotalSizeBytes    int64              `json:"totalSizeBytes"`
    HasProjectJson    bool               `json:"hasProjectJson"`
    HasOverview       bool               `json:"hasOverview"`
    DetectedCategory  *string            `json:"detectedCategory"`
    Warnings          []string           `json:"warnings"`
    RequiresMetadata  bool               `json:"requiresMetadata"`
    FileTree          []FileTreeNode     `json:"fileTree"`
}

// ImportResult contains the results of an import operation
type ImportResult struct {
    ProjectId          string   `json:"projectId"`
    ProjectName        string   `json:"projectName"`
    FilesImported      int      `json:"filesImported"`
    FoldersCreated     int      `json:"foldersCreated"`
    MetadataGenerated  bool     `json:"metadataGenerated"`
    Warnings           []string `json:"warnings"`
    Errors             []string `json:"errors"`
}

// ImportService handles project imports
type ImportService struct {
    db          *gorm.DB
    pathManager *PathManager
    workDir     string
    fileService *FileService
}

// PreviewImport analyzes import source without executing
func (s *ImportService) PreviewImport(ctx context.Context, sourcePath string) (*ImportPreview, error) {
    sourceType, err := DetectImportSource(sourcePath)
    if err != nil {
        return nil, err
    }
    
    switch sourceType {
    case ImportSourceZip:
        return s.previewZipImport(ctx, sourcePath)
    case ImportSourceMarkdown, ImportSourcePRD:
        return s.previewMarkdownImport(ctx, sourcePath, sourceType)
    case ImportSourceFolder:
        return s.previewFolderImport(ctx, sourcePath)
    default:
        return nil, NewError(ERR_INVALID_FORMAT, "Unknown import source type")
    }
}

func (s *ImportService) previewZipImport(ctx context.Context, zipPath string) (*ImportPreview, error) {
    reader, err := zip.OpenReader(zipPath)
    if err != nil {
        return nil, NewError(ERR_FILE_READ, "Failed to open ZIP file")
    }
    defer reader.Close()
    
    preview := &ImportPreview{
        DetectedType: ImportSourceZip,
        Warnings:     []string{},
        FileTree:     []FileTreeNode{},
    }
    
    for _, file := range reader.File {
        if file.FileInfo().IsDir() {
            preview.FolderCount++
            continue
        }
        
        preview.FileCount++
        preview.TotalSizeBytes += int64(file.UncompressedSize64)
        
        name := file.Name
        
        // Check for key files
        if name == "spec.project.json" {
            preview.HasProjectJson = true
            // Parse to extract project name
            rc, _ := file.Open()
            content, _ := io.ReadAll(rc)
            rc.Close()
            
            var pj ProjectJson
            if json.Unmarshal(content, &pj) == nil {
                preview.ProjectName = pj.Name
                preview.DetectedCategory = &pj.Category
            }
        }
        
        if strings.HasSuffix(name, "00-overview.md") {
            preview.HasOverview = true
        }
        
        // Build file tree
        preview.FileTree = append(preview.FileTree, FileTreeNode{
            Path:  name,
            Size:  int64(file.UncompressedSize64),
            IsDir: false,
        })
    }
    
    // Determine if metadata generation is needed
    if !preview.HasProjectJson {
        preview.RequiresMetadata = true
        preview.Warnings = append(preview.Warnings, 
            "No spec.project.json found - metadata will be auto-generated")
        
        // Try to infer project name from folder structure
        if preview.ProjectName == "" {
            preview.ProjectName = inferProjectNameFromZip(reader)
        }
    }
    
    if !preview.HasOverview {
        preview.Warnings = append(preview.Warnings,
            "No 00-overview.md found - consider adding one for better organization")
    }
    
    return preview, nil
}

func (s *ImportService) previewMarkdownImport(
    ctx context.Context, 
    mdPath string, 
    sourceType ImportSourceType,
) (*ImportPreview, error) {
    content, err := os.ReadFile(mdPath)
    if err != nil {
        return nil, NewError(ERR_FILE_READ, "Failed to read markdown file")
    }
    
    preview := &ImportPreview{
        DetectedType:     sourceType,
        FileCount:        1,
        TotalSizeBytes:   int64(len(content)),
        HasProjectJson:   false,
        RequiresMetadata: true,
        Warnings:         []string{},
    }
    
    // Extract project name from filename or first heading
    preview.ProjectName = extractProjectName(mdPath, content)
    
    if sourceType == ImportSourcePRD {
        // Preview PRD section splitting
        sections := parsePRDSections(content)
        preview.Warnings = append(preview.Warnings,
            fmt.Sprintf("PRD detected: will split into %d spec files", len(sections)))
        preview.FileCount = len(sections)
    }
    
    return preview, nil
}

// ImportFromSource executes the import operation
func (s *ImportService) ImportFromSource(
    ctx context.Context,
    userId string,
    sourcePath string,
    options ImportOptions,
) (*ImportResult, error) {
    sourceType, err := DetectImportSource(sourcePath)
    if err != nil {
        return nil, err
    }
    
    switch sourceType {
    case ImportSourceZip:
        return s.importFromZip(ctx, userId, sourcePath, options)
    case ImportSourceMarkdown:
        return s.importFromMarkdown(ctx, userId, sourcePath, options, false)
    case ImportSourcePRD:
        return s.importFromMarkdown(ctx, userId, sourcePath, options, true)
    case ImportSourceFolder:
        return s.importFromFolder(ctx, userId, sourcePath, options)
    default:
        return nil, NewError(ERR_INVALID_FORMAT, "Unknown import source type")
    }
}

func (s *ImportService) importFromZip(
    ctx context.Context,
    userId string,
    zipPath string,
    options ImportOptions,
) (*ImportResult, error) {
    reader, err := zip.OpenReader(zipPath)
    if err != nil {
        return nil, NewError(ERR_FILE_READ, "Failed to open ZIP file")
    }
    defer reader.Close()
    
    result := &ImportResult{
        Warnings: []string{},
        Errors:   []string{},
    }
    
    // 1. Check for existing project with same name
    existingSlug := slugify(options.ProjectName)
    var existing models.Project
    if err := s.db.Where("slug = ?", existingSlug).First(&existing).Error; err == nil {
        switch options.ConflictStrategy {
        case ConflictSkip:
            return nil, NewError(ERR_DUPLICATE_ENTRY, "Project already exists")
        case ConflictRename:
            options.ProjectName = generateUniqueName(options.ProjectName, s.db)
        case ConflictOverwrite:
            // Will update existing project
            result.ProjectId = existing.Id
        }
    }
    
    // 2. Create or update project
    var project models.Project
    if result.ProjectId != "" {
        project = existing
    } else {
        project = models.Project{
            OwnerId:     userId,
            Name:        options.ProjectName,
            Slug:        slugify(options.ProjectName),
            Path:        slugify(options.ProjectName),
            Type:        models.ProjectTypeProject,
            Visibility:  options.Visibility,
        }
        if options.Category != nil {
            project.ParentId = s.findOrCreateCategory(ctx, userId, *options.Category)
        }
        if err := s.db.Create(&project).Error; err != nil {
            return nil, NewError(ERR_DATABASE, "Failed to create project")
        }
        result.ProjectId = project.Id
    }
    result.ProjectName = project.Name
    
    // 3. Create project directory
    projectDir := s.pathManager.ToAbsolute(project.Id, "")
    if err := os.MkdirAll(projectDir, 0755); err != nil {
        return nil, NewError(ERR_DIR_CREATE, "Failed to create project directory")
    }
    
    // 4. Extract files
    var projectJson *ProjectJson
    for _, file := range reader.File {
        if file.FileInfo().IsDir() {
            // Create directory
            dirPath := filepath.Join(projectDir, file.Name)
            os.MkdirAll(dirPath, 0755)
            result.FoldersCreated++
            continue
        }
        
        // Read file content
        rc, err := file.Open()
        if err != nil {
            result.Errors = append(result.Errors, 
                fmt.Sprintf("Failed to read %s: %v", file.Name, err))
            continue
        }
        content, _ := io.ReadAll(rc)
        rc.Close()
        
        // Handle spec.project.json specially
        if file.Name == "spec.project.json" {
            json.Unmarshal(content, &projectJson)
            continue // Don't copy manifest, will regenerate
        }
        
        // Skip export manifest
        if file.Name == "export-manifest.json" {
            continue
        }
        
        // Write file
        destPath := filepath.Join(projectDir, file.Name)
        os.MkdirAll(filepath.Dir(destPath), 0755)
        if err := os.WriteFile(destPath, content, 0644); err != nil {
            result.Errors = append(result.Errors,
                fmt.Sprintf("Failed to write %s: %v", file.Name, err))
            continue
        }
        
        // Create file record in database
        relPath := file.Name
        s.fileService.CreateFileRecord(ctx, project.Id, relPath, content)
        result.FilesImported++
    }
    
    // 5. Generate or update metadata
    if projectJson != nil {
        s.applyProjectMetadata(ctx, &project, projectJson)
    } else if options.GenerateMetadata {
        s.generateProjectMetadata(ctx, &project, projectDir)
        result.MetadataGenerated = true
    }
    
    // 6. Write spec.project.json
    s.writeProjectJson(projectDir, &project)
    
    return result, nil
}

func (s *ImportService) importFromMarkdown(
    ctx context.Context,
    userId string,
    mdPath string,
    options ImportOptions,
    isPRD bool,
) (*ImportResult, error) {
    content, err := os.ReadFile(mdPath)
    if err != nil {
        return nil, NewError(ERR_FILE_READ, "Failed to read markdown file")
    }
    
    result := &ImportResult{
        Warnings: []string{},
        Errors:   []string{},
    }
    
    // 1. Create project
    project := models.Project{
        OwnerId:    userId,
        Name:       options.ProjectName,
        Slug:       slugify(options.ProjectName),
        Path:       slugify(options.ProjectName),
        Type:       models.ProjectTypeProject,
        Visibility: options.Visibility,
    }
    if options.Category != nil {
        project.ParentId = s.findOrCreateCategory(ctx, userId, *options.Category)
    }
    if err := s.db.Create(&project).Error; err != nil {
        return nil, NewError(ERR_DATABASE, "Failed to create project")
    }
    result.ProjectId = project.Id
    result.ProjectName = project.Name
    
    // 2. Create project directory structure
    projectDir := s.pathManager.ToAbsolute(project.Id, "")
    specDir := filepath.Join(projectDir, "spec")
    os.MkdirAll(specDir, 0755)
    result.FoldersCreated++
    
    if isPRD && options.ParsePRDSections {
        // 3a. Parse PRD and split into multiple files
        sections := parsePRDSections(content)
        for i, section := range sections {
            fileName := fmt.Sprintf("%02d-%s.md", i, section.Slug)
            filePath := filepath.Join(specDir, fileName)
            
            if err := os.WriteFile(filePath, []byte(section.Content), 0644); err != nil {
                result.Errors = append(result.Errors,
                    fmt.Sprintf("Failed to write %s: %v", fileName, err))
                continue
            }
            
            relPath := filepath.Join("spec", fileName)
            s.fileService.CreateFileRecord(ctx, project.Id, relPath, []byte(section.Content))
            result.FilesImported++
        }
    } else {
        // 3b. Import as single file (00-overview.md)
        overviewPath := filepath.Join(specDir, "00-overview.md")
        if err := os.WriteFile(overviewPath, content, 0644); err != nil {
            return nil, NewError(ERR_FILE_WRITE, "Failed to write overview file")
        }
        
        s.fileService.CreateFileRecord(ctx, project.Id, "spec/00-overview.md", content)
        result.FilesImported = 1
    }
    
    // 4. Generate metadata
    s.generateProjectMetadata(ctx, &project, projectDir)
    s.writeProjectJson(projectDir, &project)
    result.MetadataGenerated = true
    
    return result, nil
}

// generateProjectMetadata creates metadata when spec.project.json is missing
func (s *ImportService) generateProjectMetadata(
    ctx context.Context, 
    project *models.Project, 
    projectDir string,
) error {
    metadata := models.ProjectMetadata{
        ProjectId: project.Id,
        Version:   "1.0.0",
    }
    
    // Try to infer from content
    overviewPath := filepath.Join(projectDir, "spec", "00-overview.md")
    if content, err := os.ReadFile(overviewPath); err == nil {
        // Extract description from first paragraph
        description := extractFirstParagraph(string(content))
        if description != "" {
            metadata.Summary = &description
        }
        
        // Try to detect language/framework from content
        lang, framework := detectTechStack(string(content))
        if lang != "" {
            metadata.Language = &lang
        }
        if framework != "" {
            metadata.Framework = &framework
        }
    }
    
    return s.db.Create(&metadata).Error
}
```

### 29.3.4 PRD Section Parsing

```go
// PRDSection represents a parsed section from a PRD document
type PRDSection struct {
    Title   string `json:"title"`
    Slug    string `json:"slug"`
    Content string `json:"content"`
    Level   int    `json:"level"`
}

// parsePRDSections splits a PRD into logical sections
func parsePRDSections(content []byte) []PRDSection {
    text := string(content)
    lines := strings.Split(text, "\n")
    
    var sections []PRDSection
    var currentSection *PRDSection
    var currentContent strings.Builder
    
    headingRegex := regexp.MustCompile(`^(#{1,2})\s+(.+)$`)
    
    for _, line := range lines {
        if match := headingRegex.FindStringSubmatch(line); match != nil {
            // Save previous section
            if currentSection != nil {
                currentSection.Content = strings.TrimSpace(currentContent.String())
                sections = append(sections, *currentSection)
            }
            
            // Start new section
            level := len(match[1])
            title := match[2]
            currentSection = &PRDSection{
                Title: title,
                Slug:  slugify(title),
                Level: level,
            }
            currentContent.Reset()
            currentContent.WriteString(line + "\n")
        } else if currentSection != nil {
            currentContent.WriteString(line + "\n")
        }
    }
    
    // Don't forget last section
    if currentSection != nil {
        currentSection.Content = strings.TrimSpace(currentContent.String())
        sections = append(sections, *currentSection)
    }
    
    // If no sections detected, return single overview
    if len(sections) == 0 {
        return []PRDSection{{
            Title:   "Overview",
            Slug:    "overview",
            Content: text,
            Level:   1,
        }}
    }
    
    return sections
}
```

### 29.3.5 Import API Endpoints

**POST /api/v1/import/preview**

Preview import without executing.

```json
// Request (multipart/form-data)
{
  "file": <uploaded-file.zip>
}

// Response
{
  "success": true,
  "data": {
    "detectedType": "zip",
    "projectName": "exam-manager",
    "fileCount": 45,
    "folderCount": 8,
    "totalSizeBytes": 256000,
    "hasProjectJson": true,
    "hasOverview": true,
    "detectedCategory": "WordPress Plugins",
    "warnings": [],
    "requiresMetadata": false,
    "fileTree": [
      {"path": "spec/00-overview.md", "size": 2048, "isDir": false},
      {"path": "spec/01-backend/", "size": 0, "isDir": true}
    ]
  }
}
```

**POST /api/v1/import/execute**

Execute import with options.

```json
// Request (multipart/form-data)
{
  "file": <uploaded-file.zip>,
  "options": {
    "projectName": "My Exam Manager",
    "category": "WordPress Plugins",
    "visibility": "user",
    "conflictStrategy": "rename",
    "generateMetadata": true,
    "parsePrdSections": true
  }
}

// Response
{
  "success": true,
  "data": {
    "projectId": "project-uuid",
    "projectName": "My Exam Manager",
    "filesImported": 45,
    "foldersCreated": 8,
    "metadataGenerated": false,
    "warnings": [],
    "errors": []
  }
}
```

---

## 29.4 Acceptance Criteria

### Export Criteria

- [ ] Export creates valid ZIP file with all project files
- [ ] ZIP includes `spec.project.json` with all metadata
- [ ] ZIP includes `export-manifest.json` with checksums
- [ ] Large exports use background processing with progress
- [ ] Export respects user selection (includeHistory, etc.)
- [ ] Exported ZIP can be re-imported successfully
- [ ] File named with pattern: `{slug}-export-YYYY-MM-DD.zip`

### Import Criteria

- [ ] Detects ZIP, Markdown, and PRD file types correctly
- [ ] Preview shows accurate file count and structure
- [ ] Import creates project and all files in database
- [ ] Auto-generates `spec.project.json` when missing
- [ ] PRD files are split into logical section files
- [ ] Conflict handling works (skip/rename/overwrite)
- [ ] Preserves folder structure from ZIP
- [ ] Import validates path security (no traversal)

### Metadata Generation Criteria

- [ ] Infers project name from folder/file name
- [ ] Extracts description from 00-overview.md
- [ ] Detects tech stack from content analysis
- [ ] Creates valid spec.project.json on disk
- [ ] Syncs metadata to database ProjectMetadata table

---

## 29.5 Error Codes

| Code | Constant | Message |
|------|----------|---------|
| ERR_6020 | ERR_IMPORT_INVALID_FORMAT | Invalid import file format |
| ERR_6021 | ERR_IMPORT_CORRUPT_ZIP | Corrupt or invalid ZIP file |
| ERR_6022 | ERR_IMPORT_TOO_LARGE | Import file exceeds size limit |
| ERR_6023 | ERR_IMPORT_PATH_UNSAFE | Unsafe path detected in archive |
| ERR_6024 | ERR_EXPORT_IN_PROGRESS | Export already in progress |
| ERR_6025 | ERR_EXPORT_EXPIRED | Export download has expired |
| ERR_6026 | ERR_METADATA_PARSE | Failed to parse project metadata |
