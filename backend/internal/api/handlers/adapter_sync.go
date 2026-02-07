// Package handlers - Sync, Watcher, and Git service interfaces and adapters
package handlers

import (
	"context"

	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/services/watcher"
)

// SyncServiceInterface defines sync service methods
type SyncServiceInterface interface {
	CheckSync(ctx context.Context, pluginID, siteID int64) (interface{}, error)
	CheckAllSites(ctx context.Context, pluginID int64) (interface{}, error)
	CheckAllPlugins(ctx context.Context) (interface{}, error)
	GetFileChanges(ctx context.Context, pluginID, siteID int64) (interface{}, error)
	PushSync(ctx context.Context, pluginID, siteID int64) (interface{}, error)
}

// GitServiceInterface defines git service methods
type GitServiceInterface interface {
	Pull(ctx context.Context, pluginID int64) (interface{}, error)
	PullAll(ctx context.Context) (interface{}, error)
	Status(ctx context.Context, pluginID int64) (interface{}, error)
	Commit(ctx context.Context, pluginID int64, message string) (interface{}, error)
	Push(ctx context.Context, pluginID int64) (interface{}, error)
}

// WatcherServiceInterface defines watcher service methods
type WatcherServiceInterface interface {
	TriggerScan(ctx context.Context, pluginID int64) (interface{}, error)
	ScanAll(ctx context.Context) (interface{}, error)
}

// SyncServiceAdapter wraps sync.Service to implement SyncServiceInterface
type SyncServiceAdapter struct {
	sync.Service
}

func (a *SyncServiceAdapter) CheckSync(ctx context.Context, pluginID, siteID int64) (interface{}, error) {
	return a.Service.CheckSync(ctx, pluginID, siteID)
}

func (a *SyncServiceAdapter) CheckAllSites(ctx context.Context, pluginID int64) (interface{}, error) {
	return a.Service.CheckAllSites(ctx, pluginID)
}

func (a *SyncServiceAdapter) CheckAllPlugins(ctx context.Context) (interface{}, error) {
	return a.Service.CheckAllPlugins(ctx)
}

func (a *SyncServiceAdapter) GetFileChanges(ctx context.Context, pluginID, siteID int64) (interface{}, error) {
	return a.Service.GetFileChanges(ctx, pluginID, siteID)
}

func (a *SyncServiceAdapter) PushSync(ctx context.Context, pluginID, siteID int64) (interface{}, error) {
	return a.Service.PushSync(ctx, pluginID, siteID)
}

// WatcherServiceAdapter wraps *watcher.Service to implement WatcherServiceInterface
type WatcherServiceAdapter struct {
	*watcher.Service
}

func (a *WatcherServiceAdapter) TriggerScan(ctx context.Context, pluginID int64) (interface{}, error) {
	return a.Service.TriggerScan(ctx, pluginID)
}

func (a *WatcherServiceAdapter) ScanAll(ctx context.Context) (interface{}, error) {
	return a.Service.ScanAll(ctx)
}
