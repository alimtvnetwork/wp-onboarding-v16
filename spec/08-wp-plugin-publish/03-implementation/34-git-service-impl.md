# 34 — Git & Build Service Implementation

> **Location:** `spec/wp-plugin-publish/03-implementation/34-git-service-impl.md`  
> **Updated:** 2026-02-01  
> **Status:** Implementation Spec

---

## Overview

Complete Go implementation for Git operations and PowerShell build script execution. This service handles Git pull operations and custom build commands for plugins.

---

## File Structure

```
backend/internal/services/git/
├── service.go      # Main service interface and constructor
├── pull.go         # Git pull operations
├── build.go        # PowerShell/script execution
└── types.go        # Types and configuration
```

---

## Implementation: types.go

```go
package git

import "time"

// PullResult represents the outcome of a git pull operation
type PullResult struct {
	PluginID    int64     `json:"pluginId"`
	PluginName  string    `json:"pluginName"`
	Success     bool      `json:"success"`
	Branch      string    `json:"branch"`
	CommitHash  string    `json:"commitHash,omitempty"`
	CommitMsg   string    `json:"commitMsg,omitempty"`
	FilesChanged int      `json:"filesChanged"`
	Insertions  int       `json:"insertions"`
	Deletions   int       `json:"deletions"`
	Duration    int64     `json:"duration"` // milliseconds
	Output      string    `json:"output,omitempty"`
	Error       string    `json:"error,omitempty"`
	PulledAt    time.Time `json:"pulledAt"`
}

// BuildResult represents the outcome of a build command
type BuildResult struct {
	PluginID   int64     `json:"pluginId"`
	PluginName string    `json:"pluginName"`
	Success    bool      `json:"success"`
	Command    string    `json:"command"`
	ExitCode   int       `json:"exitCode"`
	Output     string    `json:"output"`
	Error      string    `json:"error,omitempty"`
	Duration   int64     `json:"duration"` // milliseconds
	BuiltAt    time.Time `json:"builtAt"`
}

// BatchPullResult holds results for multiple plugins
type BatchPullResult struct {
	Results   []PullResult `json:"results"`
	Succeeded int          `json:"succeeded"`
	Failed    int          `json:"failed"`
	Duration  int64        `json:"duration"`
}

// PluginGitConfig holds git configuration for a plugin
type PluginGitConfig struct {
	PluginID     int64  `json:"pluginId"`
	GitEnabled   bool   `json:"gitEnabled"`
	Branch       string `json:"branch"`
	BuildEnabled bool   `json:"buildEnabled"`
	BuildCommand string `json:"buildCommand"`
}

// --- Broadcast detail structs (broadcast_details.go) ---

// GitPullStartedEvent is broadcast when a git pull begins
type GitPullStartedEvent struct {
	PluginID   int64  `json:"pluginId"`
	PluginName string `json:"pluginName"`
}

// GitPullFailedEvent is broadcast when a git pull fails
type GitPullFailedEvent struct {
	PluginID int64  `json:"pluginId"`
	Error    string `json:"error"`
}

// GitPullCompleteEvent is broadcast when a git pull succeeds
type GitPullCompleteEvent struct {
	PluginID     int64  `json:"pluginId"`
	Success      bool   `json:"success"`
	FilesChanged int    `json:"filesChanged"`
	CommitHash   string `json:"commitHash"`
}

// GitPullAllCompleteEvent is broadcast when a batch pull finishes
type GitPullAllCompleteEvent struct {
	Succeeded int   `json:"succeeded"`
	Failed    int   `json:"failed"`
	Duration  int64 `json:"duration"`
}
```

---

## Implementation: service.go

