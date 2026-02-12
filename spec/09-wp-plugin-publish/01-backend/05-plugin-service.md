# 05 — Plugin Service

> **Parent:** [00-overview.md](../00-overview.md)  
> **Status:** Draft

---

## Overview

The Plugin Service manages local plugin directories, including registration, file scanning, hash calculation, and mapping to remote WordPress sites.

---

## Interface

```go
// internal/services/plugin/service.go
package plugin

import (
    "context"
    
    "wp-plugin-publish/internal/models"
)

type Service interface {
    // CRUD operations
    List(ctx context.Context) ([]models.Plugin, error)
    ListBySite(ctx context.Context, siteID int64) ([]models.Plugin, error)
    GetByID(ctx context.Context, id int64) (*models.Plugin, error)
    Create(ctx context.Context, input CreateInput) (*models.Plugin, error)
    Update(ctx context.Context, id int64, input UpdateInput) (*models.Plugin, error)
    Delete(ctx context.Context, id int64) error
    
    // Directory scanning
    ScanDirectory(ctx context.Context, path string) (*DirectoryScan, error)
    ValidatePath(ctx context.Context, path string) error
    
    // Hash management
    CalculateHash(ctx context.Context, id int64) (string, error)
    UpdateHash(ctx context.Context, id int64, hash string) error
    
    // Watcher management
    SetWatching(ctx context.Context, id int64, watching bool) error
    GetWatchedPlugins(ctx context.Context) ([]models.Plugin, error)
    
    // Status
    UpdateLastPublished(ctx context.Context, id int64) error
}
```

---

## Data Types

### Plugin Model

```go
// internal/models/plugin.go
package models

import "time"

type Plugin struct {
    ID              int64      `json:"id"`
    Name            string     `json:"name"`
    LocalPath       string     `json:"localPath"`
    RemoteSlug      string     `json:"remoteSlug"`
    SiteID          int64      `json:"siteId"`
    IsActive        bool       `json:"isActive"`
    IsWatching      bool       `json:"isWatching"`
    LastPublishedAt *time.Time `json:"lastPublishedAt,omitempty"`
    LastHash        string     `json:"lastHash,omitempty"`
    CreatedAt       time.Time  `json:"createdAt"`
    UpdatedAt       time.Time  `json:"updatedAt"`
    
    // Joined data
    Site            *Site      `json:"site,omitempty"`
}

// PluginWithStatus includes sync and file status
type PluginWithStatus struct {
    Plugin
    PendingChanges  int    `json:"pendingChanges"`
    TotalFiles      int    `json:"totalFiles"`
    TotalSize       int64  `json:"totalSize"`
    RemoteVersion   string `json:"remoteVersion,omitempty"`
    LocalVersion    string `json:"localVersion,omitempty"`
    IsSynced        bool   `json:"isSynced"`
}
```

### Input Types

```go
// internal/services/plugin/types.go
package plugin

type CreateInput struct {
    Name       string `json:"name" validate:"required,max=255"`
    LocalPath  string `json:"localPath" validate:"required,max=4096"`
    RemoteSlug string `json:"remoteSlug" validate:"required,max=255,lowercase"`
    SiteID     int64  `json:"siteId" validate:"required"`
}

type UpdateInput struct {
    Name       *string `json:"name,omitempty" validate:"omitempty,max=255"`
    LocalPath  *string `json:"localPath,omitempty" validate:"omitempty,max=4096"`
    RemoteSlug *string `json:"remoteSlug,omitempty" validate:"omitempty,max=255,lowercase"`
    IsActive   *bool   `json:"isActive,omitempty"`
}

type DirectoryScan struct {
    Path        string     `json:"path"`
    IsValid     bool       `json:"isValid"`
    PluginName  string     `json:"pluginName,omitempty"`
    Version     string     `json:"version,omitempty"`
    MainFile    string     `json:"mainFile,omitempty"`
    Files       []FileInfo `json:"files"`
    TotalSize   int64      `json:"totalSize"`
    Error       string     `json:"error,omitempty"`
}

type FileInfo struct {
    Path         string    `json:"path"`         // Relative path within plugin
    Size         int64     `json:"size"`
    Hash         string    `json:"hash"`         // MD5 or SHA256
    ModifiedAt   time.Time `json:"modifiedAt"`
    IsDirectory  bool      `json:"isDirectory"`
}
```

