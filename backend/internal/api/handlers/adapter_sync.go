// Package handlers - Sync, Watcher, and Git service interfaces and adapters
package handlers

import (
	"context"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/git"
	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/services/watcher"
)

// SyncServiceInterface defines sync service methods
type SyncServiceInterface interface {
	CheckSync(ctx context.Context, pluginID, siteID int64) (*sync.SyncResult, error)
	CheckAllSites(ctx context.Context, pluginID int64) (*sync.BatchSyncResult, error)
	CheckAllPlugins(ctx context.Context) ([]sync.SyncResult, error)
	GetFileChanges(ctx context.Context, pluginID, siteID int64) ([]models.FileChange, error)
	PushSync(ctx context.Context, pluginID, siteID int64) (*sync.PushSyncResult, error)
}

// GitServiceInterface defines git service methods
type GitServiceInterface interface {
	Pull(ctx context.Context, pluginID int64) (*git.PullResult, error)
	PullAll(ctx context.Context) (*git.BatchPullResult, error)
	Status(ctx context.Context, pluginID int64) (*git.StatusResult, error)
	Commit(ctx context.Context, pluginID int64, message string) (*git.CommitResult, error)
	Push(ctx context.Context, pluginID int64) (*git.PushResult, error)
}

// WatcherServiceInterface defines watcher service methods
type WatcherServiceInterface interface {
	TriggerScan(ctx context.Context, pluginID int64) (*watcher.ScanResult, error)
	ScanAll(ctx context.Context) ([]watcher.ScanResult, error)
}

// SyncServiceAdapter wraps sync.Service to implement SyncServiceInterface
type SyncServiceAdapter struct {
	sync.Service
}

func (a *SyncServiceAdapter) CheckSync(ctx context.Context, pluginID, siteID int64) (*sync.SyncResult, error) {
	return a.Service.CheckSync(ctx, pluginID, siteID)
}

func (a *SyncServiceAdapter) CheckAllSites(ctx context.Context, pluginID int64) (*sync.BatchSyncResult, error) {
	return a.Service.CheckAllSites(ctx, pluginID)
}

func (a *SyncServiceAdapter) CheckAllPlugins(ctx context.Context) ([]sync.SyncResult, error) {
	return a.Service.CheckAllPlugins(ctx)
}

func (a *SyncServiceAdapter) GetFileChanges(ctx context.Context, pluginID, siteID int64) ([]models.FileChange, error) {
	return a.Service.GetFileChanges(ctx, pluginID, siteID)
}

func (a *SyncServiceAdapter) PushSync(ctx context.Context, pluginID, siteID int64) (*sync.PushSyncResult, error) {
	return a.Service.PushSync(ctx, pluginID, siteID)
}

// WatcherServiceAdapter wraps *watcher.Service to implement WatcherServiceInterface
type WatcherServiceAdapter struct {
	*watcher.Service
}

func (a *WatcherServiceAdapter) TriggerScan(ctx context.Context, pluginID int64) (*watcher.ScanResult, error) {
	return a.Service.TriggerScan(ctx, pluginID)
}

func (a *WatcherServiceAdapter) ScanAll(ctx context.Context) ([]watcher.ScanResult, error) {
	return a.Service.ScanAll(ctx)
}