```go
package git

import (
	"context"
	"sync"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/ws"
)

// Service interface for git and build operations
type Service interface {
	// Git operations
	Pull(ctx context.Context, pluginID int64) (*PullResult, error)
	PullAll(ctx context.Context) (*BatchPullResult, error)
	GetStatus(ctx context.Context, pluginID int64) (*GitStatus, error)

	// Build operations
	Build(ctx context.Context, pluginID int64) (*BuildResult, error)
	PullAndBuild(ctx context.Context, pluginID int64) (*PullResult, *BuildResult, error)
	PullAndBuildAll(ctx context.Context) ([]PullResult, []BuildResult, error)

	// Configuration
	GetConfig(ctx context.Context, pluginID int64) (*PluginGitConfig, error)
	UpdateConfig(ctx context.Context, config PluginGitConfig) error
}

// GitStatus represents current git repository status
type GitStatus struct {
	PluginID     int64  `json:"pluginId"`
	IsRepo       bool   `json:"isRepo"`
	Branch       string `json:"branch"`
	CommitHash   string `json:"commitHash"`
	CommitMsg    string `json:"commitMsg"`
	HasChanges   bool   `json:"hasChanges"`
	Ahead        int    `json:"ahead"`
	Behind       int    `json:"behind"`
}

// Config holds git service configuration
type Config struct {
	DB             *database.DB
	Logger         *logger.Logger
	PluginService  plugin.Service
	WatcherService watcher.Service  // Added for hybrid mode
	WSHub          *ws.Hub
	DefaultBranch  string
	Timeout        int // seconds
}

type serviceImpl struct {
	db             *database.DB
	log            *logger.Logger
	pluginService  plugin.Service
	watcherService watcher.Service  // Added for hybrid mode
	wsHub          *ws.Hub
	defaultBranch  string
	timeout        int
	mu             sync.Mutex
}

// New creates a new git service
func New(cfg Config) Service {
	if cfg.DefaultBranch == "" {
		cfg.DefaultBranch = "main"
	}
	if cfg.Timeout == 0 {
		cfg.Timeout = 60
	}

	return &serviceImpl{
		db:             cfg.DB,
		log:            cfg.Logger,
		pluginService:  cfg.PluginService,
		watcherService: cfg.WatcherService,
		wsHub:          cfg.WSHub,
		defaultBranch:  cfg.DefaultBranch,
		timeout:        cfg.Timeout,
	}
}
```

---

## Implementation: pull.go