---

## Implementation

### Service Constructor

```go
// internal/services/plugin/service.go
package plugin

import (
    "database/sql"
    
    "wp-plugin-publish/internal/logger"
    "wp-plugin-publish/internal/services/site"
)

type serviceImpl struct {
    db          *sql.DB
    siteService site.Service
    log         *logger.Logger
}

func New(db *sql.DB, siteService site.Service, log *logger.Logger) Service {
    return &serviceImpl{
        db:          db,
        siteService: siteService,
        log:         log,
    }
}
```

### CRUD Operations

```go
// internal/services/plugin/crud.go
package plugin

import (
    "context"
    "database/sql"
    "strings"
    "time"
    
    "wp-plugin-publish/internal/models"
    "wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) List(ctx context.Context) ([]models.Plugin, error) {
    s.log.Debug("Listing all plugins")
    
    rows, err := s.db.QueryContext(ctx, `
        SELECT p.Id, p.Name, p.LocalPath, p.RemoteSlug, p.SiteId, 
               p.IsActive, p.IsWatching, p.LastPublishedAt, p.LastHash,
               p.CreatedAt, p.UpdatedAt,
               s.Id, s.Name, s.Url
        FROM Plugins p
        JOIN Sites s ON p.SiteId = s.Id
        ORDER BY p.Name ASC
    `)
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list plugins")
    }
    defer rows.Close()
    
    return s.scanPlugins(rows)
}

func (s *serviceImpl) ListBySite(ctx context.Context, siteID int64) ([]models.Plugin, error) {
    s.log.Debug("Listing plugins by site", "site_id", siteID)
    
    rows, err := s.db.QueryContext(ctx, `
        SELECT p.Id, p.Name, p.LocalPath, p.RemoteSlug, p.SiteId, 
               p.IsActive, p.IsWatching, p.LastPublishedAt, p.LastHash,
               p.CreatedAt, p.UpdatedAt,
               s.Id, s.Name, s.Url
        FROM Plugins p
        JOIN Sites s ON p.SiteId = s.Id
        WHERE p.SiteId = ?
        ORDER BY p.Name ASC
    `, siteID)
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list plugins by site")
    }
    defer rows.Close()
    
    return s.scanPlugins(rows)
}

func (s *serviceImpl) GetByID(ctx context.Context, id int64) (*models.Plugin, error) {
    s.log.Debug("Getting plugin by ID", "plugin_id", id)
    
    var plugin models.Plugin
    var site models.Site
    var lastPublishedAt, lastHash sql.NullString
    
    err := s.db.QueryRowContext(ctx, `
        SELECT p.Id, p.Name, p.LocalPath, p.RemoteSlug, p.SiteId, 
               p.IsActive, p.IsWatching, p.LastPublishedAt, p.LastHash,
               p.CreatedAt, p.UpdatedAt,
               s.Id, s.Name, s.Url
        FROM Plugins p
        JOIN Sites s ON p.SiteId = s.Id
        WHERE p.Id = ?
    `, id).Scan(
        &plugin.ID, &plugin.Name, &plugin.LocalPath, &plugin.RemoteSlug,
        &plugin.SiteID, &plugin.IsActive, &plugin.IsWatching,
        &lastPublishedAt, &lastHash, &plugin.CreatedAt, &plugin.UpdatedAt,
        &site.ID, &site.Name, &site.URL,
    )
    
    if err == sql.ErrNoRows {
        return nil, apperror.New(apperror.ErrNotFound, "plugin not found").
            WithContext("plugin_id", id)
    }
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get plugin")
    }
    
    if lastPublishedAt.Valid {
        t, _ := time.Parse(time.RFC3339, lastPublishedAt.String)
        plugin.LastPublishedAt = &t
    }
    if lastHash.Valid {
        plugin.LastHash = lastHash.String
    }
    
    plugin.Site = &site
    return &plugin, nil
}

func (s *serviceImpl) Create(ctx context.Context, input CreateInput) (*models.Plugin, error) {
    s.log.Info("Creating plugin", "name", input.Name, "path", input.LocalPath)
    
    // Validate input
    if err := s.validateCreateInput(ctx, input); err != nil {
        return nil, err
    }
    
    // Verify site exists
    if _, err := s.siteService.GetByID(ctx, input.SiteID); err != nil {
        return nil, err
    }
    
    // Check for duplicate path + site combo
    var exists int
    err := s.db.QueryRowContext(ctx,
        "SELECT 1 FROM Plugins WHERE LocalPath = ? AND SiteId = ?",
        input.LocalPath, input.SiteID,
    ).Scan(&exists)
    if err != sql.ErrNoRows {
        return nil, apperror.New(apperror.ErrDuplicate, "plugin already registered for this site").
            WithContext("path", input.LocalPath).
            WithContext("site_id", input.SiteID)
    }
    
    // Validate directory exists and is a valid plugin
    if err := s.ValidatePath(ctx, input.LocalPath); err != nil {
        return nil, err
    }
    
    // Calculate initial hash
    hash, _ := s.calculateDirectoryHash(input.LocalPath)
    
    // Insert plugin
    result, err := s.db.ExecContext(ctx, `
        INSERT INTO Plugins (Name, LocalPath, RemoteSlug, SiteId, IsActive, IsWatching, LastHash, CreatedAt, UpdatedAt)
        VALUES (?, ?, ?, ?, 1, 0, ?, datetime('now'), datetime('now'))
    `, input.Name, input.LocalPath, strings.ToLower(input.RemoteSlug), input.SiteID, hash)
    
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to create plugin")
    }
    
    id, _ := result.LastInsertId()
    s.log.Info("Plugin created", "plugin_id", id, "name", input.Name)
    
    return s.GetByID(ctx, id)
}

func (s *serviceImpl) Delete(ctx context.Context, id int64) error {
    s.log.Info("Deleting plugin", "plugin_id", id)
    
    // Verify plugin exists
    if _, err := s.GetByID(ctx, id); err != nil {
        return err
    }
    
    _, err := s.db.ExecContext(ctx, "DELETE FROM Plugins WHERE Id = ?", id)
    if err != nil {
        return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to delete plugin")
    }
    
    s.log.Info("Plugin deleted", "plugin_id", id)
    return nil
}
```

