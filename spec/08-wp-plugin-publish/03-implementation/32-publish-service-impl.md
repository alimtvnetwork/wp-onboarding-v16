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

// StageStatusType represents the status of a pipeline stage
type StageStatusType string

const (
	StageStatusPending StageStatusType = "pending"
	StageStatusRunning StageStatusType = "running"
	StageStatusSuccess StageStatusType = "success"
	StageStatusFailed  StageStatusType = "failed"
	StageStatusSkipped StageStatusType = "skipped"
)

// ActivationStatusType represents plugin activation state after publish
type ActivationStatusType string

const (
	ActivationActive   ActivationStatusType = "active"
	ActivationInactive ActivationStatusType = "inactive"
	ActivationError    ActivationStatusType = "error"
)

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
	PublishID        string               `json:"publishId"`
	Success          bool                 `json:"success"`
	PluginID         int64                `json:"pluginId"`
	SiteID           int64                `json:"siteId"`
	FilesUploaded    int                  `json:"filesUploaded"`
	BytesTransferred int64                `json:"bytesTransferred"`
	BackupID         *int64               `json:"backupId,omitempty"`
	ActivationStatus ActivationStatusType `json:"activationStatus"`
	Duration         int64                `json:"duration"`
	Stages           []StageResult        `json:"stages"`
	Error            string               `json:"error,omitempty"`
}

// StageResult tracks individual pipeline stage outcomes
type StageResult struct {
	Name     string          `json:"name"`
	Status   StageStatusType `json:"status"`
	Duration int64           `json:"duration"`
	Error    string          `json:"error,omitempty"`
}

// PackageInfo describes a created plugin package
type PackageInfo struct {
	Path      string    `json:"path"`
	Size      int64     `json:"size"`
	FileCount int       `json:"fileCount"`
	Checksum  string    `json:"checksum"`
	CreatedAt time.Time `json:"createdAt"`
}

// --- Broadcast detail structs (broadcast_details.go) ---

// PublishStartedEvent is broadcast when a publish operation begins
type PublishStartedEvent struct {
	PublishID string `json:"publishId"`
	PluginID  int64  `json:"pluginId"`
	SiteID    int64  `json:"siteId"`
	Mode      string `json:"mode"`
}

// PublishCompleteEvent is broadcast when a publish operation completes
type PublishCompleteEvent struct {
	PublishID     string `json:"publishId"`
	PluginID      int64  `json:"pluginId"`
	SiteID        int64  `json:"siteId"`
	Success       bool   `json:"success"`
	FilesUploaded int    `json:"filesUploaded"`
}

// PublishProgressEvent is broadcast during publish stage transitions
type PublishProgressEvent struct {
	Stage    string          `json:"stage"`
	Status   StageStatusType `json:"status"`
	Duration int64           `json:"duration,omitempty"`
}

