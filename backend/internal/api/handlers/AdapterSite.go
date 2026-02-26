// Package handlers - Site service interface and adapter
package handlers

import (
	"context"
	"net/http"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/site"
	"wp-plugin-publish/internal/wordpress"
)

// SiteServiceInterface defines site service methods
type SiteServiceInterface interface {
	// Core CRUD — typed inputs and returns
	List(ctx context.Context) ([]models.Site, error)
	GetById(ctx context.Context, id int64) (*models.Site, error)
	Create(ctx context.Context, input SiteCreateInput) (*models.Site, error)
	Update(ctx context.Context, id int64, input SiteUpdateInput) (*models.Site, error)
	Delete(ctx context.Context, id int64) error
	TestConnection(ctx context.Context, id int64) (*site.ConnectionResult, error)
	TestConnectionWithCredentials(ctx context.Context, url, username, password string) (*site.ConnectionResult, error)
	BootstrapUploader(ctx context.Context, id int64, uploaderPath string) (*site.BootstrapResult, error)
	GetCredentials(ctx context.Context, siteId int64) (*site.SiteCredentials, error)

	// Remote plugin proxy — typed returns
	GetRemotePlugins(ctx context.Context, siteId int64) ([]site.RemotePlugin, error)
	ForceSyncRemotePlugins(ctx context.Context, siteId int64) ([]site.RemotePlugin, error)
	InvalidateRemotePluginsCache(ctx context.Context, siteId int64) error
	EnableRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) error
	DisableRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) error
	CheckRemotePluginExists(ctx context.Context, siteId int64, pluginSlug string) (*wordpress.PluginExistsResult, error)
	DeleteRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) error
	GetRemotePluginFiles(ctx context.Context, siteId int64, pluginSlug string) (*site.RemotePluginFilesResult, error)
	GetRemotePluginFileContent(ctx context.Context, siteId int64, pluginSlug, filePath string) (string, error)

	// Snapshot proxy — typed returns
	GetRemoteSnapshots(ctx context.Context, siteId int64) ([]wordpress.SnapshotRecord, error)
	GetRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) (*wordpress.SnapshotRecord, error)
	CreateRemoteSnapshot(ctx context.Context, siteId int64, opts wordpress.SnapshotCreateOptions) (*wordpress.SnapshotCreateResult, error)
	DeleteRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) error
	RestoreRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) (*wordpress.SnapshotRestoreResult, error)
	ExportRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) (*http.Response, error)
	DownloadSnapshotZip(ctx context.Context, siteId, snapshotId int64) (*site.SnapshotZipDownload, error)
	GetRemoteSnapshotSettings(ctx context.Context, siteId int64) (*wordpress.SnapshotSettings, error)
	UpdateRemoteSnapshotSettings(ctx context.Context, siteId int64, settings wordpress.SnapshotSettings) (*wordpress.SnapshotSettings, error)
	GetRemoteSnapshotProviders(ctx context.Context, siteId int64) ([]wordpress.SnapshotProvider, error)
	GetRemoteAvailableTables(ctx context.Context, siteId int64) ([]wordpress.AvailableTable, error)
	FullBackupRemoteSnapshot(ctx context.Context, siteId int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, error)
	IncrementalBackupRemoteSnapshot(ctx context.Context, siteId int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, error)
	ImportRemoteSnapshot(ctx context.Context, siteId int64, zipPath string) (*wordpress.SnapshotImportResult, error)
	CleanupRemoteSnapshots(ctx context.Context, siteId int64, opts wordpress.SnapshotCleanupOptions) (*wordpress.SnapshotCleanupResult, error)
	ClearErrorLogHashes() int
}

// SiteServiceAdapter wraps *site.Service to implement SiteServiceInterface
type SiteServiceAdapter struct {
	*site.Service
}

func (a *SiteServiceAdapter) List(ctx context.Context) ([]models.Site, error) {
	result := a.Service.List(ctx)
	if result.HasError() {
		return nil, result.AppError()
	}
	return result.Items(), nil
}

func (a *SiteServiceAdapter) GetById(ctx context.Context, id int64) (*models.Site, error) {
	result := a.Service.GetById(ctx, id)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *SiteServiceAdapter) Create(ctx context.Context, input SiteCreateInput) (*models.Site, error) {
	siteInput := site.CreateInput{
		Name:     input.Name,
		Url:      input.Url,
		Username: input.Username,
		Password: input.Password,
	}
	result := a.Service.Create(ctx, siteInput)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *SiteServiceAdapter) Update(ctx context.Context, id int64, input SiteUpdateInput) (*models.Site, error) {
	updateInput := site.UpdateInput{
		Name:     input.Name,
		Url:      input.Url,
		Username: input.Username,
		Password: input.Password,
	}
	result := a.Service.Update(ctx, id, updateInput)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *SiteServiceAdapter) Delete(ctx context.Context, id int64) error {
	return a.Service.Delete(ctx, id)
}

func (a *SiteServiceAdapter) TestConnection(ctx context.Context, id int64) (*site.ConnectionResult, error) {
	return a.Service.TestConnection(ctx, id)
}

func (a *SiteServiceAdapter) TestConnectionWithCredentials(ctx context.Context, url, username, password string) (*site.ConnectionResult, error) {
	return a.Service.TestConnectionWithCredentials(ctx, url, username, password)
}

func (a *SiteServiceAdapter) BootstrapUploader(ctx context.Context, id int64, uploaderPath string) (*site.BootstrapResult, error) {
	return a.Service.BootstrapUploader(ctx, id, uploaderPath)
}