### Directory Scanning

```go
// internal/services/plugin/scanner.go
package plugin

import (
    "bufio"
    "context"
    "os"
    "path/filepath"
    "regexp"
    "strings"
    
    "wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) ScanDirectory(ctx context.Context, path string) (*DirectoryScan, error) {
    s.log.Debug("Scanning directory", "path", path)
    
    scan := &DirectoryScan{
        Path:    path,
        IsValid: false,
        Files:   []FileInfo{},
    }
    
    // Check if directory exists
    info, err := os.Stat(path)
    if os.IsNotExist(err) {
        scan.Error = "directory does not exist"
        return scan, nil
    }
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrDirRead, "failed to stat directory")
    }
    if !info.IsDir() {
        scan.Error = "path is not a directory"
        return scan, nil
    }
    
    // Find main plugin file
    mainFile, pluginName, version, err := s.findMainPluginFile(path)
    if err != nil {
        scan.Error = err.Error()
        return scan, nil
    }
    
    scan.IsValid = true
    scan.MainFile = mainFile
    scan.PluginName = pluginName
    scan.Version = version
    
    // Walk directory and collect files
    err = filepath.Walk(path, func(filePath string, info os.FileInfo, err error) error {
        if err != nil {
            return nil // Skip inaccessible files
        }
        
        // Get relative path
        relPath, _ := filepath.Rel(path, filePath)
        if relPath == "." {
            return nil
        }
        
        // Skip hidden files and common ignored directories
        base := filepath.Base(filePath)
        if strings.HasPrefix(base, ".") || base == "node_modules" || base == "vendor" {
            if info.IsDir() {
                return filepath.SkipDir
            }
            return nil
        }
        
        fileInfo := FileInfo{
            Path:        relPath,
            Size:        info.Size(),
            ModifiedAt:  info.ModTime(),
            IsDirectory: info.IsDir(),
        }
        
        if !info.IsDir() {
            fileInfo.Hash, _ = calculateFileHash(filePath)
            scan.TotalSize += info.Size()
        }
        
        scan.Files = append(scan.Files, fileInfo)
        return nil
    })
    
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrDirRead, "failed to scan directory")
    }
    
    s.log.Info("Directory scanned",
        "path", path,
        "plugin_name", pluginName,
        "files", len(scan.Files),
        "size", scan.TotalSize,
    )
    
    return scan, nil
}

func (s *serviceImpl) ValidatePath(ctx context.Context, path string) error {
    scan, err := s.ScanDirectory(ctx, path)
    if err != nil {
        return err
    }
    
    if !scan.IsValid {
        return apperror.New(apperror.ErrPathInvalid, scan.Error).
            WithContext("path", path)
    }
    
    return nil
}

// findMainPluginFile locates the main plugin PHP file with the plugin header
func (s *serviceImpl) findMainPluginFile(path string) (string, string, string, error) {
    entries, err := os.ReadDir(path)
    if err != nil {
        return "", "", "", err
    }
    
    pluginNameRegex := regexp.MustCompile(`Plugin Name:\s*(.+)`)
    versionRegex := regexp.MustCompile(`Version:\s*(.+)`)
    
    for _, entry := range entries {
        if entry.IsDir() || !strings.HasSuffix(entry.Name(), ".php") {
            continue
        }
        
        filePath := filepath.Join(path, entry.Name())
        file, err := os.Open(filePath)
        if err != nil {
            continue
        }
        
        scanner := bufio.NewScanner(file)
        lineCount := 0
        var pluginName, version string
        
        for scanner.Scan() && lineCount < 30 {
            line := scanner.Text()
            lineCount++
            
            if matches := pluginNameRegex.FindStringSubmatch(line); len(matches) > 1 {
                pluginName = strings.TrimSpace(matches[1])
            }
            if matches := versionRegex.FindStringSubmatch(line); len(matches) > 1 {
                version = strings.TrimSpace(matches[1])
            }
        }
        file.Close()
        
        if pluginName != "" {
            return entry.Name(), pluginName, version, nil
        }
    }
    
    return "", "", "", apperror.New(apperror.ErrPathInvalid, 
        "no valid WordPress plugin file found (missing Plugin Name header)")
}
```

