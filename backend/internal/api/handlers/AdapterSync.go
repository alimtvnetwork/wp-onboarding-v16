// Package handlers - Sync, Watcher, and Git service interfaces and adapters
package handlers

import (
	"context"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/services/watcher"
	"wp-plugin-publish/pkg/apperror"
)

// SyncServiceInterface defines sync service methods
type SyncServiceInterface interface {
	CheckSync(ctx context.Context, pluginId, siteId int64) (*sync.SyncResult, *apperror.AppError)
	CheckAllSites(ctx context.Context, pluginId int64) (*sync.BatchSyncResult, *apperror.AppError)
	CheckAllPlugins(ctx context.Context) ([]sync.SyncResult, *apperror.AppError)
	GetFileChanges(ctx context.Context, pluginId, siteId int64) ([]models.FileChange, *apperror.AppError)
	PushSync(ctx context.Context, pluginId, siteId int64) (*sync.PushSyncResult, *apperror.AppError)
	RecordFileChange(ctx context.Context, change *models.FileChange) *apperror.AppError
	MarkSynced(ctx context.Context, pluginId, siteId int64, files []string) *apperror.AppError
	ClearChanges(ctx context.Context, pluginId int64) *apperror.AppError
}

// SyncServiceAdapter wraps sync.Service to implement SyncServiceInterface
type SyncServiceAdapter struct {
	sync.Service
}

func (a *SyncServiceAdapter) CheckSync(ctx context.Context, pluginId, siteId int64) (*sync.SyncResult, *apperror.AppError) {
	result := a.Service.CheckSync(ctx, pluginId, siteId)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *SyncServiceAdapter) CheckAllSites(ctx context.Context, pluginId int64) (*sync.BatchSyncResult, *apperror.AppError) {
	result := a.Service.CheckAllSites(ctx, pluginId)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *SyncServiceAdapter) CheckAllPlugins(ctx context.Context) ([]sync.SyncResult, *apperror.AppError) {
	result := a.Service.CheckAllPlugins(ctx)
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Items(), nil
}

func (a *SyncServiceAdapter) GetFileChanges(ctx context.Context, pluginId, siteId int64) ([]models.FileChange, *apperror.AppError) {
	result := a.Service.GetFileChanges(ctx, pluginId, siteId)
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Items(), nil
}

func (a *SyncServiceAdapter) PushSync(ctx context.Context, pluginId, siteId int64) (*sync.PushSyncResult, *apperror.AppError) {
	result := a.Service.PushSync(ctx, pluginId, siteId)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *SyncServiceAdapter) RecordFileChange(ctx context.Context, change *models.FileChange) *apperror.AppError {
	return a.Service.RecordFileChange(ctx, change)
}

func (a *SyncServiceAdapter) MarkSynced(ctx context.Context, pluginId, siteId int64, files []string) *apperror.AppError {
	return a.Service.MarkSynced(ctx, pluginId, siteId, files)
}

func (a *SyncServiceAdapter) ClearChanges(ctx context.Context, pluginId int64) *apperror.AppError {
	return a.Service.ClearChanges(ctx, pluginId)
}

// WatcherServiceInterface defines watcher service methods for HTTP handlers.
type WatcherServiceInterface interface {
	TriggerScan(ctx context.Context, pluginId int64) (*watcher.ScanResult, *apperror.AppError)
	ScanAll(ctx context.Context) ([]watcher.ScanResult, *apperror.AppError)
}

// WatcherServiceAdapter wraps *watcher.Service to implement WatcherServiceInterface
type WatcherServiceAdapter struct {
	*watcher.Service
}

func (a *WatcherServiceAdapter) TriggerScan(ctx context.Context, pluginId int64) (*watcher.ScanResult, *apperror.AppError) {
	result := a.Service.TriggerScan(ctx, pluginId)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *WatcherServiceAdapter) ScanAll(ctx context.Context) ([]watcher.ScanResult, *apperror.AppError) {
	result := a.Service.ScanAll(ctx)
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Items(), nil
}
