// Package handlers - Site service interface and adapter
package handlers

import (
	"context"
	"net/http"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/site"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// SiteServiceInterface defines site service methods
type SiteServiceInterface interface {
	// Core CRUD — typed inputs and returns
	List(ctx context.Context) ([]models.Site, *apperror.AppError)
	GetById(ctx context.Context, id int64) (*models.Site, *apperror.AppError)
	Create(ctx context.Context, input SiteCreateInput) (*models.Site, *apperror.AppError)
	Update(ctx context.Context, id int64, input SiteUpdateInput) (*models.Site, *apperror.AppError)
	Delete(ctx context.Context, id int64) *apperror.AppError
	TestConnection(ctx context.Context, id int64) (*site.ConnectionResult, *apperror.AppError)
	TestConnectionWithCredentials(ctx context.Context, url, username, password string) (*site.ConnectionResult, *apperror.AppError)
	BootstrapUploader(ctx context.Context, id int64, uploaderPath string) (*site.BootstrapResult, *apperror.AppError)
	GetCredentials(ctx context.Context, siteId int64) (site.SiteCredentials, *apperror.AppError)

	// Credential CRUD
	ListCredentials(ctx context.Context, siteId int64) ([]database.SiteCredential, *apperror.AppError)
	CreateCredential(ctx context.Context, siteId int64, input CredentialCreateInput) (*database.SiteCredential, *apperror.AppError)
	UpdateCredential(ctx context.Context, credId int64, input CredentialUpdateInput) (*database.SiteCredential, *apperror.AppError)
	DeleteCredential(ctx context.Context, credId int64) *apperror.AppError
	SetDefaultCredential(ctx context.Context, siteId, credId int64) *apperror.AppError

	// Remote plugin proxy — typed returns
	GetRemotePlugins(ctx context.Context, siteId int64) ([]site.RemotePlugin, *apperror.AppError)
	ForceSyncRemotePlugins(ctx context.Context, siteId int64) ([]site.RemotePlugin, *apperror.AppError)
	InvalidateRemotePluginsCache(ctx context.Context, siteId int64) *apperror.AppError
	EnableRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) *apperror.AppError
	DisableRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) *apperror.AppError
	CheckRemotePluginExists(ctx context.Context, siteId int64, pluginSlug string) (*wordpress.PluginExistsResult, *apperror.AppError)
	DeleteRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) *apperror.AppError
	GetRemotePluginFiles(ctx context.Context, siteId int64, pluginSlug string) ([]wordpress.RemoteFile, *apperror.AppError)
	GetRemotePluginFileContent(ctx context.Context, siteId int64, pluginSlug, filePath string) (string, *apperror.AppError)

	// Snapshot proxy — typed returns
	GetRemoteSnapshots(ctx context.Context, siteId int64) ([]wordpress.SnapshotRecord, *apperror.AppError)
	GetRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) (*wordpress.SnapshotRecord, *apperror.AppError)
	CreateRemoteSnapshot(ctx context.Context, siteId int64, opts wordpress.SnapshotCreateOptions) (*wordpress.SnapshotCreateResult, *apperror.AppError)
	DeleteRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) *apperror.AppError
	RestoreRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) (*wordpress.SnapshotRestoreResult, *apperror.AppError)
	ExportRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) (*http.Response, *apperror.AppError)
	DownloadSnapshotZip(ctx context.Context, siteId, snapshotId int64) (*site.SnapshotZipDownload, *apperror.AppError)
	GetRemoteSnapshotSettings(ctx context.Context, siteId int64) (*wordpress.SnapshotSettings, *apperror.AppError)
	UpdateRemoteSnapshotSettings(ctx context.Context, siteId int64, settings wordpress.SnapshotSettings) (*wordpress.SnapshotSettings, *apperror.AppError)
	GetRemoteSnapshotProviders(ctx context.Context, siteId int64) ([]wordpress.SnapshotProvider, *apperror.AppError)
	GetRemoteAvailableTables(ctx context.Context, siteId int64) ([]wordpress.AvailableTable, *apperror.AppError)
	FullBackupRemoteSnapshot(ctx context.Context, siteId int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, *apperror.AppError)
	IncrementalBackupRemoteSnapshot(ctx context.Context, siteId int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, *apperror.AppError)
	ImportRemoteSnapshot(ctx context.Context, siteId int64, zipPath string) (*wordpress.SnapshotImportResult, *apperror.AppError)
	CleanupRemoteSnapshots(ctx context.Context, siteId int64, opts wordpress.SnapshotCleanupOptions) (*wordpress.SnapshotCleanupResult, *apperror.AppError)
	ClearErrorLogHashes() int

	// Remote log management
	GetRemoteLogsStatus(ctx context.Context, siteId int64) (any, *apperror.AppError)
	RequestRemoteLogsClear(ctx context.Context, siteId int64) (any, *apperror.AppError)
	ConfirmRemoteLogsClear(ctx context.Context, siteId int64, token string) (any, *apperror.AppError)
	EmailRemoteLogs(ctx context.Context, siteId int64, body map[string]any) (any, *apperror.AppError)
}