### Hash Calculation

```go
// internal/services/plugin/hasher.go
package plugin

import (
    "context"
    "crypto/sha256"
    "encoding/hex"
    "io"
    "os"
    "path/filepath"
    "sort"
    "strings"
    
    "wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) CalculateHash(ctx context.Context, id int64) (string, error) {
    plugin, err := s.GetByID(ctx, id)
    if err != nil {
        return "", err
    }
    
    return s.calculateDirectoryHash(plugin.LocalPath)
}

func (s *serviceImpl) UpdateHash(ctx context.Context, id int64, hash string) error {
    _, err := s.db.ExecContext(ctx,
        "UPDATE Plugins SET LastHash = ?, UpdatedAt = datetime('now') WHERE Id = ?",
        hash, id,
    )
    if err != nil {
        return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to update plugin hash")
    }
    return nil
}

func (s *serviceImpl) calculateDirectoryHash(path string) (string, error) {
    hasher := sha256.New()
    
    // Collect all file hashes in sorted order for deterministic output
    var fileHashes []string
    
    err := filepath.Walk(path, func(filePath string, info os.FileInfo, err error) error {
        if err != nil || info.IsDir() {
            return nil
        }
        
        // Skip hidden files
        if strings.HasPrefix(filepath.Base(filePath), ".") {
            return nil
        }
        
        relPath, _ := filepath.Rel(path, filePath)
        fileHash, err := calculateFileHash(filePath)
        if err != nil {
            return nil // Skip files we can't read
        }
        
        fileHashes = append(fileHashes, relPath+":"+fileHash)
        return nil
    })
    
    if err != nil {
        return "", apperror.Wrap(err, apperror.ErrDirRead, "failed to walk directory")
    }
    
    // Sort for deterministic ordering
    sort.Strings(fileHashes)
    
    for _, fh := range fileHashes {
        hasher.Write([]byte(fh))
    }
    
    return hex.EncodeToString(hasher.Sum(nil)), nil
}

func calculateFileHash(path string) (string, error) {
    file, err := os.Open(path)
    if err != nil {
        return "", err
    }
    defer file.Close()
    
    hasher := sha256.New()
    if _, err := io.Copy(hasher, file); err != nil {
        return "", err
    }
    
    return hex.EncodeToString(hasher.Sum(nil)), nil
}
```

