// Package handlers - Sync, Watcher, and Git service interfaces and adapters
package handlers

import (
	"context"

	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/services/watcher"
)

// SyncServiceInterface defines sync service methods
type SyncServiceInterface interface {
	CheckSync(ctx context.Context, pluginID, siteID int64) (any, error)
	CheckAllSites(ctx context.Context, pluginID int64) (any, error)
	CheckAllPlugins(ctx context.Context) (any, error)
	GetFileChanges(ctx context.Context, pluginID, siteID int64) (any, error)
	PushSync(ctx context.Context, pluginID, siteID int64) (any, error)
}

// GitServiceInterface defines git service methods
type GitServiceInterface interface {
	Pull(ctx context.Context, pluginID int64) (any, error)
	PullAll(ctx context.Context) (any, error)
	Status(ctx context.Context, pluginID int64) (any, error)
	Commit(ctx context.Context, pluginID int64, message string) (any, error)
	Push(ctx context.Context, pluginID int64) (any, error)
}

// WatcherServiceInterface defines watcher service methods
type WatcherServiceInterface interface {
	TriggerScan(ctx context.Context, pluginID int64) (any, error)
	ScanAll(ctx context.Context) (any, error)
}

// SyncServiceAdapter wraps sync.Service to implement SyncServiceInterface
type SyncServiceAdapter struct {
	sync.Service
}

func (a *SyncServiceAdapter) CheckSync(ctx context.Context, pluginID, siteID int64) (any, error) {
	return a.Service.CheckSync(ctx, pluginID, siteID)
}

func (a *SyncServiceAdapter) CheckAllSites(ctx context.Context, pluginID int64) (any, error) {
	return a.Service.CheckAllSites(ctx, pluginID)
}

func (a *SyncServiceAdapter) CheckAllPlugins(ctx context.Context) (any, error) {
	return a.Service.CheckAllPlugins(ctx)
}

func (a *SyncServiceAdapter) GetFileChanges(ctx context.Context, pluginID, siteID int64) (any, error) {
	return a.Service.GetFileChanges(ctx, pluginID, siteID)
}

func (a *SyncServiceAdapter) PushSync(ctx context.Context, pluginID, siteID int64) (any, error) {
	return a.Service.PushSync(ctx, pluginID, siteID)
}

// WatcherServiceAdapter wraps *watcher.Service to implement WatcherServiceInterface
type WatcherServiceAdapter struct {
	*watcher.Service
}

func (a *WatcherServiceAdapter) TriggerScan(ctx context.Context, pluginID int64) (any, error) {
	return a.Service.TriggerScan(ctx, pluginID)
}

func (a *WatcherServiceAdapter) ScanAll(ctx context.Context) (any, error) {
	return a.Service.ScanAll(ctx)
}