```go
package git

import (
	"bytes"
	"context"
	"fmt"
	"os/exec"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"
	"time"

	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) Pull(ctx context.Context, pluginID int64) (*PullResult, error) {
	startTime := time.Now()
	s.log.Info("Starting git pull", "pluginId", pluginID)

	plugin, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		return nil, err
	}

	result := newPullResult(pluginID, plugin.Name)

	s.broadcastPullStarted(pluginID, plugin.Name)

	if err := s.executePull(ctx, plugin, result); err != nil {
		result.Duration = time.Since(startTime).Milliseconds()

		return result, err
	}

	result.Duration = time.Since(startTime).Milliseconds()
	s.handlePostPull(ctx, pluginID, result)

	return result, nil
}

func newPullResult(pluginID int64, pluginName string) *PullResult {
	return &PullResult{
		PluginID:   pluginID,
		PluginName: pluginName,
		PulledAt:   time.Now(),
	}
}

func (s *serviceImpl) executePull(ctx context.Context, plugin *Plugin, result *PullResult) error {
	gitDir := filepath.Join(plugin.Path, ".git")
	if !dirExists(gitDir) {
		result.Success = false
		result.Error = "not a git repository"

		return apperror.New(apperror.ErrGitNotRepo, "directory is not a git repository")
	}

	branch, err := s.runGitCommand(plugin.Path, "rev-parse", "--abbrev-ref", "HEAD")
	if err != nil {
		result.Success = false
		result.Error = err.Error()

		return err
	}

	result.Branch = strings.TrimSpace(branch)

	return s.runPullAndParse(plugin, result)
}

func (s *serviceImpl) runPullAndParse(plugin *Plugin, result *PullResult) error {
	output, err := s.runGitCommand(plugin.Path, "pull", "origin", result.Branch)
	result.Output = output

	if err != nil {
		result.Success = false
		result.Error = err.Error()
		s.broadcastPullFailed(result.PluginID, result.Error)

		return err
	}

	result.Success = true
	s.parseGitOutput(output, result)
	s.populateCommitInfo(plugin.Path, result)

	return nil
}

func (s *serviceImpl) populateCommitInfo(path string, result *PullResult) {
	commitHash, _ := s.runGitCommand(path, "rev-parse", "--short", "HEAD")
	result.CommitHash = strings.TrimSpace(commitHash)

	commitMsg, _ := s.runGitCommand(path, "log", "-1", "--format=%s")
	result.CommitMsg = strings.TrimSpace(commitMsg)
}

func (s *serviceImpl) handlePostPull(ctx context.Context, pluginID int64, result *PullResult) {
	s.triggerPostPullScan(ctx, pluginID, result)

	s.wsHub.Broadcast(ws.EventGitPullComplete, GitPullCompleteEvent{
		PluginID:     pluginID,
		Success:      true,
		FilesChanged: result.FilesChanged,
		CommitHash:   result.CommitHash,
	})

	s.log.Info("Git pull complete",
		"pluginId", pluginID,
		"filesChanged", result.FilesChanged,
		"duration", result.Duration,
	)
}

func (s *serviceImpl) triggerPostPullScan(ctx context.Context, pluginID int64, result *PullResult) {
	hasChanges := result.FilesChanged > 0 && s.watcherService != nil
	if !hasChanges {
		return
	}

	s.log.Info("Git pull detected changes, triggering file scan", "pluginId", pluginID)
	scanResult, _ := s.watcherService.ScanAfterGitPull(ctx, pluginID)

	hasScanChanges := scanResult != nil && len(scanResult.Changes) > 0
	if hasScanChanges {
		s.log.Info("File scan complete", "changes", len(scanResult.Changes))
	}
}

func (s *serviceImpl) broadcastPullStarted(pluginID int64, pluginName string) {
	s.wsHub.Broadcast(ws.EventGitPullStarted, GitPullStartedEvent{
		PluginID:   pluginID,
		PluginName: pluginName,
	})
}

func (s *serviceImpl) broadcastPullFailed(pluginID int64, errMsg string) {
	s.wsHub.Broadcast(ws.EventGitPullFailed, GitPullFailedEvent{
		PluginID: pluginID,
		Error:    errMsg,
	})
}

func (s *serviceImpl) PullAll(ctx context.Context) (*BatchPullResult, error) {
	startTime := time.Now()
	s.log.Info("Starting git pull for all plugins")

	plugins, err := s.pluginService.List(ctx)
	if err != nil {
		return nil, err
	}

	batch := s.pullEachPlugin(ctx, plugins)
	batch.Duration = time.Since(startTime).Milliseconds()

	s.broadcastPullAllComplete(batch)

	return batch, nil
}

func (s *serviceImpl) pullEachPlugin(ctx context.Context, plugins []Plugin) *BatchPullResult {
	batch := &BatchPullResult{
		Results: make([]PullResult, 0),
	}

	for _, p := range plugins {
		gitDir := filepath.Join(p.Path, ".git")
		if !dirExists(gitDir) {
			continue
		}

		s.appendPullResult(ctx, p.ID, batch)
	}

	return batch
}

func (s *serviceImpl) appendPullResult(ctx context.Context, pluginID int64, batch *BatchPullResult) {
	result, _ := s.Pull(ctx, pluginID)
	if result == nil {
		return
	}

	batch.Results = append(batch.Results, *result)

	if result.Success {
		batch.Succeeded++
	} else {
		batch.Failed++
	}
}

func (s *serviceImpl) broadcastPullAllComplete(batch *BatchPullResult) {
	s.wsHub.Broadcast(ws.EventGitPullAllComplete, GitPullAllCompleteEvent{
		Succeeded: batch.Succeeded,
		Failed:    batch.Failed,
		Duration:  batch.Duration,
	})
}

func (s *serviceImpl) GetStatus(ctx context.Context, pluginID int64) (*GitStatus, error) {
	plugin, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		return nil, err
	}

	status := &GitStatus{
		PluginID: pluginID,
	}

	gitDir := filepath.Join(plugin.Path, ".git")
	if !dirExists(gitDir) {
		status.IsRepo = false
		return status, nil
	}
	status.IsRepo = true

	// Get branch
	branch, _ := s.runGitCommand(plugin.Path, "rev-parse", "--abbrev-ref", "HEAD")
	status.Branch = strings.TrimSpace(branch)

	// Get commit hash
	hash, _ := s.runGitCommand(plugin.Path, "rev-parse", "--short", "HEAD")
	status.CommitHash = strings.TrimSpace(hash)

	// Get commit message
	msg, _ := s.runGitCommand(plugin.Path, "log", "-1", "--format=%s")
	status.CommitMsg = strings.TrimSpace(msg)

	// Check for local changes
	diffOutput, _ := s.runGitCommand(plugin.Path, "status", "--porcelain")
	status.HasChanges = len(strings.TrimSpace(diffOutput)) > 0

	// Check ahead/behind
	s.runGitCommand(plugin.Path, "fetch", "origin", status.Branch)
	aheadBehind, _ := s.runGitCommand(plugin.Path, "rev-list", "--left-right", "--count",
		fmt.Sprintf("%s...origin/%s", status.Branch, status.Branch))
	parts := strings.Fields(aheadBehind)
	if len(parts) >= 2 {
		status.Ahead, _ = strconv.Atoi(parts[0])
		status.Behind, _ = strconv.Atoi(parts[1])
	}

	return status, nil
}

// runGitCommand executes a git command in the specified directory
func (s *serviceImpl) runGitCommand(dir string, args ...string) (string, error) {
	ctx, cancel := context.WithTimeout(context.Background(), time.Duration(s.timeout)*time.Second)
	defer cancel()

	cmd := exec.CommandContext(ctx, "git", args...)
	cmd.Dir = dir

	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	err := cmd.Run()
	if err != nil {
		return stderr.String(), apperror.Wrap(err, apperror.ErrGitCommand, stderr.String())
	}

	return stdout.String(), nil
}

// parseGitOutput extracts statistics from git pull output
func (s *serviceImpl) parseGitOutput(output string, result *PullResult) {
	// Parse "X files changed, Y insertions(+), Z deletions(-)"
	re := regexp.MustCompile(`(\d+) files? changed(?:, (\d+) insertions?\(\+\))?(?:, (\d+) deletions?\(-\))?`)
	matches := re.FindStringSubmatch(output)
	if len(matches) >= 2 {
		result.FilesChanged, _ = strconv.Atoi(matches[1])
		if len(matches) >= 3 {
			result.Insertions, _ = strconv.Atoi(matches[2])
		}
		if len(matches) >= 4 {
			result.Deletions, _ = strconv.Atoi(matches[3])
		}
	}
}

func dirExists(path string) bool {
	info, err := os.Stat(path)
	return err == nil && info.IsDir()
}
```

