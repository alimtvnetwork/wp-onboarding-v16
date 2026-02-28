// Package git — Pull, PullAll, Status, Commit, Push operations.
package git

import (
	"context"
	"strconv"
	"strings"
	"time"

	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

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

	return s.executePull(ctx, p.Path, &result, startTime)
}

// executePull runs the actual git pull and populates the result.
func (s *Service) executePull(
	ctx context.Context,
	path string,
	result *PullResult,
	startTime time.Time,
) apperror.Result[PullResult] {
	branch, err := s.runGitCommand(path, "rev-parse", "--abbrev-ref", "HEAD")
	if err != nil {
		result.IsSuccess = false
		result.Error = err.Error()
		result.Duration = time.Since(startTime).Milliseconds()

		return apperror.FailWrap[PullResult](err, apperror.ErrGitCommand, "failed to get current branch")
	}

	result.Branch = strings.TrimSpace(branch)

	output, err := s.runGitCommand(path, "pull", "origin", result.Branch)
	result.Output = output
	result.Duration = time.Since(startTime).Milliseconds()

	if err != nil {
		result.IsSuccess = false
		result.Error = err.Error()

		ws.Broadcast(s.wsHub, ws.EventGitPullFailed, ws.GitPullFailedData{
			PluginId: result.PluginId,
			Error:    result.Error,
		})

		return apperror.FailWrap[PullResult](err, apperror.ErrGitCommand, "git pull failed")
	}

	result.IsSuccess = true
	parseGitPullOutput(output, result)

	commitHash, _ := s.runGitCommand(path, "rev-parse", "--short", "HEAD")
	result.CommitHash = strings.TrimSpace(commitHash)

	commitMsg, _ := s.runGitCommand(path, "log", "-1", "--format=%s")
	result.CommitMsg = strings.TrimSpace(commitMsg)

	ws.Broadcast(s.wsHub, ws.EventGitPullComplete, ws.GitPullCompleteData{
		PluginId:     result.PluginId,
		IsSuccess:    true,
		FilesChanged: result.FilesChanged,
		CommitHash:   result.CommitHash,
	})

	s.log.Info("Git pull complete",
		"plugin", result.PluginName,
		"pluginId", result.PluginId,
		"filesChanged", result.FilesChanged,
		"duration", result.Duration,
	)

	return apperror.Ok(*result)
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

	batch.Duration = time.Since(startTime).Milliseconds()

	ws.Broadcast(s.wsHub, ws.EventGitPullAllComplete, ws.GitPullAllCompleteData{
		Succeeded: batch.Succeeded,
		Failed:    batch.Failed,
		Duration:  batch.Duration,
	})

	return apperror.Ok(batch)
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

	branch, _ := s.runGitCommand(p.Path, "rev-parse", "--abbrev-ref", "HEAD")
	result.Branch = strings.TrimSpace(branch)

	s.runGitCommand(p.Path, "fetch", "--quiet")
	revList, _ := s.runGitCommand(p.Path, "rev-list", "--left-right", "--count", result.Branch+"...origin/"+result.Branch)
	parts := strings.Fields(revList)
	hasAheadBehind := len(parts) == 2

	if hasAheadBehind {
		result.Ahead, _ = strconv.Atoi(parts[0])
		result.Behind, _ = strconv.Atoi(parts[1])
	}

	populateFileCountsFromStatus(s, p.Path, &result)

	lastCommit, _ := s.runGitCommand(p.Path, "log", "-1", "--format=%s")
	result.LastCommit = strings.TrimSpace(lastCommit)

	return apperror.Ok(result)
}

// populateFileCountsFromStatus fills staged, modified, untracked counts.
func populateFileCountsFromStatus(s *Service, path string, result *StatusResult) {
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

// Commit stages all changes and commits with the given message
func (s *Service) Commit(ctx context.Context, pluginId int64, message string) apperror.Result[CommitResult] {
	pResult := s.pluginService.GetById(ctx, pluginId)
	if pResult.HasError() {
		return apperror.Fail[CommitResult](pResult.AppError())
	}

	p := pResult.Value()
	result := CommitResult{PluginId: pluginId}

	repoErr := requireGitRepo(p.Path)
	if repoErr != nil {
		return apperror.Fail[CommitResult](repoErr)
	}

	return s.executeCommit(p.Path, p.Name, &result, message)
}

// executeCommit runs git add + commit and populates the result.
func (s *Service) executeCommit(
	path string,
	pluginName string,
	result *CommitResult,
	message string,
) apperror.Result[CommitResult] {
	_, err := s.runGitCommand(path, "add", "-A")
	if err != nil {
		result.IsSuccess = false
		result.Message = "Failed to stage changes"

		return apperror.FailWrap[CommitResult](err, apperror.ErrGitCommand, "failed to stage changes")
	}

	output, err := s.runGitCommand(path, "commit", "-m", message)
	if err != nil {
		result.IsSuccess = false
		result.Message = "Failed to commit: " + output

		return apperror.FailWrap[CommitResult](err, apperror.ErrGitCommand, "failed to commit")
	}

	hash, _ := s.runGitCommand(path, "rev-parse", "--short", "HEAD")
	result.CommitHash = strings.TrimSpace(hash)
	result.IsSuccess = true

	ws.Broadcast(s.wsHub, ws.EventGitCommitComplete, ws.GitCommitCompleteData{
		PluginId:   result.PluginId,
		IsSuccess:  true,
		CommitHash: result.CommitHash,
	})

	s.log.Info("Git commit complete", "plugin", pluginName, "pluginId", result.PluginId, "hash", result.CommitHash)

	return apperror.Ok(*result)
}

// Push pushes commits to remote
func (s *Service) Push(ctx context.Context, pluginId int64) apperror.Result[PushResult] {
	pResult := s.pluginService.GetById(ctx, pluginId)
	if pResult.HasError() {
		return apperror.Fail[PushResult](pResult.AppError())
	}

	p := pResult.Value()
	result := PushResult{PluginId: pluginId}

	repoErr := requireGitRepo(p.Path)
	if repoErr != nil {
		return apperror.Fail[PushResult](repoErr)
	}

	return s.executePush(p.Path, p.Name, &result)
}

// executePush runs the actual git push and populates the result.
func (s *Service) executePush(path string, pluginName string, result *PushResult) apperror.Result[PushResult] {
	branch, _ := s.runGitCommand(path, "rev-parse", "--abbrev-ref", "HEAD")
	branch = strings.TrimSpace(branch)

	revList, _ := s.runGitCommand(path, "rev-list", "--count", branch+"...origin/"+branch)
	result.Pushed, _ = strconv.Atoi(strings.TrimSpace(revList))

	output, err := s.runGitCommand(path, "push", "origin", branch)
	if err != nil {
		result.IsSuccess = false
		result.Message = "Failed to push: " + output

		return apperror.FailWrap[PushResult](err, apperror.ErrGitCommand, "git push failed")
	}

	result.IsSuccess = true

	ws.Broadcast(s.wsHub, ws.EventGitPushComplete, ws.GitPushCompleteData{
		PluginId:  result.PluginId,
		IsSuccess: true,
		Pushed:    result.Pushed,
	})

	s.log.Info("Git push complete", "plugin", pluginName, "pluginId", result.PluginId, "pushed", result.Pushed)

	return apperror.Ok(*result)
}
