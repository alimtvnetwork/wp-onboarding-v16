// Package handlers - Git service interface and adapter
package handlers

import (
	"context"

	"wp-plugin-publish/internal/services/git"
)

// GitServiceInterface defines git service methods for HTTP handlers.
// Returns (T, error) tuples — the adapter unwraps Result types from the service layer.
type GitServiceInterface interface {
	Pull(ctx context.Context, pluginID int64) (*git.PullResult, error)
	PullAll(ctx context.Context) (*git.BatchPullResult, error)
	Build(ctx context.Context, pluginID int64) (*git.BuildResult, error)
	PullAndBuild(ctx context.Context, pluginID int64) (*git.PullAndBuildResult, error)
	GetConfig(ctx context.Context, pluginID int64) (*git.PluginGitConfig, error)
	UpdateConfig(ctx context.Context, config git.PluginGitConfig) error
	Status(ctx context.Context, pluginID int64) (*git.StatusResult, error)
	Commit(ctx context.Context, pluginID int64, message string) (*git.CommitResult, error)
	Push(ctx context.Context, pluginID int64) (*git.PushResult, error)
}

// GitServiceAdapter wraps *git.Service to implement GitServiceInterface
type GitServiceAdapter struct {
	*git.Service
}

func (a *GitServiceAdapter) Pull(ctx context.Context, pluginID int64) (*git.PullResult, error) {
	result := a.Service.Pull(ctx, pluginID)
	if result.HasError() {
		return nil, result.Error()
	}
	v := result.Value()
	return &v, nil
}

func (a *GitServiceAdapter) PullAll(ctx context.Context) (*git.BatchPullResult, error) {
	result := a.Service.PullAll(ctx)
	if result.HasError() {
		return nil, result.Error()
	}
	v := result.Value()
	return &v, nil
}

func (a *GitServiceAdapter) Build(ctx context.Context, pluginID int64) (*git.BuildResult, error) {
	result := a.Service.Build(ctx, pluginID)
	if result.HasError() {
		return nil, result.Error()
	}
	v := result.Value()
	return &v, nil
}

func (a *GitServiceAdapter) PullAndBuild(ctx context.Context, pluginID int64) (*git.PullAndBuildResult, error) {
	result := a.Service.PullAndBuild(ctx, pluginID)
	if result.HasError() {
		return nil, result.Error()
	}
	v := result.Value()
	return &v, nil
}

func (a *GitServiceAdapter) GetConfig(ctx context.Context, pluginID int64) (*git.PluginGitConfig, error) {
	result := a.Service.GetConfig(ctx, pluginID)
	if result.HasError() {
		return nil, result.Error()
	}
	v := result.Value()
	return &v, nil
}

func (a *GitServiceAdapter) Status(ctx context.Context, pluginID int64) (*git.StatusResult, error) {
	result := a.Service.Status(ctx, pluginID)
	if result.HasError() {
		return nil, result.Error()
	}
	v := result.Value()
	return &v, nil
}

func (a *GitServiceAdapter) Commit(ctx context.Context, pluginID int64, message string) (*git.CommitResult, error) {
	result := a.Service.Commit(ctx, pluginID, message)
	if result.HasError() {
		return nil, result.Error()
	}
	v := result.Value()
	return &v, nil
}

func (a *GitServiceAdapter) Push(ctx context.Context, pluginID int64) (*git.PushResult, error) {
	result := a.Service.Push(ctx, pluginID)
	if result.HasError() {
		return nil, result.Error()
	}
	v := result.Value()
	return &v, nil
}