---

## Implementation: build.go

```go
package git

import (
	"bytes"
	"context"
	"os/exec"
	"runtime"
	"time"

	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) Build(ctx context.Context, pluginID int64) (*BuildResult, error) {
	startTime := time.Now()

	s.log.Info("Starting build", "pluginId", pluginID)

	// Get plugin and config
	plugin, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		return nil, err
	}

	config, err := s.GetConfig(ctx, pluginID)
	if err != nil || !config.BuildEnabled || config.BuildCommand == "" {
		return nil, apperror.New(apperror.ErrBuildNotConfigured, "build not configured for this plugin")
	}

	result := &BuildResult{
		PluginID:   pluginID,
		PluginName: plugin.Name,
		Command:    config.BuildCommand,
		BuiltAt:    time.Now(),
	}

	// Broadcast build started
	s.wsHub.Broadcast(ws.EventBuildStarted, map[string]interface{}{
		"pluginId":   pluginID,
		"pluginName": plugin.Name,
		"command":    config.BuildCommand,
	})

	// Execute build command
	var cmd *exec.Cmd
	if runtime.GOOS == "windows" {
		// PowerShell on Windows
		cmd = exec.CommandContext(ctx, "powershell", "-ExecutionPolicy", "Bypass", "-File", config.BuildCommand)
	} else {
		// Bash on Linux/Mac
		cmd = exec.CommandContext(ctx, "bash", "-c", config.BuildCommand)
	}

	cmd.Dir = plugin.Path

	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	err = cmd.Run()
	result.Duration = time.Since(startTime).Milliseconds()
	result.Output = stdout.String()

	if err != nil {
		result.Success = false
		result.Error = stderr.String()
		if exitErr, ok := err.(*exec.ExitError); ok {
			result.ExitCode = exitErr.ExitCode()
		}

		s.wsHub.Broadcast(ws.EventBuildFailed, map[string]interface{}{
			"pluginId": pluginID,
			"error":    result.Error,
			"exitCode": result.ExitCode,
		})

		return result, apperror.Wrap(err, apperror.ErrBuildFailed, result.Error)
	}

	result.Success = true
	result.ExitCode = 0

	s.wsHub.Broadcast(ws.EventBuildComplete, map[string]interface{}{
		"pluginId": pluginID,
		"success":  true,
		"duration": result.Duration,
	})

	s.log.Info("Build complete", "pluginId", pluginID, "duration", result.Duration)
	return result, nil
}

func (s *serviceImpl) PullAndBuild(ctx context.Context, pluginID int64) (*PullResult, *BuildResult, error) {
	s.log.Info("Starting pull and build", "pluginId", pluginID)

	// First pull
	pullResult, err := s.Pull(ctx, pluginID)
	if err != nil {
		return pullResult, nil, err
	}

	// Only build if pull was successful and there were changes
	if pullResult.Success && pullResult.FilesChanged > 0 {
		buildResult, err := s.Build(ctx, pluginID)
		return pullResult, buildResult, err
	}

	return pullResult, nil, nil
}

func (s *serviceImpl) PullAndBuildAll(ctx context.Context) ([]PullResult, []BuildResult, error) {
	s.log.Info("Starting pull and build for all plugins")

	plugins, err := s.pluginService.List(ctx)
	if err != nil {
		return nil, nil, err
	}

	var pullResults []PullResult
	var buildResults []BuildResult

	for _, p := range plugins {
		pullResult, buildResult, _ := s.PullAndBuild(ctx, p.ID)
		if pullResult != nil {
			pullResults = append(pullResults, *pullResult)
		}
		if buildResult != nil {
			buildResults = append(buildResults, *buildResult)
		}
	}

	return pullResults, buildResults, nil
}

func (s *serviceImpl) GetConfig(ctx context.Context, pluginID int64) (*PluginGitConfig, error) {
	var config PluginGitConfig
	config.PluginID = pluginID

	err := s.db.QueryRowContext(ctx, `
		SELECT GitEnabled, GitBranch, BuildEnabled, BuildCommand
		FROM PluginGitConfig
		WHERE PluginId = ?
	`, pluginID).Scan(&config.GitEnabled, &config.Branch, &config.BuildEnabled, &config.BuildCommand)

	if err != nil {
		// Return default config
		config.GitEnabled = true
		config.Branch = s.defaultBranch
		config.BuildEnabled = false
		return &config, nil
	}

	return &config, nil
}

func (s *serviceImpl) UpdateConfig(ctx context.Context, config PluginGitConfig) error {
	_, err := s.db.ExecContext(ctx, `
		INSERT OR REPLACE INTO PluginGitConfig (PluginId, GitEnabled, GitBranch, BuildEnabled, BuildCommand, UpdatedAt)
		VALUES (?, ?, ?, ?, ?, datetime('now'))
	`, config.PluginID, config.GitEnabled, config.Branch, config.BuildEnabled, config.BuildCommand)

	return err
}
```

