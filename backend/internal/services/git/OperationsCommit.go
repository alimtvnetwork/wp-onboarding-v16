// Package git — Commit and Push operations.
package git

import (
	"context"
	"strconv"
	"strings"

	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// commitInput bundles parameters for executeCommit.
type commitInput struct {
	Path       string
	PluginName string
	Result     *CommitResult
	Message    string
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

	return s.executeCommit(commitInput{
		Path:       p.Path,
		PluginName: p.Name,
		Result:     &result,
		Message:    message,
	})
}

// executeCommit runs git add + commit and populates the result.
func (s *Service) executeCommit(input commitInput) apperror.Result[CommitResult] {
	_, err := s.runGitCommand(input.Path, "add", "-A")
	if err != nil {
		input.Result.IsSuccess = false
		input.Result.Message = "Failed to stage changes"

		return apperror.FailWrap[CommitResult](err, apperror.ErrGitCommand, "failed to stage changes")
	}

	output, err := s.runGitCommand(input.Path, "commit", "-m", input.Message)
	if err != nil {
		input.Result.IsSuccess = false
		input.Result.Message = "Failed to commit: " + output

		return apperror.FailWrap[CommitResult](err, apperror.ErrGitCommand, "failed to commit")
	}

	hash, _ := s.runGitCommand(input.Path, "rev-parse", "--short", "HEAD")
	input.Result.CommitHash = strings.TrimSpace(hash)
	input.Result.IsSuccess = true

	ws.Broadcast(s.wsHub, ws.EventGitCommitComplete, ws.GitCommitCompleteData{
		PluginId:   input.Result.PluginId,
		IsSuccess:  true,
		CommitHash: input.Result.CommitHash,
	})

	s.log.Info("Git commit complete", "plugin", input.PluginName, "pluginId", input.Result.PluginId, "hash", input.Result.CommitHash)

	return apperror.Ok(*input.Result)
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
	branch, branchErr := s.runGitCommand(path, "rev-parse", "--abbrev-ref", "HEAD")

	if branchErr != nil {
		return apperror.FailWrap[PushResult](branchErr, apperror.ErrGitCommand, "failed to detect current branch")
	}
	branch = strings.TrimSpace(branch)

	revList, revErr := s.runGitCommand(path, "rev-list", "--count", branch+"...origin/"+branch)

	if revErr != nil {
		s.log.Debug("rev-list count failed (may not have remote tracking)", "error", revErr.Error())
	}

	pushed, pushCountErr := strconv.Atoi(strings.TrimSpace(revList))
	if pushCountErr == nil {
		result.Pushed = pushed
	}

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
