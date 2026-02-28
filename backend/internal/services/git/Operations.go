// Package git — Pull, PullAll, and Status operations.
package git

import (
	"context"
	"strconv"
	"strings"
	"time"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// pullContext bundles parameters for executePull.
type pullContext struct {
	Path      string
	Result    *PullResult
	StartTime time.Time
}

// Pull performs a git pull for a single plugin
func (s *Service) Pull(ctx context.Context, pluginId int64) apperror.Result[PullResult] {
	startTime := time.Now()

	s.log.Info("Starting git pull", "pluginId", pluginId)

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

	ws.Broadcast(s.wsHub, ws.EventGitPullStarted, ws.GitPullStartedData{
		PluginId:   pluginId,
		PluginName: p.Name,
	})

	repoErr := requireGitRepo(p.Path)
	if repoErr != nil {
		result.IsSuccess = false
		result.Error = "not a git repository"
		result.Duration = time.Since(startTime).Milliseconds()

		return apperror.Fail[PullResult](repoErr)
	}

	return s.executePull(pullContext{
		Path:      p.Path,
		Result:    &result,
		StartTime: startTime,
	})
}

// executePull runs the actual git pull and populates the result.
func (s *Service) executePull(pc pullContext) apperror.Result[PullResult] {
	branch, err := s.runGitCommand(pc.Path, "rev-parse", "--abbrev-ref", "HEAD")
	if err != nil {
		pc.Result.IsSuccess = false
		pc.Result.Error = err.Error()
		pc.Result.Duration = time.Since(pc.StartTime).Milliseconds()

		return apperror.FailWrap[PullResult](err, apperror.ErrGitCommand, "failed to get current branch")
	}

	pc.Result.Branch = strings.TrimSpace(branch)

	output, err := s.runGitCommand(pc.Path, "pull", "origin", pc.Result.Branch)
	pc.Result.Output = output
	pc.Result.Duration = time.Since(pc.StartTime).Milliseconds()

	if err != nil {
		return s.handlePullFailure(pc.Result, err)
	}

	return s.finalizePullSuccess(pc.Path, pc.Result, output)
}

// handlePullFailure broadcasts failure and returns error result.
func (s *Service) handlePullFailure(result *PullResult, err error) apperror.Result[PullResult] {
	result.IsSuccess = false
	result.Error = err.Error()

	ws.Broadcast(s.wsHub, ws.EventGitPullFailed, ws.GitPullFailedData{
		PluginId: result.PluginId,
		Error:    result.Error,
	})

	return apperror.FailWrap[PullResult](err, apperror.ErrGitCommand, "git pull failed")
}

// finalizePullSuccess parses output, fetches commit info, and broadcasts success.
func (s *Service) finalizePullSuccess(path string, result *PullResult, output string) apperror.Result[PullResult] {
	result.IsSuccess = true
	parseGitPullOutput(output, result)
	s.populateCommitInfo(path, result)
	s.broadcastPullSuccess(result)

	s.log.Info("Git pull complete",
		"plugin", result.PluginName, "pluginId", result.PluginId,
		"filesChanged", result.FilesChanged, "duration", result.Duration,
	)

	return apperror.Ok(*result)
}

// populateCommitInfo fetches the latest commit hash and message.
func (s *Service) populateCommitInfo(path string, result *PullResult) {
	commitHash, _ := s.runGitCommand(path, "rev-parse", "--short", "HEAD")
	result.CommitHash = strings.TrimSpace(commitHash)

	commitMsg, _ := s.runGitCommand(path, "log", "-1", "--format=%s")
	result.CommitMsg = strings.TrimSpace(commitMsg)
}

// broadcastPullSuccess sends the pull complete event via WebSocket.
func (s *Service) broadcastPullSuccess(result *PullResult) {
	ws.Broadcast(s.wsHub, ws.EventGitPullComplete, ws.GitPullCompleteData{
		PluginId:     result.PluginId,
		IsSuccess:    true,
		FilesChanged: result.FilesChanged,
		CommitHash:   result.CommitHash,
	})
}

// PullAll performs git pull for all plugins with git enabled
func (s *Service) PullAll(ctx context.Context) apperror.Result[BatchPullResult] {
	startTime := time.Now()

	s.log.Info("Starting git pull for all plugins")

	pluginsResult := s.pluginService.List(ctx)
	if pluginsResult.HasError() {
		return apperror.Fail[BatchPullResult](pluginsResult.AppError())
	}

	batch := s.pullEachPlugin(ctx, pluginsResult.Items())
	batch.Duration = time.Since(startTime).Milliseconds()

	ws.Broadcast(s.wsHub, ws.EventGitPullAllComplete, ws.GitPullAllCompleteData{
		Succeeded: batch.Succeeded,
		Failed:    batch.Failed,
		Duration:  batch.Duration,
	})

	return apperror.Ok(batch)
}

// pullEachPlugin iterates over plugins and pulls those with git repos.
func (s *Service) pullEachPlugin(ctx context.Context, plugins []models.Plugin) BatchPullResult {
	batch := BatchPullResult{Results: make([]PullResult, 0)}

	for _, p := range plugins {
		gitDir, err := pathutil.Join(p.Path, ".git")
		isGitMissing := err != nil || !pathutil.IsDir(gitDir)

		if isGitMissing {
			continue
		}

		pullResult := s.Pull(ctx, p.Id)
		if pullResult.IsSafe() {
			v := pullResult.Value()
			batch.Results = append(batch.Results, v)

			if v.IsSuccess {
				batch.Succeeded++
			} else {
				batch.Failed++
			}
		}
	}

	return batch
}

// Status returns git status for a plugin
func (s *Service) Status(ctx context.Context, pluginId int64) apperror.Result[StatusResult] {
	pResult := s.pluginService.GetById(ctx, pluginId)
	if pResult.HasError() {
		return apperror.Fail[StatusResult](pResult.AppError())
	}

	p := pResult.Value()
	result := StatusResult{PluginId: pluginId}

	repoErr := requireGitRepo(p.Path)
	if repoErr != nil {
		return apperror.Fail[StatusResult](repoErr)
	}

	return s.collectStatus(p.Path, &result)
}

// collectStatus gathers branch, ahead/behind, and file counts.
func (s *Service) collectStatus(path string, result *StatusResult) apperror.Result[StatusResult] {
	branch, _ := s.runGitCommand(path, "rev-parse", "--abbrev-ref", "HEAD")
	result.Branch = strings.TrimSpace(branch)

	s.runGitCommand(path, "fetch", "--quiet")
	s.populateAheadBehind(path, result)
	s.populateFileCountsFromStatus(path, result)

	lastCommit, _ := s.runGitCommand(path, "log", "-1", "--format=%s")
	result.LastCommit = strings.TrimSpace(lastCommit)

	return apperror.Ok(*result)
}

// populateAheadBehind fills ahead/behind counts from rev-list.
func (s *Service) populateAheadBehind(path string, result *StatusResult) {
	revList, _ := s.runGitCommand(path, "rev-list", "--left-right", "--count", result.Branch+"...origin/"+result.Branch)
	parts := strings.Fields(revList)
	hasAheadBehind := len(parts) == 2

	if hasAheadBehind {
		result.Ahead, _ = strconv.Atoi(parts[0])
		result.Behind, _ = strconv.Atoi(parts[1])
	}
}

// populateFileCountsFromStatus fills staged, modified, untracked counts.
func (s *Service) populateFileCountsFromStatus(path string, result *StatusResult) {
	staged, _ := s.runGitCommand(path, "diff", "--cached", "--name-only")
	hasStagedFiles := staged != ""

	if hasStagedFiles {
		result.Staged = len(strings.Split(strings.TrimSpace(staged), "\n"))
	}

	modified, _ := s.runGitCommand(path, "diff", "--name-only")
	hasModifiedFiles := modified != ""

	if hasModifiedFiles {
		result.Modified = len(strings.Split(strings.TrimSpace(modified), "\n"))
	}

	untracked, _ := s.runGitCommand(path, "ls-files", "--others", "--exclude-standard")
	hasUntrackedFiles := untracked != ""

	if hasUntrackedFiles {
		result.Untracked = len(strings.Split(strings.TrimSpace(untracked), "\n"))
	}

	result.HasChanges = result.Staged > 0 || result.Modified > 0 || result.Untracked > 0
}
