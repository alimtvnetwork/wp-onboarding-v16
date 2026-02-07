// Package handlers - Sync and Watcher service adapters
package handlers

import (
	"context"

	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/services/watcher"
)

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
