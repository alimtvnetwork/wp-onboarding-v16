// Package git provides git operations and build command execution
package git

import (
	"bytes"
	"context"
	"os/exec"
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
	"wp-plugin-publish/pkg/pathutil"
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

// PullAndBuildResult holds the combined outcome of a pull followed by an optional build
type PullAndBuildResult struct {
	Pull  PullResult   `json:"pull"`
	Build *BuildResult `json:"build,omitempty"`
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
func (s *Service) Pull(ctx context.Context, pluginID int64) apperror.Result[PullResult] {
	startTime := time.Now()

	s.log.Info("Starting git pull", "pluginId", pluginID)

	// Get plugin details
	pResult := s.pluginService.GetByID(ctx, pluginID)
	if pResult.HasError() {
		return apperror.Fail[PullResult](pResult.Error())
	}
	p := pResult.Value()

	result := PullResult{
		PluginID:   pluginID,
		PluginName: p.Name,
		PulledAt:   time.Now(),
	}

	// Broadcast pull started
	ws.Broadcast(s.wsHub, ws.EventGitPullStarted, ws.GitPullStartedData{
		PluginID:   pluginID,
		PluginName: p.Name,
	})

	// Check if directory is a git repo
	gitDir, err := pathutil.Join(p.Path, ".git")
	if err != nil {
		return apperror.FailWrap[PullResult](err, apperror.ErrInternal, "failed to resolve git directory path")
	}
	if !pathutil.IsDir(gitDir) {
		result.Success = false
		result.Error = "not a git repository"
		result.Duration = time.Since(startTime).Milliseconds()
		return apperror.FailNew[PullResult](apperror.ErrGitNotRepo, "directory is not a git repository")
	}

	// Get current branch
	branch, err := s.runGitCommand(p.Path, "rev-parse", "--abbrev-ref", "HEAD")
	if err != nil {
		result.Success = false
		result.Error = err.Error()
		result.Duration = time.Since(startTime).Milliseconds()
		return apperror.FailWrap[PullResult](err, apperror.ErrGitCommand, "failed to get current branch")
	}
	result.Branch = strings.TrimSpace(branch)

	// Run git pull
	output, err := s.runGitCommand(p.Path, "pull", "origin", result.Branch)
	result.Output = output
	result.Duration = time.Since(startTime).Milliseconds()

	if err != nil {
		result.Success = false
		result.Error = err.Error()

		ws.Broadcast(s.wsHub, ws.EventGitPullFailed, ws.GitPullFailedData{
			PluginID: pluginID,
			Error:    result.Error,
		})
		return apperror.FailWrap[PullResult](err, apperror.ErrGitCommand, "git pull failed")
	}

	// Parse output for stats
	result.Success = true
	s.parseGitOutput(output, &result)

	// Get latest commit info
	commitHash, _ := s.runGitCommand(p.Path, "rev-parse", "--short", "HEAD")
	result.CommitHash = strings.TrimSpace(commitHash)

	commitMsg, _ := s.runGitCommand(p.Path, "log", "-1", "--format=%s")
	result.CommitMsg = strings.TrimSpace(commitMsg)

	// Broadcast pull complete
	ws.Broadcast(s.wsHub, ws.EventGitPullComplete, ws.GitPullCompleteData{
		PluginID:     pluginID,
		IsSuccess:    true,
		FilesChanged: result.FilesChanged,
		CommitHash:   result.CommitHash,
	})

	s.log.Info("Git pull complete",
		"plugin", p.Name,
		"pluginId", pluginID,
		"filesChanged", result.FilesChanged,
		"duration", result.Duration,
	)

	return apperror.Ok(result)
}

// PullAll performs git pull for all plugins with git enabled
func (s *Service) PullAll(ctx context.Context) apperror.Result[BatchPullResult] {
	startTime := time.Now()

	s.log.Info("Starting git pull for all plugins")

	pluginsResult := s.pluginService.List(ctx)
	if pluginsResult.HasError() {
		return apperror.Fail[BatchPullResult](pluginsResult.Error())
	}
	plugins := pluginsResult.Items()

	batch := BatchPullResult{
		Results: make([]PullResult, 0),
	}

	for _, p := range plugins {
		// Check if git directory exists
		gitDir, err := pathutil.Join(p.Path, ".git")
		if err != nil || !pathutil.IsDir(gitDir) {
			continue
		}

		pullResult := s.Pull(ctx, p.ID)
		if pullResult.IsSafe() {
			v := pullResult.Value()
			batch.Results = append(batch.Results, v)
			if v.Success {
				batch.Succeeded++
			} else {
				batch.Failed++
			}
		}
	}

	batch.Duration = time.Since(startTime).Milliseconds()

	ws.Broadcast(s.wsHub, ws.EventGitPullAllComplete, ws.GitPullAllCompleteData{
		Succeeded: batch.Succeeded,
		Failed:    batch.Failed,
		Duration:  batch.Duration,
	})

	return apperror.Ok(batch)
}

// Build executes the build command for a plugin
func (s *Service) Build(ctx context.Context, pluginID int64) apperror.Result[BuildResult] {
	startTime := time.Now()

	s.log.Info("Starting build", "pluginId", pluginID)

	// Get plugin
	pResult := s.pluginService.GetByID(ctx, pluginID)
	if pResult.HasError() {
		return apperror.Fail[BuildResult](pResult.Error())
	}
	p := pResult.Value()

	// Get git config
	configResult := s.GetConfig(ctx, pluginID)
	if configResult.HasError() {
		return apperror.Fail[BuildResult](configResult.Error())
	}
	config := configResult.Value()
	if !config.BuildEnabled || config.BuildCommand == "" {
		return apperror.FailNew[BuildResult](apperror.ErrBuildNotConfigured, "build not configured for this plugin")
	}

	result := BuildResult{
		PluginID:   pluginID,
		PluginName: p.Name,
		Command:    config.BuildCommand,
		BuiltAt:    time.Now(),
	}

	// Broadcast build started
	ws.Broadcast(s.wsHub, ws.EventBuildStarted, ws.BuildStartedData{
		PluginID:   pluginID,
		PluginName: p.Name,
		Command:    config.BuildCommand,
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

	err := cmd.Run()
	result.Duration = time.Since(startTime).Milliseconds()
	result.Output = stdout.String()

	if err != nil {
		result.Success = false
		result.Error = stderr.String()
		if exitErr, ok := err.(*exec.ExitError); ok {
			result.ExitCode = exitErr.ExitCode()
		}

		ws.Broadcast(s.wsHub, ws.EventBuildFailed, ws.BuildFailedData{
			PluginID: pluginID,
			Error:    result.Error,
			ExitCode: result.ExitCode,
		})

		return apperror.FailWrap[BuildResult](err, apperror.ErrBuildFailed, result.Error)
	}

	result.Success = true
	result.ExitCode = 0

	ws.Broadcast(s.wsHub, ws.EventBuildComplete, ws.BuildCompleteData{
		PluginID:  pluginID,
		IsSuccess: true,
		Duration:  result.Duration,
	})

	s.log.Info("Build complete", "plugin", p.Name, "pluginId", pluginID, "duration", result.Duration)
	return apperror.Ok(result)
}

// PullAndBuild performs git pull followed by build
func (s *Service) PullAndBuild(ctx context.Context, pluginID int64) apperror.Result[PullAndBuildResult] {
	s.log.Info("Starting pull and build", "pluginId", pluginID)

	// First pull
	pullResult := s.Pull(ctx, pluginID)
	if pullResult.HasError() {
		return apperror.Fail[PullAndBuildResult](pullResult.Error())
	}
	pull := pullResult.Value()

	combined := PullAndBuildResult{Pull: pull}

	// Only build if pull was successful and there were changes
	if pull.Success && pull.FilesChanged > 0 {
		buildResult := s.Build(ctx, pluginID)
		if buildResult.HasError() {
			return apperror.Fail[PullAndBuildResult](buildResult.Error())
		}
		v := buildResult.Value()
		combined.Build = &v
	}

	return apperror.Ok(combined)
}

// GetConfig returns git configuration for a plugin
func (s *Service) GetConfig(ctx context.Context, pluginID int64) apperror.Result[PluginGitConfig] {
	var config PluginGitConfig
	config.PluginID = pluginID

	err := s.db.QueryRowContext(ctx, `
		SELECT GitEnabled, GitBranch, GitRemoteUrl, BuildEnabled, BuildCommand
		FROM PluginGitConfig
		WHERE PluginId = ?
	`, pluginID).Scan(&config.GitEnabled, &config.Branch, &config.GitRemoteURL, &config.BuildEnabled, &config.BuildCommand)

	if err != nil {
		// Return default config (not an error — just absent row)
		config.GitEnabled = true
		config.Branch = s.defaultBranch
		config.BuildEnabled = false
		return apperror.Ok(config)
	}

	return apperror.Ok(config)
}

// UpdateConfig saves git configuration for a plugin
func (s *Service) UpdateConfig(ctx context.Context, config PluginGitConfig) error {
	_, err := s.db.ExecContext(ctx, `
		INSERT OR REPLACE INTO PluginGitConfig (PluginId, GitEnabled, GitBranch, GitRemoteUrl, BuildEnabled, BuildCommand, UpdatedAt)
		VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
	`, config.PluginID, config.GitEnabled, config.Branch, config.GitRemoteURL, config.BuildEnabled, config.BuildCommand)

	return err
}

// StatusResult represents git status information
type StatusResult struct {
	PluginID   int64  `json:"pluginId"`
	Branch     string `json:"branch"`
	Ahead      int    `json:"ahead"`
	Behind     int    `json:"behind"`
	Staged     int    `json:"staged"`
	Modified   int    `json:"modified"`
	Untracked  int    `json:"untracked"`
	HasChanges bool   `json:"hasChanges"`
	LastCommit string `json:"lastCommit,omitempty"`
}

// Status returns git status for a plugin
func (s *Service) Status(ctx context.Context, pluginID int64) apperror.Result[StatusResult] {
	pResult := s.pluginService.GetByID(ctx, pluginID)
	if pResult.HasError() {
		return apperror.Fail[StatusResult](pResult.Error())
	}
	p := pResult.Value()

	result := StatusResult{PluginID: pluginID}

	// Check if git repo
	gitDir, err := pathutil.Join(p.Path, ".git")
	if err != nil {
		return apperror.FailWrap[StatusResult](err, apperror.ErrInternal, "failed to resolve git directory path")
	}
	if !pathutil.IsDir(gitDir) {
		return apperror.FailNew[StatusResult](apperror.ErrGitNotRepo, "directory is not a git repository")
	}

	// Get branch
	branch, _ := s.runGitCommand(p.Path, "rev-parse", "--abbrev-ref", "HEAD")
	result.Branch = strings.TrimSpace(branch)

	// Get ahead/behind
	s.runGitCommand(p.Path, "fetch", "--quiet")
	revList, _ := s.runGitCommand(p.Path, "rev-list", "--left-right", "--count", result.Branch+"...origin/"+result.Branch)
	parts := strings.Fields(revList)
	if len(parts) == 2 {
		result.Ahead, _ = strconv.Atoi(parts[0])
		result.Behind, _ = strconv.Atoi(parts[1])
	}

	// Get staged files count
	staged, _ := s.runGitCommand(p.Path, "diff", "--cached", "--name-only")
	if staged != "" {
		result.Staged = len(strings.Split(strings.TrimSpace(staged), "\n"))
	}

	// Get modified files count
	modified, _ := s.runGitCommand(p.Path, "diff", "--name-only")
	if modified != "" {
		result.Modified = len(strings.Split(strings.TrimSpace(modified), "\n"))
	}

	// Get untracked files count
	untracked, _ := s.runGitCommand(p.Path, "ls-files", "--others", "--exclude-standard")
	if untracked != "" {
		result.Untracked = len(strings.Split(strings.TrimSpace(untracked), "\n"))
	}

	result.HasChanges = result.Staged > 0 || result.Modified > 0 || result.Untracked > 0

	// Get last commit message
	lastCommit, _ := s.runGitCommand(p.Path, "log", "-1", "--format=%s")
	result.LastCommit = strings.TrimSpace(lastCommit)

	return apperror.Ok(result)
}

// CommitResult represents git commit result
type CommitResult struct {
	PluginID   int64  `json:"pluginId"`
	Success    bool   `json:"success"`
	CommitHash string `json:"commitHash"`
	Message    string `json:"message,omitempty"`
}

// Commit stages all changes and commits with the given message
func (s *Service) Commit(ctx context.Context, pluginID int64, message string) apperror.Result[CommitResult] {
	pResult := s.pluginService.GetByID(ctx, pluginID)
	if pResult.HasError() {
		return apperror.Fail[CommitResult](pResult.Error())
	}
	p := pResult.Value()

	result := CommitResult{PluginID: pluginID}

	// Check if git repo
	gitDir, err := pathutil.Join(p.Path, ".git")
	if err != nil {
		return apperror.FailWrap[CommitResult](err, apperror.ErrInternal, "failed to resolve git directory path")
	}
	if !pathutil.IsDir(gitDir) {
		return apperror.FailNew[CommitResult](apperror.ErrGitNotRepo, "directory is not a git repository")
	}

	// Stage all changes
	if _, err := s.runGitCommand(p.Path, "add", "-A"); err != nil {
		result.Success = false
		result.Message = "Failed to stage changes"
		return apperror.FailWrap[CommitResult](err, apperror.ErrGitCommand, "failed to stage changes")
	}

	// Commit
	output, err := s.runGitCommand(p.Path, "commit", "-m", message)
	if err != nil {
		result.Success = false
		result.Message = "Failed to commit: " + output
		return apperror.FailWrap[CommitResult](err, apperror.ErrGitCommand, "failed to commit")
	}

	// Get commit hash
	hash, _ := s.runGitCommand(p.Path, "rev-parse", "--short", "HEAD")
	result.CommitHash = strings.TrimSpace(hash)
	result.Success = true

	ws.Broadcast(s.wsHub, ws.EventGitCommitComplete, ws.GitCommitCompleteData{
		PluginID:   pluginID,
		IsSuccess:  true,
		CommitHash: result.CommitHash,
	})

	s.log.Info("Git commit complete", "plugin", p.Name, "pluginId", pluginID, "hash", result.CommitHash)
	return apperror.Ok(result)
}

// PushResult represents git push result
type PushResult struct {
	PluginID int64  `json:"pluginId"`
	Success  bool   `json:"success"`
	Pushed   int    `json:"pushed"`
	Message  string `json:"message,omitempty"`
}

// Push pushes commits to remote
func (s *Service) Push(ctx context.Context, pluginID int64) apperror.Result[PushResult] {
	pResult := s.pluginService.GetByID(ctx, pluginID)
	if pResult.HasError() {
		return apperror.Fail[PushResult](pResult.Error())
	}
	p := pResult.Value()

	result := PushResult{PluginID: pluginID}

	// Check if git repo
	gitDir, err := pathutil.Join(p.Path, ".git")
	if err != nil {
		return apperror.FailWrap[PushResult](err, apperror.ErrInternal, "failed to resolve git directory path")
	}
	if !pathutil.IsDir(gitDir) {
		return apperror.FailNew[PushResult](apperror.ErrGitNotRepo, "directory is not a git repository")
	}

	// Get current branch
	branch, _ := s.runGitCommand(p.Path, "rev-parse", "--abbrev-ref", "HEAD")
	branch = strings.TrimSpace(branch)

	// Count commits to push
	revList, _ := s.runGitCommand(p.Path, "rev-list", "--count", branch+"...origin/"+branch)
	result.Pushed, _ = strconv.Atoi(strings.TrimSpace(revList))

	// Push
	output, err := s.runGitCommand(p.Path, "push", "origin", branch)
	if err != nil {
		result.Success = false
		result.Message = "Failed to push: " + output
		return apperror.FailWrap[PushResult](err, apperror.ErrGitCommand, "git push failed")
	}

	result.Success = true

	ws.Broadcast(s.wsHub, ws.EventGitPushComplete, ws.GitPushCompleteData{
		PluginID:  pluginID,
		IsSuccess: true,
		Pushed:    result.Pushed,
	})

	s.log.Info("Git push complete", "plugin", p.Name, "pluginId", pluginID, "pushed", result.Pushed)
	return apperror.Ok(result)
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
