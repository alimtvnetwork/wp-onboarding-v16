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
	PluginId     int64
	PluginName   string
	IsSuccess    bool
	Branch       string
	CommitHash   string `json:",omitempty"`
	CommitMsg    string `json:",omitempty"`
	FilesChanged int
	Insertions   int
	Deletions    int
	Duration     int64
	Output       string    `json:",omitempty"`
	Error        string    `json:",omitempty"`
	PulledAt     time.Time
}

// BuildResult represents the outcome of a build command
type BuildResult struct {
	PluginId   int64
	PluginName string
	IsSuccess  bool
	Command    string
	ExitCode   int
	Output     string
	Error      string    `json:",omitempty"`
	Duration   int64
	BuiltAt    time.Time
}

// BatchPullResult holds results for multiple plugins
type BatchPullResult struct {
	Results   []PullResult
	Succeeded int
	Failed    int
	Duration  int64
}

// PullAndBuildResult holds the combined outcome of a pull followed by an optional build
type PullAndBuildResult struct {
	Pull  PullResult
	Build *BuildResult `json:",omitempty"`
}

// PluginGitConfig holds git configuration for a plugin
type PluginGitConfig struct {
	PluginId     int64
	GitEnabled   bool
	Branch       string
	GitRemoteUrl string
	BuildEnabled bool
	BuildCommand string
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
	isBranchMissing := cfg.DefaultBranch == ""

	if isBranchMissing {
		cfg.DefaultBranch = "main"
	}

	isTimeoutMissing := cfg.Timeout == 0

	if isTimeoutMissing {
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
func (s *Service) Pull(ctx context.Context, pluginId int64) apperror.Result[PullResult] {
	startTime := time.Now()

	s.log.Info("Starting git pull", "pluginId", pluginId)

	// Get plugin details
	pResult := s.pluginService.GetById(ctx, pluginId)
	if pResult.HasError() {
		return apperror.Fail[PullResult](pResult.AppError())
	}
	p := pResult.Value()

	result := PullResult{
		PluginId:   pluginId,
		PluginName: p.Name,
		PulledAt:   time.Now(),
	}

	// Broadcast pull started
	ws.Broadcast(s.wsHub, ws.EventGitPullStarted, ws.GitPullStartedData{
		PluginId:   pluginId,
		PluginName: p.Name,
	})

	// Check if directory is a git repo
	gitDir, err := pathutil.Join(p.Path, ".git")
	if err != nil {
		return apperror.FailWrap[PullResult](err, apperror.ErrInternal, "failed to resolve git directory path")
	}
	if pathutil.IsDirMissing(gitDir) {
		result.IsSuccess = false
		result.Error = "not a git repository"
		result.Duration = time.Since(startTime).Milliseconds()
		return apperror.FailNew[PullResult](apperror.ErrGitNotRepo, "directory is not a git repository")
	}

	// Get current branch
	branch, err := s.runGitCommand(p.Path, "rev-parse", "--abbrev-ref", "HEAD")
	if err != nil {
		result.IsSuccess = false
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
		result.IsSuccess = false
		result.Error = err.Error()

		ws.Broadcast(s.wsHub, ws.EventGitPullFailed, ws.GitPullFailedData{
			PluginId: pluginId,
			Error:    result.Error,
		})
		return apperror.FailWrap[PullResult](err, apperror.ErrGitCommand, "git pull failed")
	}

	// Parse output for stats
	result.IsSuccess = true
	s.parseGitOutput(output, &result)

	// Get latest commit info
	commitHash, _ := s.runGitCommand(p.Path, "rev-parse", "--short", "HEAD")
	result.CommitHash = strings.TrimSpace(commitHash)

	commitMsg, _ := s.runGitCommand(p.Path, "log", "-1", "--format=%s")
	result.CommitMsg = strings.TrimSpace(commitMsg)

	// Broadcast pull complete
	ws.Broadcast(s.wsHub, ws.EventGitPullComplete, ws.GitPullCompleteData{
		PluginId:     pluginId,
		IsSuccess:    true,
		FilesChanged: result.FilesChanged,
		CommitHash:   result.CommitHash,
	})

	s.log.Info("Git pull complete",
		"plugin", p.Name,
		"pluginId", pluginId,
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
		return apperror.Fail[BatchPullResult](pluginsResult.AppError())
	}
	plugins := pluginsResult.Items()

	batch := BatchPullResult{
		Results: make([]PullResult, 0),
	}

	for _, p := range plugins {
		// Check if git directory exists
		gitDir, err := pathutil.Join(p.Path, ".git")
		isGitMissing := err != nil || !pathutil.IsDir(gitDir)
		if isGitMissing {
			continue
		}

		pullResult := s.Pull(ctx, p.Id)
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
func (s *Service) Build(ctx context.Context, pluginId int64) apperror.Result[BuildResult] {
	startTime := time.Now()

	s.log.Info("Starting build", "pluginId", pluginId)

	// Get plugin
	pResult := s.pluginService.GetById(ctx, pluginId)
	if pResult.HasError() {
		return apperror.Fail[BuildResult](pResult.AppError())
	}
	p := pResult.Value()

	// Get git config
	configResult := s.GetConfig(ctx, pluginId)
	if configResult.HasError() {
		return apperror.Fail[BuildResult](configResult.AppError())
	}
	config := configResult.Value()
	isBuildMissing := !config.BuildEnabled || config.BuildCommand == ""
	if isBuildMissing {
		return apperror.FailNew[BuildResult](apperror.ErrBuildNotConfigured, "build not configured for this plugin")
	}

	result := BuildResult{
		PluginId:   pluginId,
		PluginName: p.Name,
		Command:    config.BuildCommand,
		BuiltAt:    time.Now(),
	}

	// Broadcast build started
	ws.Broadcast(s.wsHub, ws.EventBuildStarted, ws.BuildStartedData{
		PluginId:   pluginId,
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
		result.IsSuccess = false
		result.Error = stderr.String()
		exitErr := wordpress.ExtractExitError(err)
		if exitErr != nil {
			result.ExitCode = exitErr.ExitCode()
		}

		ws.Broadcast(s.wsHub, ws.EventBuildFailed, ws.BuildFailedData{
			PluginId: pluginId,
			Error:    result.Error,
			ExitCode: result.ExitCode,
		})

		return apperror.FailWrap[BuildResult](err, apperror.ErrBuildFailed, result.Error)
	}

	result.IsSuccess = true
	result.ExitCode = 0

	ws.Broadcast(s.wsHub, ws.EventBuildComplete, ws.BuildCompleteData{
		PluginId:  pluginId,
		IsSuccess: true,
		Duration:  result.Duration,
	})

	s.log.Info("Build complete", "plugin", p.Name, "pluginId", pluginId, "duration", result.Duration)
	return apperror.Ok(result)
}

// PullAndBuild performs git pull followed by build
func (s *Service) PullAndBuild(ctx context.Context, pluginId int64) apperror.Result[PullAndBuildResult] {
	s.log.Info("Starting pull and build", "pluginId", pluginId)

	// First pull
	pullResult := s.Pull(ctx, pluginId)
	if pullResult.HasError() {
		return apperror.Fail[PullAndBuildResult](pullResult.AppError())
	}
	pull := pullResult.Value()

	combined := PullAndBuildResult{Pull: pull}

	// Only build if pull was successful and there were changes
	if pull.Success && pull.FilesChanged > 0 {
		buildResult := s.Build(ctx, pluginId)
		if buildResult.HasError() {
			return apperror.Fail[PullAndBuildResult](buildResult.AppError())
		}
		v := buildResult.Value()
		combined.Build = &v
	}

	return apperror.Ok(combined)
}

// GetConfig returns git configuration for a plugin
func (s *Service) GetConfig(ctx context.Context, pluginId int64) apperror.Result[PluginGitConfig] {
	var config PluginGitConfig
	config.PluginId = pluginId

	err := s.db.QueryRowContext(ctx, `
		SELECT GitEnabled, GitBranch, GitRemoteUrl, BuildEnabled, BuildCommand
		FROM PluginGitConfig
		WHERE PluginId = ?
	`, pluginId).Scan(&config.GitEnabled, &config.Branch, &config.GitRemoteUrl, &config.BuildEnabled, &config.BuildCommand)

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
	`, config.PluginId, config.GitEnabled, config.Branch, config.GitRemoteUrl, config.BuildEnabled, config.BuildCommand)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to update git config")
	}

	return nil
}

// StatusResult represents git status information
type StatusResult struct {
	PluginId   int64
	Branch     string
	Ahead      int
	Behind     int
	Staged     int
	Modified   int
	Untracked  int
	HasChanges bool
	LastCommit string `json:",omitempty"`
}

// Status returns git status for a plugin
func (s *Service) Status(ctx context.Context, pluginID int64) apperror.Result[StatusResult] {
	pResult := s.pluginService.GetByID(ctx, pluginID)
	if pResult.HasError() {
		return apperror.Fail[StatusResult](pResult.AppError())
	}
	p := pResult.Value()

	result := StatusResult{PluginID: pluginID}

	// Check if git repo
	gitDir, err := pathutil.Join(p.Path, ".git")
	if err != nil {
		return apperror.FailWrap[StatusResult](err, apperror.ErrInternal, "failed to resolve git directory path")
	}
	if pathutil.IsDirMissing(gitDir) {
		return apperror.FailNew[StatusResult](apperror.ErrGitNotRepo, "directory is not a git repository")
	}

	// Get branch
	branch, _ := s.runGitCommand(p.Path, "rev-parse", "--abbrev-ref", "HEAD")
	result.Branch = strings.TrimSpace(branch)

	// Get ahead/behind
	s.runGitCommand(p.Path, "fetch", "--quiet")
	revList, _ := s.runGitCommand(p.Path, "rev-list", "--left-right", "--count", result.Branch+"...origin/"+result.Branch)
	parts := strings.Fields(revList)
	hasAheadBehind := len(parts) == 2

	if hasAheadBehind {
		result.Ahead, _ = strconv.Atoi(parts[0])
		result.Behind, _ = strconv.Atoi(parts[1])
	}

	// Get staged files count
	staged, _ := s.runGitCommand(p.Path, "diff", "--cached", "--name-only")
	hasStagedFiles := staged != ""

	if hasStagedFiles {
		result.Staged = len(strings.Split(strings.TrimSpace(staged), "\n"))
	}

	// Get modified files count
	modified, _ := s.runGitCommand(p.Path, "diff", "--name-only")
	hasModifiedFiles := modified != ""

	if hasModifiedFiles {
		result.Modified = len(strings.Split(strings.TrimSpace(modified), "\n"))
	}

	// Get untracked files count
	untracked, _ := s.runGitCommand(p.Path, "ls-files", "--others", "--exclude-standard")
	hasUntrackedFiles := untracked != ""

	if hasUntrackedFiles {
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
	PluginId   int64
	IsSuccess  bool
	CommitHash string
	Message    string `json:",omitempty"`
}

// Commit stages all changes and commits with the given message
func (s *Service) Commit(ctx context.Context, pluginId int64, message string) apperror.Result[CommitResult] {
	pResult := s.pluginService.GetById(ctx, pluginId)
	if pResult.HasError() {
		return apperror.Fail[CommitResult](pResult.AppError())
	}
	p := pResult.Value()

	result := CommitResult{PluginId: pluginId}

	// Check if git repo
	gitDir, err := pathutil.Join(p.Path, ".git")
	if err != nil {
		return apperror.FailWrap[CommitResult](err, apperror.ErrInternal, "failed to resolve git directory path")
	}
	if pathutil.IsDirMissing(gitDir) {
		return apperror.FailNew[CommitResult](apperror.ErrGitNotRepo, "directory is not a git repository")
	}

	// Stage all changes
	_, err = s.runGitCommand(p.Path, "add", "-A")
	if err != nil {
		result.IsSuccess = false
		result.Message = "Failed to stage changes"

		return apperror.FailWrap[CommitResult](err, apperror.ErrGitCommand, "failed to stage changes")
	}

	// Commit
	output, err := s.runGitCommand(p.Path, "commit", "-m", message)
	if err != nil {
		result.IsSuccess = false
		result.Message = "Failed to commit: " + output
		return apperror.FailWrap[CommitResult](err, apperror.ErrGitCommand, "failed to commit")
	}

	// Get commit hash
	hash, _ := s.runGitCommand(p.Path, "rev-parse", "--short", "HEAD")
	result.CommitHash = strings.TrimSpace(hash)
	result.IsSuccess = true

	ws.Broadcast(s.wsHub, ws.EventGitCommitComplete, ws.GitCommitCompleteData{
		PluginId:   pluginId,
		IsSuccess:  true,
		CommitHash: result.CommitHash,
	})

	s.log.Info("Git commit complete", "plugin", p.Name, "pluginId", pluginId, "hash", result.CommitHash)
	return apperror.Ok(result)
}

// PushResult represents git push result
type PushResult struct {
	PluginId  int64
	IsSuccess bool
	Pushed    int
	Message   string `json:",omitempty"`
}

// Push pushes commits to remote
func (s *Service) Push(ctx context.Context, pluginId int64) apperror.Result[PushResult] {
	pResult := s.pluginService.GetById(ctx, pluginId)
	if pResult.HasError() {
		return apperror.Fail[PushResult](pResult.AppError())
	}
	p := pResult.Value()

	result := PushResult{PluginId: pluginId}

	// Check if git repo
	gitDir, err := pathutil.Join(p.Path, ".git")
	if err != nil {
		return apperror.FailWrap[PushResult](err, apperror.ErrInternal, "failed to resolve git directory path")
	}
	if pathutil.IsDirMissing(gitDir) {
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
		result.IsSuccess = false
		result.Message = "Failed to push: " + output
		return apperror.FailWrap[PushResult](err, apperror.ErrGitCommand, "git push failed")
	}

	result.IsSuccess = true

	ws.Broadcast(s.wsHub, ws.EventGitPushComplete, ws.GitPushCompleteData{
		PluginId:  pluginId,
		IsSuccess: true,
		Pushed:    result.Pushed,
	})

	s.log.Info("Git push complete", "plugin", p.Name, "pluginId", pluginId, "pushed", result.Pushed)
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
	hasFileChanges := len(matches) >= 2

	if hasFileChanges {
		result.FilesChanged, _ = strconv.Atoi(matches[1])

		hasInsertions := len(matches) >= 3

		if hasInsertions {
			result.Insertions, _ = strconv.Atoi(matches[2])
		}

		hasDeletions := len(matches) >= 4

		if hasDeletions {
			result.Deletions, _ = strconv.Atoi(matches[3])
		}
	}
}
