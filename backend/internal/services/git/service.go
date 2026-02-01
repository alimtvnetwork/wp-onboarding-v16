// Package git provides git operations and build command execution
package git

import (
	"bytes"
	"context"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"runtime"
	"strconv"
	"strings"
	"sync"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// PullResult represents the outcome of a git pull operation
type PullResult struct {
	PluginID     int64     `json:"pluginId"`
	PluginName   string    `json:"pluginName"`
	Success      bool      `json:"success"`
	Branch       string    `json:"branch"`
	CommitHash   string    `json:"commitHash,omitempty"`
	CommitMsg    string    `json:"commitMsg,omitempty"`
	FilesChanged int       `json:"filesChanged"`
	Insertions   int       `json:"insertions"`
	Deletions    int       `json:"deletions"`
	Duration     int64     `json:"duration"`
	Output       string    `json:"output,omitempty"`
	Error        string    `json:"error,omitempty"`
	PulledAt     time.Time `json:"pulledAt"`
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
	Duration   int64     `json:"duration"`
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
	GitRemoteURL string `json:"gitRemoteUrl"`
	BuildEnabled bool   `json:"buildEnabled"`
	BuildCommand string `json:"buildCommand"`
}

// Config holds git service configuration
type Config struct {
	DB            *database.DB
	Logger        *logger.Logger
	PluginService *plugin.Service
	WSHub         *ws.Hub
	DefaultBranch string
	Timeout       int
}

// Service provides git and build operations
type Service struct {
	db            *database.DB
	log           *logger.Logger
	pluginService *plugin.Service
	wsHub         *ws.Hub
	defaultBranch string
	timeout       int
	mu            sync.Mutex
}

// New creates a new git service
func New(cfg Config) *Service {
	if cfg.DefaultBranch == "" {
		cfg.DefaultBranch = "main"
	}
	if cfg.Timeout == 0 {
		cfg.Timeout = 60
	}

	return &Service{
		db:            cfg.DB,
		log:           cfg.Logger,
		pluginService: cfg.PluginService,
		wsHub:         cfg.WSHub,
		defaultBranch: cfg.DefaultBranch,
		timeout:       cfg.Timeout,
	}
}

// Pull performs a git pull for a single plugin
func (s *Service) Pull(ctx context.Context, pluginID int64) (*PullResult, error) {
	startTime := time.Now()

	s.log.Info("Starting git pull", "pluginId", pluginID)

	// Get plugin details
	p, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		return nil, err
	}

	result := &PullResult{
		PluginID:   pluginID,
		PluginName: p.Name,
		PulledAt:   time.Now(),
	}

	// Broadcast pull started
	s.wsHub.Broadcast(ws.EventGitPullStarted, map[string]interface{}{
		"pluginId":   pluginID,
		"pluginName": p.Name,
	})

	// Check if directory is a git repo
	gitDir := filepath.Join(p.Path, ".git")
	if !dirExists(gitDir) {
		result.Success = false
		result.Error = "not a git repository"
		result.Duration = time.Since(startTime).Milliseconds()
		return result, apperror.New(apperror.ErrGitNotRepo, "directory is not a git repository")
	}

	// Get current branch
	branch, err := s.runGitCommand(p.Path, "rev-parse", "--abbrev-ref", "HEAD")
	if err != nil {
		result.Success = false
		result.Error = err.Error()
		result.Duration = time.Since(startTime).Milliseconds()
		return result, err
	}
	result.Branch = strings.TrimSpace(branch)

	// Run git pull
	output, err := s.runGitCommand(p.Path, "pull", "origin", result.Branch)
	result.Output = output
	result.Duration = time.Since(startTime).Milliseconds()

	if err != nil {
		result.Success = false
		result.Error = err.Error()

		s.wsHub.Broadcast(ws.EventGitPullFailed, map[string]interface{}{
			"pluginId": pluginID,
			"error":    result.Error,
		})
		return result, err
	}

	// Parse output for stats
	result.Success = true
	s.parseGitOutput(output, result)

	// Get latest commit info
	commitHash, _ := s.runGitCommand(p.Path, "rev-parse", "--short", "HEAD")
	result.CommitHash = strings.TrimSpace(commitHash)

	commitMsg, _ := s.runGitCommand(p.Path, "log", "-1", "--format=%s")
	result.CommitMsg = strings.TrimSpace(commitMsg)

	// Broadcast pull complete
	s.wsHub.Broadcast(ws.EventGitPullComplete, map[string]interface{}{
		"pluginId":     pluginID,
		"success":      true,
		"filesChanged": result.FilesChanged,
		"commitHash":   result.CommitHash,
	})

	s.log.Info("Git pull complete",
		"pluginId", pluginID,
		"filesChanged", result.FilesChanged,
		"duration", result.Duration,
	)

	return result, nil
}

