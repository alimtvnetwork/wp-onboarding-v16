# 32 — Publish Service Implementation

> **Location:** `spec/wp-plugin-publish/03-implementation/32-publish-service-impl.md`  
> **Updated:** 2026-02-01  
> **Status:** Implementation Spec

---

## Overview

Complete Go implementation for the Publish Service. This service manages plugin publishing to WordPress sites, including validation, packaging, upload, and activation.

---

## File Structure

```
backend/internal/services/publish/
├── service.go      # Main service interface and constructor
├── pipeline.go     # Publishing pipeline orchestration
├── packager.go     # ZIP file creation
├── uploader.go     # File upload to WordPress
└── types.go        # Input/output types
```

---

## Implementation: types.go

```go
package publish

import "time"

// PublishOptions configures the publish operation
type PublishOptions struct {
	Mode         string   `json:"mode"`         // "full" or "selected"
	Files        []string `json:"files"`        // files to publish (for selected mode)
	CreateBackup bool     `json:"createBackup"` // backup before publishing
	Activate     bool     `json:"activate"`     // activate plugin after publish
	DryRun       bool     `json:"dryRun"`       // validate without publishing
}

// PublishResult represents the outcome of a publish operation
type PublishResult struct {
	PublishID        string       `json:"publishId"`
	Success          bool         `json:"success"`
	PluginID         int64        `json:"pluginId"`
	SiteID           int64        `json:"siteId"`
	FilesUploaded    int          `json:"filesUploaded"`
	BytesTransferred int64        `json:"bytesTransferred"`
	BackupID         *int64       `json:"backupId,omitempty"`
	ActivationStatus string       `json:"activationStatus"` // active, inactive, error
	Duration         int64        `json:"duration"`         // milliseconds
	Stages           []StageResult `json:"stages"`
	Error            string       `json:"error,omitempty"`
}

// StageResult tracks individual pipeline stage outcomes
type StageResult struct {
	Name     string `json:"name"`
	Status   string `json:"status"` // pending, running, success, failed, skipped
	Duration int64  `json:"duration"`
	Error    string `json:"error,omitempty"`
}

// PackageInfo describes a created plugin package
type PackageInfo struct {
	Path      string    `json:"path"`
	Size      int64     `json:"size"`
	FileCount int       `json:"fileCount"`
	Checksum  string    `json:"checksum"`
	CreatedAt time.Time `json:"createdAt"`
}
```

---

## Implementation: service.go

```go
package publish

import (
	"context"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/services/backup"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/internal/ws"
)

// Service interface for publish operations
type Service interface {
	// Publishing
	Publish(ctx context.Context, pluginID, siteID int64, opts PublishOptions) (*PublishResult, error)
	PublishToAll(ctx context.Context, pluginID int64, opts PublishOptions) ([]PublishResult, error)

	// Packaging
	CreatePackage(ctx context.Context, pluginID int64, files []string) (*PackageInfo, error)

	// History
	GetHistory(ctx context.Context, pluginID int64, siteID *int64) ([]PublishResult, error)

	// Rollback
	Rollback(ctx context.Context, pluginID, siteID, backupID int64) (*PublishResult, error)
}

// Config holds publish service configuration
type Config struct {
	DB              *database.DB
	Logger          *logger.Logger
	PluginService   plugin.Service
	BackupService   *backup.Service
	SyncService     sync.Service
	WPClientFactory func(url, user, pass string) *wordpress.Client
	TempDir         string
	WSHub           *ws.Hub
}

type serviceImpl struct {
	db              *database.DB
	log             *logger.Logger
	pluginService   plugin.Service
	backupService   *backup.Service
	syncService     sync.Service
	wpClientFactory func(url, user, pass string) *wordpress.Client
	tempDir         string
	wsHub           *ws.Hub
}

// New creates a new publish service
func New(cfg Config) Service {
	return &serviceImpl{
		db:              cfg.DB,
		log:             cfg.Logger,
		pluginService:   cfg.PluginService,
		backupService:   cfg.BackupService,
		syncService:     cfg.SyncService,
		wpClientFactory: cfg.WPClientFactory,
		tempDir:         cfg.TempDir,
		wsHub:           cfg.WSHub,
	}
}
```