func (a *SiteServiceAdapter) GetRemotePlugins(ctx context.Context, siteId int64) ([]site.RemotePlugin, error) {
	return a.Service.GetRemotePlugins(ctx, siteId)
}

func (a *SiteServiceAdapter) ForceSyncRemotePlugins(ctx context.Context, siteId int64) ([]site.RemotePlugin, error) {
	return a.Service.ForceSyncRemotePlugins(ctx, siteId)
}

func (a *SiteServiceAdapter) InvalidateRemotePluginsCache(ctx context.Context, siteId int64) error {
	return a.Service.InvalidateRemotePluginsCache(ctx, siteId)
}

func (a *SiteServiceAdapter) EnableRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) error {
	return a.Service.EnableRemotePlugin(ctx, siteId, pluginSlug)
}

func (a *SiteServiceAdapter) DisableRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) error {
	return a.Service.DisableRemotePlugin(ctx, siteId, pluginSlug)
}

func (a *SiteServiceAdapter) CheckRemotePluginExists(ctx context.Context, siteId int64, pluginSlug string) (*wordpress.PluginExistsResult, error) {
	return a.Service.CheckRemotePluginExists(ctx, siteId, pluginSlug)
}

func (a *SiteServiceAdapter) DeleteRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) error {
	return a.Service.DeleteRemotePlugin(ctx, siteId, pluginSlug)
}

func (a *SiteServiceAdapter) GetRemotePluginFiles(ctx context.Context, siteId int64, pluginSlug string) (*site.RemotePluginFilesResult, error) {
	return a.Service.GetRemotePluginFiles(ctx, siteId, pluginSlug)
}

func (a *SiteServiceAdapter) GetRemotePluginFileContent(ctx context.Context, siteId int64, pluginSlug, filePath string) (string, error) {
	return a.Service.GetRemotePluginFileContent(ctx, siteId, pluginSlug, filePath)
}

func (a *SiteServiceAdapter) GetCredentials(ctx context.Context, siteId int64) (*site.SiteCredentials, error) {
	return a.Service.GetCredentials(ctx, siteId)
}

func (a *SiteServiceAdapter) GetRemoteSnapshots(ctx context.Context, siteId int64) ([]wordpress.SnapshotRecord, error) {
	return a.Service.GetRemoteSnapshots(ctx, siteId)
}

func (a *SiteServiceAdapter) GetRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) (*wordpress.SnapshotRecord, error) {
	return a.Service.GetRemoteSnapshot(ctx, siteId, snapshotId)
}

func (a *SiteServiceAdapter) CreateRemoteSnapshot(ctx context.Context, siteId int64, opts wordpress.SnapshotCreateOptions) (*wordpress.SnapshotCreateResult, error) {
	return a.Service.CreateRemoteSnapshot(ctx, siteId, opts)
}

func (a *SiteServiceAdapter) DeleteRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) error {
	return a.Service.DeleteRemoteSnapshot(ctx, siteId, snapshotId)
}

func (a *SiteServiceAdapter) RestoreRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) (*wordpress.SnapshotRestoreResult, error) {
	return a.Service.RestoreRemoteSnapshot(ctx, siteId, snapshotId)
}

func (a *SiteServiceAdapter) ExportRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) (*http.Response, error) {
	return a.Service.ExportRemoteSnapshot(ctx, siteId, snapshotId)
}

func (a *SiteServiceAdapter) DownloadSnapshotZip(ctx context.Context, siteId, snapshotId int64) (*site.SnapshotZipDownload, error) {
	return a.Service.DownloadSnapshotZip(ctx, siteId, snapshotId)
}

func (a *SiteServiceAdapter) GetRemoteSnapshotSettings(ctx context.Context, siteId int64) (*wordpress.SnapshotSettings, error) {
	return a.Service.GetRemoteSnapshotSettings(ctx, siteId)
}

func (a *SiteServiceAdapter) UpdateRemoteSnapshotSettings(ctx context.Context, siteId int64, settings wordpress.SnapshotSettings) (*wordpress.SnapshotSettings, error) {
	return a.Service.UpdateRemoteSnapshotSettings(ctx, siteId, settings)
}

func (a *SiteServiceAdapter) GetRemoteSnapshotProviders(ctx context.Context, siteId int64) ([]wordpress.SnapshotProvider, error) {
	return a.Service.GetRemoteSnapshotProviders(ctx, siteId)
}

func (a *SiteServiceAdapter) GetRemoteAvailableTables(ctx context.Context, siteId int64) ([]wordpress.AvailableTable, error) {
	return a.Service.GetRemoteAvailableTables(ctx, siteId)
}

func (a *SiteServiceAdapter) FullBackupRemoteSnapshot(ctx context.Context, siteId int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, error) {
	return a.Service.FullBackupRemoteSnapshot(ctx, siteId, opts)
}

func (a *SiteServiceAdapter) IncrementalBackupRemoteSnapshot(ctx context.Context, siteId int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, error) {
	return a.Service.IncrementalBackupRemoteSnapshot(ctx, siteId, opts)
}

func (a *SiteServiceAdapter) ImportRemoteSnapshot(ctx context.Context, siteId int64, zipPath string) (*wordpress.SnapshotImportResult, error) {
	return a.Service.ImportRemoteSnapshot(ctx, siteId, zipPath)
}

func (a *SiteServiceAdapter) CleanupRemoteSnapshots(ctx context.Context, siteId int64, opts wordpress.SnapshotCleanupOptions) (*wordpress.SnapshotCleanupResult, error) {
	return a.Service.CleanupRemoteSnapshots(ctx, siteId, opts)
}

func (a *SiteServiceAdapter) ClearErrorLogHashes() int {
	return a.Service.ClearErrorLogHashes()
}
