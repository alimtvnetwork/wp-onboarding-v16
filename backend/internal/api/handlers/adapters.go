// Package handlers - Compile-time interface assertions for all adapters
package handlers

import "wp-plugin-publish/internal/models"

// Compile-time interface checks
var _ SiteServiceInterface = (*SiteServiceAdapter)(nil)
var _ PluginServiceInterface = (*PluginServiceAdapter)(nil)
var _ SyncServiceInterface = (*SyncServiceAdapter)(nil)
var _ WatcherServiceInterface = (*WatcherServiceAdapter)(nil)
var _ PublishServiceInterface = (*PublishServiceAdapter)(nil)
var _ BackupServiceInterface = (*BackupServiceAdapter)(nil)
var _ SessionServiceInterface = (*SessionServiceAdapter)(nil)
var _ ErrorHistoryServiceInterface = (*ErrorHistoryServiceAdapter)(nil)
var _ PublishHistoryServiceInterface = (*PublishHistoryServiceAdapter)(nil)
var _ SiteHealthServiceInterface = (*SiteHealthServiceAdapter)(nil)

// Placeholder to satisfy imports (actual types come from models package)
var _ = models.Site{}