// PullAll performs git pull for all plugins with git enabled
func (s *Service) PullAll(ctx context.Context) (*BatchPullResult, error) {
	startTime := time.Now()

	s.log.Info("Starting git pull for all plugins")

	plugins, err := s.pluginService.List(ctx)
	if err != nil {
		return nil, err
	}

	batch := &BatchPullResult{
		Results: make([]PullResult, 0),
	}

	for _, p := range plugins {
		// Check if git directory exists
		gitDir := filepath.Join(p.Path, ".git")
		if !dirExists(gitDir) {
			continue
		}

		result, _ := s.Pull(ctx, p.ID)
		if result != nil {
			batch.Results = append(batch.Results, *result)
			if result.Success {
				batch.Succeeded++
			} else {
				batch.Failed++
			}
		}
	}

	batch.Duration = time.Since(startTime).Milliseconds()

	s.wsHub.Broadcast(ws.EventGitPullAllComplete, map[string]interface{}{
		"succeeded": batch.Succeeded,
		"failed":    batch.Failed,
		"duration":  batch.Duration,
	})

	return batch, nil
}

// Build executes the build command for a plugin
func (s *Service) Build(ctx context.Context, pluginID int64) (*BuildResult, error) {
	startTime := time.Now()

	s.log.Info("Starting build", "pluginId", pluginID)

	// Get plugin
	p, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		return nil, err
	}

	// Get git config
	config, err := s.GetConfig(ctx, pluginID)
	if err != nil || !config.BuildEnabled || config.BuildCommand == "" {
		return nil, apperror.New(apperror.ErrBuildNotConfigured, "build not configured for this plugin")
	}

	result := &BuildResult{
		PluginID:   pluginID,
		PluginName: p.Name,
		Command:    config.BuildCommand,
		BuiltAt:    time.Now(),
	}

	// Broadcast build started
	s.wsHub.Broadcast(ws.EventBuildStarted, map[string]interface{}{
		"pluginId":   pluginID,
		"pluginName": p.Name,
		"command":    config.BuildCommand,
	})

	// Execute build command
	var cmd *exec.Cmd
	if runtime.GOOS == "windows" {
		cmd = exec.CommandContext(ctx, "powershell", "-ExecutionPolicy", "Bypass", "-Command", config.BuildCommand)
	} else {
		cmd = exec.CommandContext(ctx, "bash", "-c", config.BuildCommand)
	}

	cmd.Dir = p.Path

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

// PullAndBuild performs git pull followed by build
func (s *Service) PullAndBuild(ctx context.Context, pluginID int64) (*PullResult, *BuildResult, error) {
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

// GetConfig returns git configuration for a plugin
func (s *Service) GetConfig(ctx context.Context, pluginID int64) (*PluginGitConfig, error) {
	var config PluginGitConfig
	config.PluginID = pluginID

	err := s.db.QueryRowContext(ctx, `
		SELECT GitEnabled, GitBranch, GitRemoteUrl, BuildEnabled, BuildCommand
		FROM PluginGitConfig
		WHERE PluginId = ?
	`, pluginID).Scan(&config.GitEnabled, &config.Branch, &config.GitRemoteURL, &config.BuildEnabled, &config.BuildCommand)

	if err != nil {
		// Return default config
		config.GitEnabled = true
		config.Branch = s.defaultBranch
		config.BuildEnabled = false
		return &config, nil
	}

	return &config, nil
}

// UpdateConfig saves git configuration for a plugin
func (s *Service) UpdateConfig(ctx context.Context, config PluginGitConfig) error {
	_, err := s.db.ExecContext(ctx, `
		INSERT OR REPLACE INTO PluginGitConfig (PluginId, GitEnabled, GitBranch, GitRemoteUrl, BuildEnabled, BuildCommand, UpdatedAt)
		VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
	`, config.PluginID, config.GitEnabled, config.Branch, config.GitRemoteURL, config.BuildEnabled, config.BuildCommand)

	return err
}

// runGitCommand executes a git command in the specified directory
func (s *Service) runGitCommand(dir string, args ...string) (string, error) {
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
func (s *Service) parseGitOutput(output string, result *PullResult) {
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