---

## Database Schema

```sql
CREATE TABLE IF NOT EXISTS PluginGitConfig (
    PluginId INTEGER PRIMARY KEY,
    GitEnabled INTEGER DEFAULT 1,
    GitBranch TEXT DEFAULT 'main',
    BuildEnabled INTEGER DEFAULT 0,
    BuildCommand TEXT,
    UpdatedAt TEXT NOT NULL,
    FOREIGN KEY (PluginId) REFERENCES Plugins(Id)
);
```

---

## WebSocket Events

| Event | Payload | Trigger |
|-------|---------|---------|
| `git:pull:started` | `{pluginId, pluginName}` | Pull started |
| `git:pull:complete` | `{pluginId, success, filesChanged}` | Pull finished |
| `git:pull:failed` | `{pluginId, error}` | Pull error |
| `git:pullall:complete` | `{succeeded, failed, duration}` | Batch pull done |
| `build:started` | `{pluginId, command}` | Build started |
| `build:complete` | `{pluginId, success, duration}` | Build finished |
| `build:failed` | `{pluginId, error, exitCode}` | Build error |

---

## API Endpoints

| Method | Endpoint | Handler |
|--------|----------|---------|
| POST | `/api/git/pull/:pluginId` | Pull single plugin |
| POST | `/api/git/pull-all` | Pull all plugins |
| GET | `/api/git/status/:pluginId` | Get git status |
| POST | `/api/git/build/:pluginId` | Run build command |
| POST | `/api/git/pull-build/:pluginId` | Pull then build |
| POST | `/api/git/pull-build-all` | Pull & build all |
| GET | `/api/git/config/:pluginId` | Get git config |
| PUT | `/api/git/config/:pluginId` | Update git config |

---

*See also: [35-implementation-plan.md](35-implementation-plan.md)*