// SiteServiceAdapter wraps *site.Service to implement SiteServiceInterface
type SiteServiceAdapter struct {
	*site.Service
}

func (a *SiteServiceAdapter) List(ctx context.Context) ([]models.Site, *apperror.AppError) {
	result := a.Service.List(ctx)
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Items(), nil
}

func (a *SiteServiceAdapter) GetById(ctx context.Context, id int64) (*models.Site, *apperror.AppError) {
	result := a.Service.GetById(ctx, id)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *SiteServiceAdapter) Create(ctx context.Context, input SiteCreateInput) (*models.Site, *apperror.AppError) {
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

func (a *SiteServiceAdapter) Update(ctx context.Context, id int64, input SiteUpdateInput) (*models.Site, *apperror.AppError) {
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

func (a *SiteServiceAdapter) Delete(ctx context.Context, id int64) *apperror.AppError {
	return a.Service.Delete(ctx, id)
}

func (a *SiteServiceAdapter) TestConnection(ctx context.Context, id int64) (*site.ConnectionResult, *apperror.AppError) {
	return a.Service.TestConnection(ctx, id)
}

func (a *SiteServiceAdapter) TestConnectionWithCredentials(ctx context.Context, url, username, password string) (*site.ConnectionResult, *apperror.AppError) {
	return a.Service.TestConnectionWithCredentials(url, username, password)
}

func (a *SiteServiceAdapter) BootstrapUploader(ctx context.Context, id int64, uploaderPath string) (*site.BootstrapResult, *apperror.AppError) {
	return a.Service.BootstrapUploader(ctx, id, uploaderPath)
}

func (a *SiteServiceAdapter) GetRemotePlugins(ctx context.Context, siteId int64) ([]site.RemotePlugin, *apperror.AppError) {
	return a.Service.GetRemotePlugins(ctx, siteId)
}

func (a *SiteServiceAdapter) ForceSyncRemotePlugins(ctx context.Context, siteId int64) ([]site.RemotePlugin, *apperror.AppError) {
	return a.Service.ForceSyncRemotePlugins(ctx, siteId)
}

func (a *SiteServiceAdapter) InvalidateRemotePluginsCache(ctx context.Context, siteId int64) *apperror.AppError {
	return a.Service.InvalidateRemotePluginsCache(ctx, siteId)
}

func (a *SiteServiceAdapter) EnableRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) *apperror.AppError {
	return a.Service.EnableRemotePlugin(ctx, siteId, pluginSlug)
}

func (a *SiteServiceAdapter) DisableRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) *apperror.AppError {
	return a.Service.DisableRemotePlugin(ctx, siteId, pluginSlug)
}

func (a *SiteServiceAdapter) CheckRemotePluginExists(ctx context.Context, siteId int64, pluginSlug string) (*wordpress.PluginExistsResult, *apperror.AppError) {
	return a.Service.CheckRemotePluginExists(ctx, siteId, pluginSlug)
}

func (a *SiteServiceAdapter) DeleteRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) *apperror.AppError {
	return a.Service.DeleteRemotePlugin(ctx, siteId, pluginSlug)
}

func (a *SiteServiceAdapter) GetRemotePluginFiles(ctx context.Context, siteId int64, pluginSlug string) ([]wordpress.RemoteFile, *apperror.AppError) {
	return a.Service.GetRemotePluginFiles(ctx, siteId, pluginSlug)
}

func (a *SiteServiceAdapter) GetRemotePluginFileContent(ctx context.Context, siteId int64, pluginSlug, filePath string) (string, *apperror.AppError) {
	return a.Service.GetRemotePluginFileContent(ctx, siteId, pluginSlug, filePath)
}

func (a *SiteServiceAdapter) GetCredentials(ctx context.Context, siteId int64) (site.SiteCredentials, *apperror.AppError) {
	result := a.Service.GetCredentials(ctx, siteId)
	if result.HasError() {
		return site.SiteCredentials{}, result.AppError()
	}

	return result.Value(), nil
}

func (a *SiteServiceAdapter) GetRemoteSnapshots(ctx context.Context, siteId int64) ([]wordpress.SnapshotRecord, *apperror.AppError) {
	return a.Service.GetRemoteSnapshots(ctx, siteId)
}

func (a *SiteServiceAdapter) GetRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) (*wordpress.SnapshotRecord, *apperror.AppError) {
	return a.Service.GetRemoteSnapshot(ctx, siteId, snapshotId)
}

func (a *SiteServiceAdapter) CreateRemoteSnapshot(ctx context.Context, siteId int64, opts wordpress.SnapshotCreateOptions) (*wordpress.SnapshotCreateResult, *apperror.AppError) {
	return a.Service.CreateRemoteSnapshot(ctx, siteId, opts)
}