### Watcher Management

```go
// internal/services/plugin/watcher.go
package plugin

import (
    "context"
    
    "wp-plugin-publish/internal/models"
    "wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) SetWatching(ctx context.Context, id int64, watching bool) error {
    s.log.Info("Setting plugin watching status", "plugin_id", id, "watching", watching)
    
    watchingInt := 0
    if watching {
        watchingInt = 1
    }
    
    _, err := s.db.ExecContext(ctx,
        "UPDATE Plugins SET IsWatching = ?, UpdatedAt = datetime('now') WHERE Id = ?",
        watchingInt, id,
    )
    if err != nil {
        return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to update watching status")
    }
    
    return nil
}

func (s *serviceImpl) GetWatchedPlugins(ctx context.Context) ([]models.Plugin, error) {
    s.log.Debug("Getting watched plugins")
    
    rows, err := s.db.QueryContext(ctx, `
        SELECT p.Id, p.Name, p.LocalPath, p.RemoteSlug, p.SiteId, 
               p.IsActive, p.IsWatching, p.LastPublishedAt, p.LastHash,
               p.CreatedAt, p.UpdatedAt,
               s.Id, s.Name, s.Url
        FROM Plugins p
        JOIN Sites s ON p.SiteId = s.Id
        WHERE p.IsWatching = 1 AND p.IsActive = 1
        ORDER BY p.Name ASC
    `)
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get watched plugins")
    }
    defer rows.Close()
    
    return s.scanPlugins(rows)
}

func (s *serviceImpl) UpdateLastPublished(ctx context.Context, id int64) error {
    _, err := s.db.ExecContext(ctx,
        "UPDATE Plugins SET LastPublishedAt = datetime('now'), UpdatedAt = datetime('now') WHERE Id = ?",
        id,
    )
    if err != nil {
        return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to update last published")
    }
    return nil
}
```

### Validation

```go
// internal/services/plugin/validator.go
package plugin

import (
    "context"
    "path/filepath"
    "regexp"
    "strings"
    
    "wp-plugin-publish/pkg/apperror"
)

var slugRegex = regexp.MustCompile(`^[a-z0-9-]+$`)

func (s *serviceImpl) validateCreateInput(ctx context.Context, input CreateInput) error {
    if strings.TrimSpace(input.Name) == "" {
        return apperror.New(apperror.ErrValidationEmpty, "plugin name is required")
    }
    
    if len(input.Name) > 255 {
        return apperror.New(apperror.ErrValidationLength, "plugin name must be 255 characters or less")
    }
    
    if strings.TrimSpace(input.LocalPath) == "" {
        return apperror.New(apperror.ErrValidationEmpty, "local path is required")
    }
    
    // Must be absolute path
    if !filepath.IsAbs(input.LocalPath) {
        return apperror.New(apperror.ErrValidationPath, "local path must be absolute")
    }
    
    if len(input.LocalPath) > 4096 {
        return apperror.New(apperror.ErrValidationLength, "local path must be 4096 characters or less")
    }
    
    if strings.TrimSpace(input.RemoteSlug) == "" {
        return apperror.New(apperror.ErrValidationEmpty, "remote slug is required")
    }
    
    slug := strings.ToLower(strings.TrimSpace(input.RemoteSlug))
    if !slugRegex.MatchString(slug) {
        return apperror.New(apperror.ErrValidationFormat, 
            "remote slug must be lowercase with only letters, numbers, and hyphens")
    }
    
    if len(input.RemoteSlug) > 255 {
        return apperror.New(apperror.ErrValidationLength, "remote slug must be 255 characters or less")
    }
    
    if input.SiteID <= 0 {
        return apperror.New(apperror.ErrValidationEmpty, "site ID is required")
    }
    
    return nil
}
```

---

## Error Scenarios

| Scenario | Error Code | HTTP Status |
|----------|------------|-------------|
| Plugin not found | E2005 | 404 |
| Duplicate plugin+site | E2006 | 409 |
| Path not found | E4009 | 400 |
| Invalid plugin directory | E4008 | 400 |
| Invalid slug format | E6006 | 400 |

---

## Next Document

See [06-file-watcher.md](./06-file-watcher.md) for file change detection.
