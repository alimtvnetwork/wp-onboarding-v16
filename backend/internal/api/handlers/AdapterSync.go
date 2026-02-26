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
	RecordFileChange(ctx context.Context, change *models.FileChange) error
	MarkSynced(ctx context.Context, pluginID, siteID int64, files []string) error
	ClearChanges(ctx context.Context, pluginID int64) error
}

// SyncServiceAdapter wraps sync.Service to implement SyncServiceInterface
type SyncServiceAdapter struct {
	sync.Service
}

func (a *SyncServiceAdapter) CheckSync(ctx context.Context, pluginID, siteID int64) (*sync.SyncResult, error) {
	result := a.Service.CheckSync(ctx, pluginID, siteID)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *SyncServiceAdapter) CheckAllSites(ctx context.Context, pluginID int64) (*sync.BatchSyncResult, error) {
	result := a.Service.CheckAllSites(ctx, pluginID)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *SyncServiceAdapter) CheckAllPlugins(ctx context.Context) ([]sync.SyncResult, error) {
	result := a.Service.CheckAllPlugins(ctx)
	if result.HasError() {
		return nil, result.AppError()
	}
	return result.Items(), nil
}

func (a *SyncServiceAdapter) GetFileChanges(ctx context.Context, pluginID, siteID int64) ([]models.FileChange, error) {
	result := a.Service.GetFileChanges(ctx, pluginID, siteID)
	if result.HasError() {
		return nil, result.AppError()
	}
	return result.Items(), nil
}

func (a *SyncServiceAdapter) PushSync(ctx context.Context, pluginID, siteID int64) (*sync.PushSyncResult, error) {
	result := a.Service.PushSync(ctx, pluginID, siteID)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

// WatcherServiceInterface defines watcher service methods for HTTP handlers.
// Returns (T, error) tuples — the adapter unwraps Result types from the service layer.
type WatcherServiceInterface interface {
	TriggerScan(ctx context.Context, pluginID int64) (*watcher.ScanResult, error)
	ScanAll(ctx context.Context) ([]watcher.ScanResult, error)
}

// WatcherServiceAdapter wraps *watcher.Service to implement WatcherServiceInterface
type WatcherServiceAdapter struct {
	*watcher.Service
}

func (a *WatcherServiceAdapter) TriggerScan(ctx context.Context, pluginID int64) (*watcher.ScanResult, error) {
	result := a.Service.TriggerScan(ctx, pluginID)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *WatcherServiceAdapter) ScanAll(ctx context.Context) ([]watcher.ScanResult, error) {
	result := a.Service.ScanAll(ctx)
	if result.HasError() {
		return nil, result.AppError()
	}
	return result.Items(), nil
}