func (a *SiteServiceAdapter) DeleteRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) *apperror.AppError {
	return a.Service.DeleteRemoteSnapshot(ctx, siteId, snapshotId)
}

func (a *SiteServiceAdapter) RestoreRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) (*wordpress.SnapshotRestoreResult, *apperror.AppError) {
	return a.Service.RestoreRemoteSnapshot(ctx, siteId, snapshotId)
}

func (a *SiteServiceAdapter) ExportRemoteSnapshot(ctx context.Context, siteId, snapshotId int64) (*http.Response, *apperror.AppError) {
	return a.Service.ExportRemoteSnapshot(ctx, siteId, snapshotId)
}

func (a *SiteServiceAdapter) DownloadSnapshotZip(ctx context.Context, siteId, snapshotId int64) (*site.SnapshotZipDownload, *apperror.AppError) {
	return a.Service.DownloadSnapshotZip(ctx, siteId, snapshotId)
}

func (a *SiteServiceAdapter) GetRemoteSnapshotSettings(ctx context.Context, siteId int64) (*wordpress.SnapshotSettings, *apperror.AppError) {
	return a.Service.GetRemoteSnapshotSettings(ctx, siteId)
}

func (a *SiteServiceAdapter) UpdateRemoteSnapshotSettings(ctx context.Context, siteId int64, settings wordpress.SnapshotSettings) (*wordpress.SnapshotSettings, *apperror.AppError) {
	return a.Service.UpdateRemoteSnapshotSettings(ctx, siteId, settings)
}

func (a *SiteServiceAdapter) GetRemoteSnapshotProviders(ctx context.Context, siteId int64) ([]wordpress.SnapshotProvider, *apperror.AppError) {
	return a.Service.GetRemoteSnapshotProviders(ctx, siteId)
}

func (a *SiteServiceAdapter) GetRemoteAvailableTables(ctx context.Context, siteId int64) ([]wordpress.AvailableTable, *apperror.AppError) {
	return a.Service.GetRemoteAvailableTables(ctx, siteId)
}

func (a *SiteServiceAdapter) FullBackupRemoteSnapshot(ctx context.Context, siteId int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, *apperror.AppError) {
	return a.Service.FullBackupRemoteSnapshot(ctx, siteId, opts)
}

func (a *SiteServiceAdapter) IncrementalBackupRemoteSnapshot(ctx context.Context, siteId int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, *apperror.AppError) {
	return a.Service.IncrementalBackupRemoteSnapshot(ctx, siteId, opts)
}

func (a *SiteServiceAdapter) ImportRemoteSnapshot(ctx context.Context, siteId int64, zipPath string) (*wordpress.SnapshotImportResult, *apperror.AppError) {
	return a.Service.ImportRemoteSnapshot(ctx, siteId, zipPath)
}

func (a *SiteServiceAdapter) CleanupRemoteSnapshots(ctx context.Context, siteId int64, opts wordpress.SnapshotCleanupOptions) (*wordpress.SnapshotCleanupResult, *apperror.AppError) {
	return a.Service.CleanupRemoteSnapshots(ctx, siteId, opts)
}

func (a *SiteServiceAdapter) ClearErrorLogHashes() int {
	return a.Service.ClearErrorLogHashes()
}

func (a *SiteServiceAdapter) ListCredentials(_ context.Context, siteId int64) ([]database.SiteCredential, *apperror.AppError) {
	return a.Service.DB().ListSiteCredentials(siteId)
}

func (a *SiteServiceAdapter) CreateCredential(_ context.Context, siteId int64, input CredentialCreateInput) (*database.SiteCredential, *apperror.AppError) {
	return a.Service.CreateCredential(siteId, input.AppName, input.Username, input.Password)
}

func (a *SiteServiceAdapter) UpdateCredential(_ context.Context, credId int64, input CredentialUpdateInput) (*database.SiteCredential, *apperror.AppError) {
	return a.Service.UpdateCredential(credId, input.AppName, input.Username, input.Password)
}

func (a *SiteServiceAdapter) DeleteCredential(_ context.Context, credId int64) *apperror.AppError {
	return a.Service.DB().DeleteSiteCredential(credId)
}

func (a *SiteServiceAdapter) SetDefaultCredential(_ context.Context, siteId, credId int64) *apperror.AppError {
	return a.Service.DB().SetDefaultCredential(siteId, credId)
}

func (a *SiteServiceAdapter) GetRemoteLogsStatus(ctx context.Context, siteId int64) (any, *apperror.AppError) {
	return a.Service.GetRemoteLogsStatus(ctx, siteId)
}

func (a *SiteServiceAdapter) RequestRemoteLogsClear(ctx context.Context, siteId int64) (any, *apperror.AppError) {
	return a.Service.RequestRemoteLogsClear(ctx, siteId)
}

func (a *SiteServiceAdapter) ConfirmRemoteLogsClear(ctx context.Context, siteId int64, token string) (any, *apperror.AppError) {
	return a.Service.ConfirmRemoteLogsClear(ctx, siteId, token)
}
