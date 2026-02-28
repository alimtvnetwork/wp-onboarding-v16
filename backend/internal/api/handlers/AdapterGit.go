// Package handlers - Git service interface and adapter
package handlers

import (
	"context"

	"wp-plugin-publish/internal/services/git"
	"wp-plugin-publish/pkg/apperror"
)

// GitServiceInterface defines git service methods for HTTP handlers.
type GitServiceInterface interface {
	Pull(ctx context.Context, pluginID int64) (*git.PullResult, *apperror.AppError)
	PullAll(ctx context.Context) (*git.BatchPullResult, *apperror.AppError)
	Build(ctx context.Context, pluginID int64) (*git.BuildResult, *apperror.AppError)
	PullAndBuild(ctx context.Context, pluginID int64) (*git.PullAndBuildResult, *apperror.AppError)
	GetConfig(ctx context.Context, pluginID int64) (*git.PluginGitConfig, *apperror.AppError)
	UpdateConfig(ctx context.Context, config git.PluginGitConfig) *apperror.AppError
	Status(ctx context.Context, pluginID int64) (*git.StatusResult, *apperror.AppError)
	Commit(ctx context.Context, pluginID int64, message string) (*git.CommitResult, *apperror.AppError)
	Push(ctx context.Context, pluginID int64) (*git.PushResult, *apperror.AppError)
}

// GitServiceAdapter wraps *git.Service to implement GitServiceInterface
type GitServiceAdapter struct {
	*git.Service
}

func (a *GitServiceAdapter) Pull(ctx context.Context, pluginID int64) (*git.PullResult, *apperror.AppError) {
	result := a.Service.Pull(ctx, pluginID)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *GitServiceAdapter) PullAll(ctx context.Context) (*git.BatchPullResult, *apperror.AppError) {
	result := a.Service.PullAll(ctx)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *GitServiceAdapter) Build(ctx context.Context, pluginID int64) (*git.BuildResult, *apperror.AppError) {
	result := a.Service.Build(ctx, pluginID)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *GitServiceAdapter) PullAndBuild(ctx context.Context, pluginID int64) (*git.PullAndBuildResult, *apperror.AppError) {
	result := a.Service.PullAndBuild(ctx, pluginID)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *GitServiceAdapter) GetConfig(ctx context.Context, pluginID int64) (*git.PluginGitConfig, *apperror.AppError) {
	result := a.Service.GetConfig(ctx, pluginID)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *GitServiceAdapter) UpdateConfig(ctx context.Context, config git.PluginGitConfig) *apperror.AppError {
	return a.Service.UpdateConfig(ctx, config)
}

func (a *GitServiceAdapter) Status(ctx context.Context, pluginID int64) (*git.StatusResult, *apperror.AppError) {
	result := a.Service.Status(ctx, pluginID)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *GitServiceAdapter) Commit(ctx context.Context, pluginID int64, message string) (*git.CommitResult, *apperror.AppError) {
	result := a.Service.Commit(ctx, pluginID, message)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *GitServiceAdapter) Push(ctx context.Context, pluginID int64) (*git.PushResult, *apperror.AppError) {
	result := a.Service.Push(ctx, pluginID)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}
