# 30 — Plugin Service Implementation

> **Location:** `spec/wp-plugin-publish/03-implementation/30-plugin-service-impl.md`  
> **Updated:** 2026-02-01  
> **Status:** Implementation Spec

---

## Overview

Complete Go implementation for the Plugin Service. This service manages local plugin directories, including registration, file scanning, hash calculation, and site mappings.

---

## File Structure

```
backend/internal/services/plugin/
├── service.go      # Main service interface and constructor
├── crud.go         # CRUD operations (List, Get, Create, Update, Delete)
├── scanner.go      # Directory scanning and validation
├── hasher.go       # Hash calculation for change detection
├── mappings.go     # Plugin-site mapping operations
└── types.go        # Input/output types
```

---

## Implementation: types.go

```go
package plugin

import "time"

// CreateInput holds data for creating a plugin
type CreateInput struct {
	Name            string   `json:"name" validate:"required,max=255"`
	Path            string   `json:"path" validate:"required,max=4096"`
	WatchEnabled    bool     `json:"watchEnabled"`
	ExcludePatterns []string `json:"excludePatterns"`
}

// UpdateInput holds data for updating a plugin
type UpdateInput struct {
	Name            *string   `json:"name,omitempty" validate:"omitempty,max=255"`
	Path            *string   `json:"path,omitempty" validate:"omitempty,max=4096"`
	WatchEnabled    *bool     `json:"watchEnabled,omitempty"`
	ExcludePatterns *[]string `json:"excludePatterns,omitempty"`
}

// CreateMappingInput holds data for creating a plugin-site mapping
type CreateMappingInput struct {
	PluginID   int64  `json:"pluginId" validate:"required"`
	SiteID     int64  `json:"siteId" validate:"required"`
	RemoteSlug string `json:"remoteSlug" validate:"required,max=255"`
}

// ScanResult represents the result of a directory scan
type ScanResult struct {
	Path        string     `json:"path"`
	IsValid     bool       `json:"isValid"`
	PluginName  string     `json:"pluginName,omitempty"`
	Version     string     `json:"version,omitempty"`
	MainFile    string     `json:"mainFile,omitempty"`
	FileCount   int        `json:"fileCount"`
	TotalSize   int64      `json:"totalSize"`
	Files       []FileInfo `json:"files,omitempty"`
	Error       string     `json:"error,omitempty"`
}

// FileInfo holds metadata about a single file
type FileInfo struct {
	Path        string    `json:"path"`
	Size        int64     `json:"size"`
	Hash        string    `json:"hash"`
	ModifiedAt  time.Time `json:"modifiedAt"`
	IsDirectory bool      `json:"isDirectory"`
}
```

---

## Implementation: service.go

```go
package plugin

import (
	"context"
	"database/sql"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
)

// Service interface for plugin operations
type Service interface {
	// CRUD operations
	List(ctx context.Context) ([]models.Plugin, error)
	GetByID(ctx context.Context, id int64) (*models.Plugin, error)
	Create(ctx context.Context, input CreateInput) (*models.Plugin, error)
	Update(ctx context.Context, id int64, input UpdateInput) (*models.Plugin, error)
	Delete(ctx context.Context, id int64) error

	// Directory scanning
	ScanDirectory(ctx context.Context, path string) (*ScanResult, error)
	ValidatePath(ctx context.Context, path string) error
	RefreshFileCount(ctx context.Context, id int64) error

	// Mappings
	GetMappings(ctx context.Context, pluginID int64) ([]models.PluginMapping, error)
	CreateMapping(ctx context.Context, input CreateMappingInput) (*models.PluginMapping, error)
	DeleteMapping(ctx context.Context, mappingID int64) error
	GetMappingsBySite(ctx context.Context, siteID int64) ([]models.PluginMapping, error)
}

// Config holds service configuration
type Config struct {
	DB     *database.DB
	Logger *logger.Logger
}

type serviceImpl struct {
	db  *database.DB
	log *logger.Logger
}

// New creates a new plugin service instance
func New(cfg Config) Service {
	return &serviceImpl{
		db:  cfg.DB,
		log: cfg.Logger,
	}
}
```

---

## Implementation: crud.go