---

## Implementation: pipeline.go

```go
package publish

import (
	"context"
	"fmt"
	"time"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"

	"github.com/google/uuid"
)

func (s *serviceImpl) Publish(ctx context.Context, pluginID, siteID int64, opts PublishOptions) (*PublishResult, error) {
	publishID := uuid.New().String()[:8]
	startTime := time.Now()

	s.log.Info("Starting publish", "publishId", publishID, "pluginId", pluginID, "siteId", siteID)

	result := &PublishResult{
		PublishID: publishID,
		PluginID:  pluginID,
		SiteID:    siteID,
		Stages:    make([]StageResult, 0),
	}

	// Broadcast publish started
	s.wsHub.Broadcast(ws.EventPublishStarted, map[string]interface{}{
		"publishId": publishID,
		"pluginId":  pluginID,
		"siteId":    siteID,
		"mode":      opts.Mode,
	})

	// Get plugin details
	plugin, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		return s.failPublish(result, "validate", err, startTime)
	}

	// Get site details
	var site models.Site
	err = s.db.QueryRowContext(ctx, `
		SELECT Id, Name, Url, Username, PasswordEncrypted
		FROM Sites WHERE Id = ?
	`, siteID).Scan(&site.ID, &site.Name, &site.URL, &site.Username, &site.PasswordEncrypted)
	if err != nil {
		return s.failPublish(result, "validate", apperror.New(apperror.ErrNotFound, "site not found"), startTime)
	}

	// Get remote slug from mapping
	var remoteSlug string
	err = s.db.QueryRowContext(ctx, `
		SELECT RemoteSlug FROM PluginMappings
		WHERE PluginId = ? AND SiteId = ?
	`, pluginID, siteID).Scan(&remoteSlug)
	if err != nil {
		return s.failPublish(result, "validate", apperror.New(apperror.ErrNotFound, "plugin not mapped to site"), startTime)
	}

	// Stage 1: Validate
	result.Stages = append(result.Stages, s.runStage("validate", func() error {
		return s.pluginService.ValidatePath(ctx, plugin.Path)
	}))
	if result.Stages[0].Status == "failed" {
		return s.failPublish(result, "validate", fmt.Errorf(result.Stages[0].Error), startTime)
	}

	// Stage 2: Backup (optional)
	if opts.CreateBackup {
		result.Stages = append(result.Stages, s.runStage("backup", func() error {
			backup, err := s.backupService.CreateFromRemote(ctx, pluginID, siteID)
			if err != nil {
				return err
			}
			result.BackupID = &backup.ID
			return nil
		}))
		if result.Stages[len(result.Stages)-1].Status == "failed" {
			s.log.Warn("Backup failed, continuing publish", "error", result.Stages[len(result.Stages)-1].Error)
		}
	}

	// Stage 3: Package
	var pkg *PackageInfo
	result.Stages = append(result.Stages, s.runStage("package", func() error {
		var err error
		pkg, err = s.CreatePackage(ctx, pluginID, opts.Files)
		return err
	}))
	if result.Stages[len(result.Stages)-1].Status == "failed" {
		return s.failPublish(result, "package", fmt.Errorf(result.Stages[len(result.Stages)-1].Error), startTime)
	}

	// Dry run stops here
	if opts.DryRun {
		result.Success = true
		result.Duration = time.Since(startTime).Milliseconds()
		return result, nil
	}

	// Stage 4: Upload
	wpClient := s.wpClientFactory(site.URL, site.Username, string(site.PasswordEncrypted))
	result.Stages = append(result.Stages, s.runStage("upload", func() error {
		return s.uploadPackage(ctx, wpClient, pkg.Path, remoteSlug)
	}))
	if result.Stages[len(result.Stages)-1].Status == "failed" {
		return s.failPublish(result, "upload", fmt.Errorf(result.Stages[len(result.Stages)-1].Error), startTime)
	}
	result.FilesUploaded = pkg.FileCount
	result.BytesTransferred = pkg.Size

	// Stage 5: Activate (optional)
	if opts.Activate {
		result.Stages = append(result.Stages, s.runStage("activate", func() error {
			return wpClient.ActivatePlugin(ctx, remoteSlug)
		}))
		if result.Stages[len(result.Stages)-1].Status == "failed" {
			result.ActivationStatus = "error"
		} else {
			result.ActivationStatus = "active"
		}
	} else {
		result.ActivationStatus = "inactive"
	}

	// Mark files as synced
	if len(opts.Files) > 0 {
		s.syncService.MarkSynced(ctx, pluginID, siteID, opts.Files)
	}

	// Update publish timestamp
	s.db.ExecContext(ctx, `
		UPDATE PluginMappings
		SET LastSyncAt = datetime('now'), SyncStatus = 'synced', UpdatedAt = datetime('now')
		WHERE PluginId = ? AND SiteId = ?
	`, pluginID, siteID)

	result.Success = true
	result.Duration = time.Since(startTime).Milliseconds()

	// Broadcast publish complete
	s.wsHub.Broadcast(ws.EventPublishComplete, map[string]interface{}{
		"publishId":     publishID,
		"pluginId":      pluginID,
		"siteId":        siteID,
		"success":       true,
		"filesUploaded": result.FilesUploaded,
	})

	s.log.Info("Publish complete", "publishId", publishID, "duration", result.Duration)
	return result, nil
}

func (s *serviceImpl) runStage(name string, fn func() error) StageResult {
	start := time.Now()
	stage := StageResult{Name: name, Status: "running"}

	s.wsHub.Broadcast(ws.EventPublishProgress, map[string]interface{}{
		"stage":  name,
		"status": "running",
	})

	err := fn()
	stage.Duration = time.Since(start).Milliseconds()

	if err != nil {
		stage.Status = "failed"
		stage.Error = err.Error()
	} else {
		stage.Status = "success"
	}

	s.wsHub.Broadcast(ws.EventPublishProgress, map[string]interface{}{
		"stage":    name,
		"status":   stage.Status,
		"duration": stage.Duration,
	})

	return stage
}

func (s *serviceImpl) failPublish(result *PublishResult, stage string, err error, startTime time.Time) (*PublishResult, error) {
	result.Success = false
	result.Error = err.Error()
	result.Duration = time.Since(startTime).Milliseconds()

	s.wsHub.Broadcast(ws.EventPublishFailed, map[string]interface{}{
		"publishId": result.PublishID,
		"stage":     stage,
		"error":     err.Error(),
	})

	return result, err
}

func (s *serviceImpl) PublishToAll(ctx context.Context, pluginID int64, opts PublishOptions) ([]PublishResult, error) {
	mappings, err := s.pluginService.GetMappings(ctx, pluginID)
	if err != nil {
		return nil, err
	}

	results := make([]PublishResult, 0, len(mappings))
	for _, m := range mappings {
		result, _ := s.Publish(ctx, pluginID, m.SiteID, opts)
		results = append(results, *result)
	}

	return results, nil
}

func (s *serviceImpl) GetHistory(ctx context.Context, pluginID int64, siteID *int64) ([]PublishResult, error) {
	// TODO: Query publish history from database
	return []PublishResult{}, nil
}

func (s *serviceImpl) Rollback(ctx context.Context, pluginID, siteID, backupID int64) (*PublishResult, error) {
	// TODO: Implement rollback using backup
	return nil, apperror.New(apperror.ErrNotImplemented, "rollback not yet implemented")
}
```