// PublishFailedEvent is broadcast when a publish operation fails
type PublishFailedEvent struct {
	PublishID string `json:"publishId"`
	Stage     string `json:"stage"`
	Error     string `json:"error"`
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

func (s *serviceImpl) Publish(
	ctx context.Context,
	pluginID int64,
	siteID int64,
	opts PublishOptions,
) (*PublishResult, error) {
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
	s.wsHub.Broadcast(ws.EventPublishStarted, PublishStartedEvent{
		PublishID: publishID,
		PluginID:  pluginID,
		SiteID:    siteID,
		Mode:      opts.Mode,
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
	if result.Stages[0].Status == StageStatusFailed {
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
		if result.Stages[len(result.Stages)-1].Status == StageStatusFailed {
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
	if result.Stages[len(result.Stages)-1].Status == StageStatusFailed {
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
	if result.Stages[len(result.Stages)-1].Status == StageStatusFailed {
		return s.failPublish(result, "upload", fmt.Errorf(result.Stages[len(result.Stages)-1].Error), startTime)
	}
	result.FilesUploaded = pkg.FileCount
	result.BytesTransferred = pkg.Size

	// Stage 5: Activate (optional)
	if opts.Activate {
		result.Stages = append(result.Stages, s.runStage("activate", func() error {
			return wpClient.ActivatePlugin(ctx, remoteSlug)
		}))
		if result.Stages[len(result.Stages)-1].Status == StageStatusFailed {
			result.ActivationStatus = ActivationError
		} else {
			result.ActivationStatus = ActivationActive
		}
	} else {
		result.ActivationStatus = ActivationInactive
	}

	// Mark files as synced
	if len(opts.Files) > 0 {
		s.syncService.MarkSynced(ctx, pluginID, siteID, opts.Files)
	}

	// Update publish timestamp
	s.db.ExecContext(ctx, `
		UPDATE PluginMappings
		SET LastSyncAt = datetime('now'), SyncStatus = ?, UpdatedAt = datetime('now')
		WHERE PluginId = ? AND SiteId = ?
	`, string(sync.SyncStatusSynced), pluginID, siteID)

	result.Success = true
	result.Duration = time.Since(startTime).Milliseconds()

	// Broadcast publish complete
	s.wsHub.Broadcast(ws.EventPublishComplete, PublishCompleteEvent{
		PublishID:     publishID,
		PluginID:      pluginID,
		SiteID:        siteID,
		Success:       true,
		FilesUploaded: result.FilesUploaded,
	})

	s.log.Info("Publish complete", "publishId", publishID, "duration", result.Duration)
	return result, nil
}

func (s *serviceImpl) runStage(name string, fn func() error) StageResult {
	start := time.Now()
	stage := StageResult{Name: name, Status: StageStatusRunning}

	s.wsHub.Broadcast(ws.EventPublishProgress, PublishProgressEvent{
		Stage:  name,
		Status: StageStatusRunning,
	})

	err := fn()
	stage.Duration = time.Since(start).Milliseconds()

	if err != nil {
		stage.Status = StageStatusFailed
		stage.Error = err.Error()
	} else {
		stage.Status = StageStatusSuccess
	}

	s.wsHub.Broadcast(ws.EventPublishProgress, PublishProgressEvent{
		Stage:    name,
		Status:   stage.Status,
		Duration: stage.Duration,
	})

	return stage
}

func (s *serviceImpl) failPublish(
	result *PublishResult,
	stage string,
	err error,
	startTime time.Time,
) (*PublishResult, error) {
	result.Success = false
	result.Error = err.Error()
	result.Duration = time.Since(startTime).Milliseconds()

	s.wsHub.Broadcast(ws.EventPublishFailed, PublishFailedEvent{
		PublishID: result.PublishID,
		Stage:     stage,
		Error:     err.Error(),
	})

	return result, err
}

func (s *serviceImpl) PublishToAll(
	ctx context.Context,
	pluginID int64,
	opts PublishOptions,
) ([]PublishResult, error) {
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

func (s *serviceImpl) GetHistory(
	ctx context.Context,
	pluginID int64,
	siteID *int64,
) ([]PublishResult, error) {
	// TODO: Query publish history from database
	return []PublishResult{}, nil
}

func (s *serviceImpl) Rollback(
	ctx context.Context,
	pluginID int64,
	siteID int64,
	backupID int64,
) (*PublishResult, error) {
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

func (s *serviceImpl) CreatePackage(
	ctx context.Context,
	pluginID int64,
	files []string,
) (*PackageInfo, error) {
	s.log.Info("Creating package", "pluginId", pluginID, "files", len(files))

	plugin, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		return nil, err
	}

	zipPath := filepath.Join(s.tempDir, fmt.Sprintf("plugin_%d_%d.zip", pluginID, time.Now().Unix()))

	filesToPackage, err := s.resolveFilesToPackage(plugin, files)
	if err != nil {
		return nil, err
	}

	return s.writeZipPackage(plugin, zipPath, filesToPackage)
}

func (s *serviceImpl) resolveFilesToPackage(plugin *models.Plugin, files []string) ([]string, error) {
	if len(files) > 0 {
		return files, nil
	}

	return s.collectPluginFiles(plugin)
}

func (s *serviceImpl) collectPluginFiles(plugin *models.Plugin) ([]string, error) {
	var filesToPackage []string

	err := filepath.Walk(plugin.Path, func(path string, info os.FileInfo, err error) error {
		return s.filterPluginFile(plugin, path, info, err, &filesToPackage)
	})

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDirRead, "failed to walk plugin directory")
	}

	return filesToPackage, nil
}

func (s *serviceImpl) filterPluginFile(
	plugin *models.Plugin,
	path string,
	info os.FileInfo,
	err error,
	filesToPackage *[]string,
) error {
	if err != nil || info.IsDir() {
		return nil
	}

	relPath, _ := filepath.Rel(plugin.Path, path)
	base := filepath.Base(path)

	if strings.HasPrefix(base, ".") {
		return nil
	}

	if s.isFileExcluded(base, plugin.ExcludePatterns) {
		return nil
	}

	*filesToPackage = append(*filesToPackage, relPath)

	return nil
}

func (s *serviceImpl) isFileExcluded(base string, excludes []string) bool {
	for _, exclude := range excludes {
		if matched, _ := filepath.Match(exclude, base); matched {
			return true
		}
	}

	return false
}

func (s *serviceImpl) writeZipPackage(plugin *models.Plugin, zipPath string, filesToPackage []string) (*PackageInfo, error) {
	zipFile, err := os.Create(zipPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFileWrite, "failed to create zip file")
	}
	defer zipFile.Close()

	zipWriter := zip.NewWriter(zipFile)
	defer zipWriter.Close()

	hash := md5.New()
	pluginDirName := filepath.Base(plugin.Path)

	stats := s.addFilesToZip(zipWriter, hash, plugin.Path, pluginDirName, filesToPackage)

	zipWriter.Close()
	zipFile.Close()

	zipInfo, _ := os.Stat(zipPath)

	pkg := &PackageInfo{
		Path:      zipPath,
		Size:      zipInfo.Size(),
		FileCount: stats.fileCount,
		Checksum:  hex.EncodeToString(hash.Sum(nil)),
		CreatedAt: time.Now(),
	}

	s.log.Info("Package created", "path", zipPath, "size", pkg.Size, "files", stats.fileCount)

	return pkg, nil
}

type zipStats struct {
	totalSize int64
	fileCount int
}

func (s *serviceImpl) addFilesToZip(
	zipWriter *zip.Writer,
	hash io.Writer,
	pluginPath string,
	pluginDirName string,
	filesToPackage []string,
) zipStats {
	var stats zipStats
	for _, relPath := range filesToPackage {
		written := s.addSingleFileToZip(zipWriter, hash, pluginPath, pluginDirName, relPath)
		if written < 0 {
			continue
		}
		stats.totalSize += written
		stats.fileCount++
	}

	return stats
}

func (s *serviceImpl) addSingleFileToZip(
	zipWriter *zip.Writer,
	hash io.Writer,
	pluginPath string,
	pluginDirName string,
	relPath string,
) int64 {
	fullPath := filepath.Join(pluginPath, relPath)
	info, err := os.Stat(fullPath)
	if err != nil {
		return -1
	}

	header, err := zip.FileInfoHeader(info)
	if err != nil {
		return -1
	}
	header.Name = filepath.Join(pluginDirName, relPath)
	header.Method = zip.Deflate

	writer, err := zipWriter.CreateHeader(header)
	if err != nil {
		return -1
	}

	file, err := os.Open(fullPath)
	if err != nil {
		return -1
	}

	multiWriter := io.MultiWriter(writer, hash)
	written, _ := io.Copy(multiWriter, file)
	file.Close()

	return written
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

func (s *serviceImpl) uploadPackage(
	ctx context.Context,
	client *wordpress.Client,
	zipPath string,
	remoteSlug string,
) error {
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