```go
package plugin

import (
	"context"
	"database/sql"
	"encoding/json"
	"strings"
	"time"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) List(ctx context.Context) ([]models.Plugin, error) {
	s.log.Debug("Listing all plugins")

	rows, err := s.db.QueryContext(ctx, `
		SELECT Id, Name, Path, WatchEnabled, ExcludePatterns, 
		       FileCount, LastScannedAt, CreatedAt, UpdatedAt
		FROM Plugins
		ORDER BY Name ASC
	`)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list plugins")
	}
	defer rows.Close()

	var plugins []models.Plugin
	for rows.Next() {
		var p models.Plugin
		var excludeJSON string
		var lastScannedAt sql.NullString

		err := rows.Scan(
			&p.ID, &p.Name, &p.Path, &p.WatchEnabled, &excludeJSON,
			&p.FileCount, &lastScannedAt, &p.CreatedAt, &p.UpdatedAt,
		)
		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrDatabaseScan, "failed to scan plugin row")
		}

		// Parse exclude patterns JSON
		if excludeJSON != "" {
			json.Unmarshal([]byte(excludeJSON), &p.ExcludePatterns)
		}

		// Parse last scanned timestamp
		if lastScannedAt.Valid {
			t, _ := time.Parse(time.RFC3339, lastScannedAt.String)
			p.LastScannedAt = &t
		}

		// Load mappings for each plugin
		p.Mappings, _ = s.GetMappings(ctx, p.ID)

		plugins = append(plugins, p)
	}

	return plugins, nil
}

func (s *serviceImpl) GetByID(ctx context.Context, id int64) (*models.Plugin, error) {
	s.log.Debug("Getting plugin by ID", "pluginId", id)

	var p models.Plugin
	var excludeJSON string
	var lastScannedAt sql.NullString

	err := s.db.QueryRowContext(ctx, `
		SELECT Id, Name, Path, WatchEnabled, ExcludePatterns, 
		       FileCount, LastScannedAt, CreatedAt, UpdatedAt
		FROM Plugins
		WHERE Id = ?
	`, id).Scan(
		&p.ID, &p.Name, &p.Path, &p.WatchEnabled, &excludeJSON,
		&p.FileCount, &lastScannedAt, &p.CreatedAt, &p.UpdatedAt,
	)

	if err == sql.ErrNoRows {
		return nil, apperror.New(apperror.ErrNotFound, "plugin not found").
			WithContext("pluginId", id)
	}
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get plugin")
	}

	if excludeJSON != "" {
		json.Unmarshal([]byte(excludeJSON), &p.ExcludePatterns)
	}
	if lastScannedAt.Valid {
		t, _ := time.Parse(time.RFC3339, lastScannedAt.String)
		p.LastScannedAt = &t
	}

	p.Mappings, _ = s.GetMappings(ctx, p.ID)
	return &p, nil
}

func (s *serviceImpl) Create(ctx context.Context, input CreateInput) (*models.Plugin, error) {
	s.log.Info("Creating plugin", "name", input.Name, "path", input.Path)

	if err := s.ValidatePath(ctx, input.Path); err != nil {
		return nil, err
	}

	if err := s.checkDuplicatePath(ctx, input.Path); err != nil {
		return nil, err
	}

	fileCount := s.countFiles(ctx, input.Path)
	excludeJSON, _ := json.Marshal(input.ExcludePatterns)

	id, err := s.insertPlugin(ctx, input, string(excludeJSON), fileCount)
	if err != nil {
		return nil, err
	}

	s.log.Info("Plugin created", "pluginId", id, "name", input.Name)

	return s.GetByID(ctx, id)
}

func (s *serviceImpl) checkDuplicatePath(ctx context.Context, path string) error {
	var exists int
	err := s.db.QueryRowContext(ctx,
		"SELECT 1 FROM Plugins WHERE Path = ?", path,
	).Scan(&exists)
	if err != sql.ErrNoRows {
		return apperror.New(apperror.ErrDuplicate, "plugin path already registered").
			WithContext("path", path)
	}

	return nil
}

func (s *serviceImpl) countFiles(ctx context.Context, path string) int {
	scan, _ := s.ScanDirectory(ctx, path)
	if scan != nil {
		return scan.FileCount
	}

	return 0
}

func (s *serviceImpl) insertPlugin(ctx context.Context, input CreateInput, excludeJSON string, fileCount int) (int64, error) {
	result, err := s.db.ExecContext(ctx, `
		INSERT INTO Plugins (Name, Path, WatchEnabled, ExcludePatterns, FileCount, LastScannedAt, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'), datetime('now'))
	`, input.Name, input.Path, input.WatchEnabled, excludeJSON, fileCount)

	if err != nil {
		return 0, apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to create plugin")
	}

	id, _ := result.LastInsertId()

	return id, nil
}

func (s *serviceImpl) Update(
	ctx context.Context,
	id int64,
	input UpdateInput,
) (*models.Plugin, error) {
	s.log.Info("Updating plugin", "pluginId", id)

	existing, err := s.GetByID(ctx, id)
	if err != nil {
		return nil, err
	}

	updates, args := s.buildUpdateFields(ctx, input)
	if len(updates) == 0 {
		return existing, nil
	}

	return s.executeUpdate(ctx, id, updates, args)
}

func (s *serviceImpl) buildUpdateFields(ctx context.Context, input UpdateInput) ([]string, []any) {
	var updates []string
	var args []any

	if input.Name != nil {
		updates = append(updates, "Name = ?")
		args = append(args, *input.Name)
	}
	if input.Path != nil {
		if err := s.ValidatePath(ctx, *input.Path); err == nil {
			updates = append(updates, "Path = ?")
			args = append(args, *input.Path)
		}
	}
	if input.WatchEnabled != nil {
		updates = append(updates, "WatchEnabled = ?")
		args = append(args, *input.WatchEnabled)
	}
	if input.ExcludePatterns != nil {
		excludeJSON, _ := json.Marshal(*input.ExcludePatterns)
		updates = append(updates, "ExcludePatterns = ?")
		args = append(args, string(excludeJSON))
	}

	return updates, args
}

func (s *serviceImpl) executeUpdate(ctx context.Context, id int64, updates []string, args []any) (*models.Plugin, error) {
	updates = append(updates, "UpdatedAt = datetime('now')")
	args = append(args, id)

	query := "UPDATE Plugins SET " + strings.Join(updates, ", ") + " WHERE Id = ?"
	_, err := s.db.ExecContext(ctx, query, args...)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to update plugin")
	}

	return s.GetByID(ctx, id)
}

func (s *serviceImpl) Delete(ctx context.Context, id int64) error {
	s.log.Info("Deleting plugin", "pluginId", id)

	// Verify plugin exists
	if _, err := s.GetByID(ctx, id); err != nil {
		return err
	}

	// Delete mappings first (foreign key)
	_, err := s.db.ExecContext(ctx, "DELETE FROM PluginMappings WHERE PluginId = ?", id)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to delete plugin mappings")
	}

	// Delete plugin
	_, err = s.db.ExecContext(ctx, "DELETE FROM Plugins WHERE Id = ?", id)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to delete plugin")
	}

	s.log.Info("Plugin deleted", "pluginId", id)
	return nil
}
```

---

## Implementation: scanner.go

```go
package plugin

import (
	"bufio"
	"context"
	"crypto/md5"
	"encoding/hex"
	"io"
	"os"
	"path/filepath"
	"regexp"
	"strings"

	"wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) ScanDirectory(ctx context.Context, path string) (*ScanResult, error) {
	s.log.Debug("Scanning directory", "path", path)

	scan := &ScanResult{Path: path, IsValid: false, Files: []FileInfo{}}

	if err := s.validateDirectory(path, scan); err != nil {
		return scan, err
	}

	if err := s.collectFiles(path, scan); err != nil {
		return nil, err
	}

	s.log.Info("Directory scanned", "path", path, "pluginName", scan.PluginName, "files", scan.FileCount, "size", scan.TotalSize)

	return scan, nil
}

func (s *serviceImpl) validateDirectory(path string, scan *ScanResult) error {
	info, err := os.Stat(path)
	if os.IsNotExist(err) {
		scan.Error = "directory does not exist"
		return nil
	}
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDirRead, "failed to stat directory")
	}
	if !info.IsDir() {
		scan.Error = "path is not a directory"
		return nil
	}

	mainFile, pluginName, version, err := s.findMainPluginFile(path)
	if err != nil {
		scan.Error = err.Error()
		return nil
	}

	scan.IsValid = true
	scan.MainFile = mainFile
	scan.PluginName = pluginName
	scan.Version = version

	return nil
}

func (s *serviceImpl) collectFiles(path string, scan *ScanResult) error {
	err := filepath.Walk(path, func(filePath string, info os.FileInfo, err error) error {
		return s.processWalkEntry(path, filePath, info, err, scan)
	})

	if err != nil {
		return apperror.Wrap(err, apperror.ErrDirRead, "failed to scan directory")
	}

	return nil
}

func (s *serviceImpl) processWalkEntry(basePath, filePath string, info os.FileInfo, err error, scan *ScanResult) error {
	if err != nil {
		return nil
	}

	relPath, _ := filepath.Rel(basePath, filePath)
	if relPath == "." {
		return nil
	}

	if s.shouldSkipEntry(filePath, info) {
		return filepath.SkipDir
	}

	scan.Files = append(scan.Files, s.buildFileInfo(relPath, filePath, info, scan))

	return nil
}

func (s *serviceImpl) shouldSkipEntry(filePath string, info os.FileInfo) bool {
	base := filepath.Base(filePath)
	isHiddenOrIgnored := strings.HasPrefix(base, ".") || base == "node_modules" || base == "vendor"

	return isHiddenOrIgnored && info.IsDir()
}

func (s *serviceImpl) buildFileInfo(relPath, filePath string, info os.FileInfo, scan *ScanResult) FileInfo {
	fileInfo := FileInfo{
		Path:        relPath,
		Size:        info.Size(),
		ModifiedAt:  info.ModTime(),
		IsDirectory: info.IsDir(),
	}

	if !info.IsDir() {
		fileInfo.Hash, _ = s.calculateFileHash(filePath)
		scan.TotalSize += info.Size()
		scan.FileCount++
	}

	return fileInfo
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

func (s *serviceImpl) RefreshFileCount(ctx context.Context, id int64) error {
	plugin, err := s.GetByID(ctx, id)
	if err != nil {
		return err
	}

	scan, err := s.ScanDirectory(ctx, plugin.Path)
	if err != nil {
		return err
	}

	_, err = s.db.ExecContext(ctx, `
		UPDATE Plugins 
		SET FileCount = ?, LastScannedAt = datetime('now'), UpdatedAt = datetime('now')
		WHERE Id = ?
	`, scan.FileCount, id)

	return err
}

// findMainPluginFile locates the main plugin PHP file with the plugin header
func (s *serviceImpl) findMainPluginFile(path string) (string, string, string, error) {
	entries, err := os.ReadDir(path)
	if err != nil {
		return "", "", "", err
	}

	for _, entry := range entries {
		if entry.IsDir() || !strings.HasSuffix(entry.Name(), ".php") {
			continue
		}

		name, version := s.parsePluginHeader(filepath.Join(path, entry.Name()))
		if name != "" {
			return entry.Name(), name, version, nil
		}
	}

	return "", "", "", apperror.New(apperror.ErrPathInvalid,
		"no valid WordPress plugin file found (missing Plugin Name header)")
}

func (s *serviceImpl) parsePluginHeader(filePath string) (string, string) {
	file, err := os.Open(filePath)
	if err != nil {
		return "", ""
	}
	defer file.Close()

	pluginNameRegex := regexp.MustCompile(`Plugin Name:\s*(.+)`)
	versionRegex := regexp.MustCompile(`Version:\s*(.+)`)

	scanner := bufio.NewScanner(file)
	lineCount := 0
	var pluginName, version string

	for scanner.Scan() && lineCount < 30 {
		line := scanner.Text()
		lineCount++

		pluginName = extractMatch(pluginNameRegex, line, pluginName)
		version = extractMatch(versionRegex, line, version)
	}

	return pluginName, version
}

func extractMatch(re *regexp.Regexp, line, current string) string {
	if matches := re.FindStringSubmatch(line); len(matches) > 1 {
		return strings.TrimSpace(matches[1])
	}

	return current
}

// calculateFileHash computes MD5 hash of a file
func (s *serviceImpl) calculateFileHash(path string) (string, error) {
	file, err := os.Open(path)
	if err != nil {
		return "", err
	}
	defer file.Close()

	hash := md5.New()
	if _, err := io.Copy(hash, file); err != nil {
		return "", err
	}

	return hex.EncodeToString(hash.Sum(nil)), nil
}
```

---

## Implementation: mappings.go

```go
package plugin

import (
	"context"
	"database/sql"
	"time"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) GetMappings(ctx context.Context, pluginID int64) ([]models.PluginMapping, error) {
	rows, err := s.db.QueryContext(ctx, `
		SELECT pm.Id, pm.PluginId, pm.SiteId, pm.RemoteSlug, pm.SyncStatus,
		       pm.LastSyncAt, pm.LastBackupAt, pm.CreatedAt, pm.UpdatedAt,
		       s.Name, s.Url
		FROM PluginMappings pm
		JOIN Sites s ON pm.SiteId = s.Id
		WHERE pm.PluginId = ?
		ORDER BY s.Name ASC
	`, pluginID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get mappings")
	}
	defer rows.Close()

	return s.scanMappingRows(rows, true)
}

func (s *serviceImpl) scanMappingRows(rows *sql.Rows, hasSiteInfo bool) ([]models.PluginMapping, error) {
	var mappings []models.PluginMapping
	for rows.Next() {
		m, err := s.scanSingleMapping(rows, hasSiteInfo)
		if err != nil {
			continue
		}

		mappings = append(mappings, m)
	}

	return mappings, nil
}

func (s *serviceImpl) scanSingleMapping(rows *sql.Rows, hasSiteInfo bool) (models.PluginMapping, error) {
	var m models.PluginMapping
	var lastSyncAt, lastBackupAt sql.NullString

	var err error
	if hasSiteInfo {
		err = rows.Scan(
			&m.ID, &m.PluginID, &m.SiteID, &m.RemoteSlug, &m.SyncStatus,
			&lastSyncAt, &lastBackupAt, &m.CreatedAt, &m.UpdatedAt,
			&m.SiteName, &m.SiteURL,
		)
	} else {
		var pluginName string
		err = rows.Scan(
			&m.ID, &m.PluginID, &m.SiteID, &m.RemoteSlug, &m.SyncStatus,
			&lastSyncAt, &lastBackupAt, &m.CreatedAt, &m.UpdatedAt,
			&pluginName,
		)
		_ = pluginName
	}
	if err != nil {
		return m, err
	}

	s.parseMappingDates(&m, lastSyncAt, lastBackupAt)

	return m, nil
}

func (s *serviceImpl) parseMappingDates(m *models.PluginMapping, lastSyncAt, lastBackupAt sql.NullString) {
	if lastSyncAt.Valid {
		t, _ := time.Parse(time.RFC3339, lastSyncAt.String)
		m.LastSyncAt = &t
	}
	if lastBackupAt.Valid {
		t, _ := time.Parse(time.RFC3339, lastBackupAt.String)
		m.LastBackupAt = &t
	}
}

func (s *serviceImpl) GetMappingsBySite(ctx context.Context, siteID int64) ([]models.PluginMapping, error) {
	rows, err := s.db.QueryContext(ctx, `
		SELECT pm.Id, pm.PluginId, pm.SiteId, pm.RemoteSlug, pm.SyncStatus,
		       pm.LastSyncAt, pm.LastBackupAt, pm.CreatedAt, pm.UpdatedAt,
		       p.Name as PluginName
		FROM PluginMappings pm
		JOIN Plugins p ON pm.PluginId = p.Id
		WHERE pm.SiteId = ?
		ORDER BY p.Name ASC
	`, siteID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get mappings by site")
	}
	defer rows.Close()

	return s.scanMappingRows(rows, false)
}

func (s *serviceImpl) CreateMapping(ctx context.Context, input CreateMappingInput) (*models.PluginMapping, error) {
	s.log.Info("Creating plugin mapping", "pluginId", input.PluginID, "siteId", input.SiteID)

	if err := s.checkDuplicateMapping(ctx, input.PluginID, input.SiteID); err != nil {
		return nil, err
	}

	id, err := s.insertMapping(ctx, input)
	if err != nil {
		return nil, err
	}

	return s.getMappingByID(ctx, id)
}

func (s *serviceImpl) checkDuplicateMapping(ctx context.Context, pluginID, siteID int64) error {
	var exists int
	err := s.db.QueryRowContext(ctx,
		"SELECT 1 FROM PluginMappings WHERE PluginId = ? AND SiteId = ?",
		pluginID, siteID,
	).Scan(&exists)
	if err != sql.ErrNoRows {
		return apperror.New(apperror.ErrDuplicate, "mapping already exists").
			WithContext("pluginId", pluginID).
			WithContext("siteId", siteID)
	}

	return nil
}

func (s *serviceImpl) insertMapping(ctx context.Context, input CreateMappingInput) (int64, error) {
	result, err := s.db.ExecContext(ctx, `
		INSERT INTO PluginMappings (PluginId, SiteId, RemoteSlug, SyncStatus, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, 'pending', datetime('now'), datetime('now'))
	`, input.PluginID, input.SiteID, input.RemoteSlug)

	if err != nil {
		return 0, apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to create mapping")
	}

	id, _ := result.LastInsertId()

	return id, nil
}

func (s *serviceImpl) getMappingByID(ctx context.Context, id int64) (*models.PluginMapping, error) {
	var m models.PluginMapping
	err := s.db.QueryRowContext(ctx, `
		SELECT pm.Id, pm.PluginId, pm.SiteId, pm.RemoteSlug, pm.SyncStatus,
		       pm.CreatedAt, pm.UpdatedAt, s.Name, s.Url
		FROM PluginMappings pm
		JOIN Sites s ON pm.SiteId = s.Id
		WHERE pm.Id = ?
	`, id).Scan(
		&m.ID, &m.PluginID, &m.SiteID, &m.RemoteSlug, &m.SyncStatus,
		&m.CreatedAt, &m.UpdatedAt, &m.SiteName, &m.SiteURL,
	)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get mapping")
	}

	return &m, nil
}

func (s *serviceImpl) DeleteMapping(ctx context.Context, mappingID int64) error {
	s.log.Info("Deleting plugin mapping", "mappingId", mappingID)

	result, err := s.db.ExecContext(ctx, "DELETE FROM PluginMappings WHERE Id = ?", mappingID)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to delete mapping")
	}

	rows, _ := result.RowsAffected()
	if rows == 0 {
		return apperror.New(apperror.ErrNotFound, "mapping not found")
	}

	return nil
}
```

---

## Database Schema

```sql
CREATE TABLE IF NOT EXISTS Plugins (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    Name TEXT NOT NULL,
    Path TEXT NOT NULL UNIQUE,
    WatchEnabled INTEGER DEFAULT 0,
    ExcludePatterns TEXT DEFAULT '[]',
    FileCount INTEGER DEFAULT 0,
    LastScannedAt TEXT,
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS PluginMappings (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    PluginId INTEGER NOT NULL,
    SiteId INTEGER NOT NULL,
    RemoteSlug TEXT NOT NULL,
    SyncStatus TEXT DEFAULT 'pending',
    LastSyncAt TEXT,
    LastBackupAt TEXT,
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL,
    FOREIGN KEY (PluginId) REFERENCES Plugins(Id),
    FOREIGN KEY (SiteId) REFERENCES Sites(Id),
    UNIQUE(PluginId, SiteId)
);

CREATE INDEX IF NOT EXISTS idx_plugins_path ON Plugins(Path);
CREATE INDEX IF NOT EXISTS idx_mappings_plugin ON PluginMappings(PluginId);
CREATE INDEX IF NOT EXISTS idx_mappings_site ON PluginMappings(SiteId);
```

---

## API Endpoints

| Method | Endpoint | Handler |
|--------|----------|---------|
| GET | `/api/plugins` | List all plugins |
| GET | `/api/plugins/:id` | Get plugin by ID |
| POST | `/api/plugins` | Create plugin |
| PUT | `/api/plugins/:id` | Update plugin |
| DELETE | `/api/plugins/:id` | Delete plugin |
| POST | `/api/plugins/scan` | Scan directory |
| GET | `/api/plugins/:id/mappings` | Get mappings |
| POST | `/api/plugins/:id/mappings` | Create mapping |
| DELETE | `/api/mappings/:id` | Delete mapping |

---

*See also: [31-sync-service-impl.md](31-sync-service-impl.md)*