---

## Implementation: packager.go

```go
package publish

import (
	"archive/zip"
	"context"
	"crypto/md5"
	"encoding/hex"
	"io"
	"os"
	"path/filepath"
	"strings"
	"time"

	"wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) CreatePackage(ctx context.Context, pluginID int64, files []string) (*PackageInfo, error) {
	s.log.Info("Creating package", "pluginId", pluginID, "files", len(files))

	// Get plugin details
	plugin, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		return nil, err
	}

	// Create temp zip file
	zipPath := filepath.Join(s.tempDir, fmt.Sprintf("plugin_%d_%d.zip", pluginID, time.Now().Unix()))
	zipFile, err := os.Create(zipPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFileWrite, "failed to create zip file")
	}
	defer zipFile.Close()

	zipWriter := zip.NewWriter(zipFile)
	defer zipWriter.Close()

	hash := md5.New()
	var totalSize int64
	var fileCount int

	// Determine which files to include
	var filesToPackage []string
	if len(files) > 0 {
		// Selected files only
		filesToPackage = files
	} else {
		// All files in plugin directory
		err = filepath.Walk(plugin.Path, func(path string, info os.FileInfo, err error) error {
			if err != nil || info.IsDir() {
				return nil
			}

			relPath, _ := filepath.Rel(plugin.Path, path)
			base := filepath.Base(path)

			// Skip hidden and excluded files
			if strings.HasPrefix(base, ".") {
				return nil
			}
			for _, exclude := range plugin.ExcludePatterns {
				if matched, _ := filepath.Match(exclude, base); matched {
					return nil
				}
			}

			filesToPackage = append(filesToPackage, relPath)
			return nil
		})
		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrDirRead, "failed to walk plugin directory")
		}
	}

	// Add files to zip
	pluginDirName := filepath.Base(plugin.Path)
	for _, relPath := range filesToPackage {
		fullPath := filepath.Join(plugin.Path, relPath)
		info, err := os.Stat(fullPath)
		if err != nil {
			continue
		}

		// Create zip entry with plugin directory prefix
		zipPath := filepath.Join(pluginDirName, relPath)
		header, err := zip.FileInfoHeader(info)
		if err != nil {
			continue
		}
		header.Name = zipPath
		header.Method = zip.Deflate

		writer, err := zipWriter.CreateHeader(header)
		if err != nil {
			continue
		}

		file, err := os.Open(fullPath)
		if err != nil {
			continue
		}

		// Write to both zip and hash
		multiWriter := io.MultiWriter(writer, hash)
		written, _ := io.Copy(multiWriter, file)
		file.Close()

		totalSize += written
		fileCount++
	}

	zipWriter.Close()
	zipFile.Close()

	// Get final zip size
	zipInfo, _ := os.Stat(zipPath)

	pkg := &PackageInfo{
		Path:      zipPath,
		Size:      zipInfo.Size(),
		FileCount: fileCount,
		Checksum:  hex.EncodeToString(hash.Sum(nil)),
		CreatedAt: time.Now(),
	}

	s.log.Info("Package created", "path", zipPath, "size", pkg.Size, "files", fileCount)
	return pkg, nil
}
```

---

## Implementation: uploader.go

```go
package publish

import (
	"context"
	"os"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) uploadPackage(ctx context.Context, client *wordpress.Client, zipPath, remoteSlug string) error {
	s.log.Info("Uploading package", "path", zipPath, "slug", remoteSlug)

	// Read zip file
	data, err := os.ReadFile(zipPath)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrFileRead, "failed to read package")
	}

	// Upload via WordPress REST API
	err = client.UploadPlugin(ctx, remoteSlug, data)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrRemoteUpload, "failed to upload to WordPress")
	}

	// Clean up temp file
	os.Remove(zipPath)

	s.log.Info("Package uploaded successfully", "slug", remoteSlug)
	return nil
}
```

---

## API Endpoints

| Method | Endpoint | Handler |
|--------|----------|---------|
| POST | `/api/publish/:pluginId/:siteId` | Publish to single site |
| POST | `/api/publish/:pluginId` | Publish to all mapped sites |
| GET | `/api/publish/history/:pluginId` | Get publish history |
| POST | `/api/publish/rollback` | Rollback to backup |

---

*See also: [33-watcher-service-impl.md](33-watcher-service-impl.md)*
