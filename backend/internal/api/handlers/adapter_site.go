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
	// Core CRUD — typed returns
	List(ctx context.Context) ([]models.Site, error)
	GetByID(ctx context.Context, id int64) (*models.Site, error)
	Create(ctx context.Context, input any) (*models.Site, error)
	Update(ctx context.Context, id int64, input any) (*models.Site, error)
	Delete(ctx context.Context, id int64) error
	TestConnection(ctx context.Context, id int64) (*site.ConnectionResult, error)
	TestConnectionWithCredentials(ctx context.Context, url, username, password string) (*site.ConnectionResult, error)
	BootstrapUploader(ctx context.Context, id int64, uploaderPath string) (*site.BootstrapResult, error)
	GetCredentials(ctx context.Context, siteID int64) (*site.SiteCredentials, error)

	// Remote plugin proxy — typed returns
	GetRemotePlugins(ctx context.Context, siteID int64) ([]site.RemotePlugin, error)
	ForceSyncRemotePlugins(ctx context.Context, siteID int64) ([]site.RemotePlugin, error)
	InvalidateRemotePluginsCache(ctx context.Context, siteID int64) error
	EnableRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error
	DisableRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error
	CheckRemotePluginExists(ctx context.Context, siteID int64, pluginSlug string) (bool, string, string, error)
	DeleteRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error
	GetRemotePluginFiles(ctx context.Context, siteID int64, pluginSlug string) (*site.RemotePluginFilesResult, error)
	GetRemotePluginFileContent(ctx context.Context, siteID int64, pluginSlug, filePath string) (string, error)

	// Snapshot proxy — typed returns
	GetRemoteSnapshots(ctx context.Context, siteID int64) ([]wordpress.SnapshotRecord, error)
	GetRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) (*wordpress.SnapshotRecord, error)
	CreateRemoteSnapshot(ctx context.Context, siteID int64, opts wordpress.SnapshotCreateOptions) (*wordpress.SnapshotCreateResult, error)
	DeleteRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) error
	RestoreRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) (*wordpress.SnapshotRestoreResult, error)
	ExportRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) (*http.Response, error)
	DownloadSnapshotZip(ctx context.Context, siteID, snapshotID int64) (*http.Response, *wordpress.SnapshotDownloadResult, error)
	GetRemoteSnapshotSettings(ctx context.Context, siteID int64) (*wordpress.SnapshotSettings, error)
	UpdateRemoteSnapshotSettings(ctx context.Context, siteID int64, settings wordpress.SnapshotSettings) (*wordpress.SnapshotSettings, error)
	GetRemoteSnapshotProviders(ctx context.Context, siteID int64) ([]wordpress.SnapshotProvider, error)
	GetRemoteAvailableTables(ctx context.Context, siteID int64) ([]wordpress.AvailableTable, error)
	FullBackupRemoteSnapshot(ctx context.Context, siteID int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, error)
	IncrementalBackupRemoteSnapshot(ctx context.Context, siteID int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, error)
	ImportRemoteSnapshot(ctx context.Context, siteID int64, zipPath string) (*wordpress.SnapshotImportResult, error)
	CleanupRemoteSnapshots(ctx context.Context, siteID int64, opts wordpress.SnapshotCleanupOptions) (*wordpress.SnapshotCleanupResult, error)
	ClearErrorLogHashes() int
}

// SiteServiceAdapter wraps *site.Service to implement SiteServiceInterface
type SiteServiceAdapter struct {
	*site.Service
}

func (a *SiteServiceAdapter) List(ctx context.Context) ([]models.Site, error) {
	return a.Service.List(ctx)
}

func (a *SiteServiceAdapter) GetByID(ctx context.Context, id int64) (*models.Site, error) {
	return a.Service.GetByID(ctx, id)
}

func (a *SiteServiceAdapter) Create(ctx context.Context, input any) (*models.Site, error) {
	in, ok := input.(SiteCreateInput)
	if !ok {
		if m, ok := input.(map[string]any); ok {
			in = SiteCreateInput{
				Name:     getString(m, "name"),
				URL:      getString(m, "url"),
				Username: getString(m, "username"),
				Password: getStringAny(m, "password", "applicationPassword", "application_password"),
			}
		}
	}
	siteInput := site.CreateInput{
		Name:     in.Name,
		URL:      in.URL,
		Username: in.Username,
		Password: in.Password,
	}
	return a.Service.Create(ctx, siteInput)
}

func (a *SiteServiceAdapter) Update(ctx context.Context, id int64, input any) (*models.Site, error) {
	updateInput := site.UpdateInput{}
	if in, ok := input.(SiteUpdateInput); ok {
		updateInput.Name = in.Name
		updateInput.URL = in.URL
		updateInput.Username = in.Username
		updateInput.Password = in.Password
	} else if m, ok := input.(map[string]any); ok {
		if v, ok := m["name"].(string); ok {
			updateInput.Name = &v
		}
		if v, ok := m["url"].(string); ok {
			updateInput.URL = &v
		}
		if v, ok := m["username"].(string); ok {
			updateInput.Username = &v
		}
		if v, ok := firstString(m, "password", "applicationPassword", "application_password"); ok && v != "" {
			updateInput.Password = &v
		}
	}
	return a.Service.Update(ctx, id, updateInput)
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

func (a *SiteServiceAdapter) GetRemotePlugins(ctx context.Context, siteID int64) ([]site.RemotePlugin, error) {
	return a.Service.GetRemotePlugins(ctx, siteID)
}

func (a *SiteServiceAdapter) ForceSyncRemotePlugins(ctx context.Context, siteID int64) ([]site.RemotePlugin, error) {
	return a.Service.ForceSyncRemotePlugins(ctx, siteID)
}

func (a *SiteServiceAdapter) InvalidateRemotePluginsCache(ctx context.Context, siteID int64) error {
	return a.Service.InvalidateRemotePluginsCache(ctx, siteID)
}

func (a *SiteServiceAdapter) EnableRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error {
	return a.Service.EnableRemotePlugin(ctx, siteID, pluginSlug)
}

func (a *SiteServiceAdapter) DisableRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error {
	return a.Service.DisableRemotePlugin(ctx, siteID, pluginSlug)
}

func (a *SiteServiceAdapter) CheckRemotePluginExists(ctx context.Context, siteID int64, pluginSlug string) (bool, string, string, error) {
	return a.Service.CheckRemotePluginExists(ctx, siteID, pluginSlug)
}

func (a *SiteServiceAdapter) DeleteRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error {
	return a.Service.DeleteRemotePlugin(ctx, siteID, pluginSlug)
}

func (a *SiteServiceAdapter) GetRemotePluginFiles(ctx context.Context, siteID int64, pluginSlug string) (*site.RemotePluginFilesResult, error) {
	return a.Service.GetRemotePluginFiles(ctx, siteID, pluginSlug)
}

func (a *SiteServiceAdapter) GetRemotePluginFileContent(ctx context.Context, siteID int64, pluginSlug, filePath string) (string, error) {
	return a.Service.GetRemotePluginFileContent(ctx, siteID, pluginSlug, filePath)
}

func (a *SiteServiceAdapter) GetCredentials(ctx context.Context, siteID int64) (*site.SiteCredentials, error) {
	return a.Service.GetCredentials(ctx, siteID)
}

func (a *SiteServiceAdapter) GetRemoteSnapshots(ctx context.Context, siteID int64) ([]wordpress.SnapshotRecord, error) {
	return a.Service.GetRemoteSnapshots(ctx, siteID)
}

func (a *SiteServiceAdapter) GetRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) (*wordpress.SnapshotRecord, error) {
	return a.Service.GetRemoteSnapshot(ctx, siteID, snapshotID)
}

func (a *SiteServiceAdapter) CreateRemoteSnapshot(ctx context.Context, siteID int64, opts wordpress.SnapshotCreateOptions) (*wordpress.SnapshotCreateResult, error) {
	return a.Service.CreateRemoteSnapshot(ctx, siteID, opts)
}

func (a *SiteServiceAdapter) DeleteRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) error {
	return a.Service.DeleteRemoteSnapshot(ctx, siteID, snapshotID)
}

func (a *SiteServiceAdapter) RestoreRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) (*wordpress.SnapshotRestoreResult, error) {
	return a.Service.RestoreRemoteSnapshot(ctx, siteID, snapshotID)
}

func (a *SiteServiceAdapter) ExportRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) (*http.Response, error) {
	return a.Service.ExportRemoteSnapshot(ctx, siteID, snapshotID)
}

func (a *SiteServiceAdapter) DownloadSnapshotZip(ctx context.Context, siteID, snapshotID int64) (*http.Response, *wordpress.SnapshotDownloadResult, error) {
	return a.Service.DownloadSnapshotZip(ctx, siteID, snapshotID)
}

func (a *SiteServiceAdapter) GetRemoteSnapshotSettings(ctx context.Context, siteID int64) (*wordpress.SnapshotSettings, error) {
	return a.Service.GetRemoteSnapshotSettings(ctx, siteID)
}

func (a *SiteServiceAdapter) UpdateRemoteSnapshotSettings(ctx context.Context, siteID int64, settings wordpress.SnapshotSettings) (*wordpress.SnapshotSettings, error) {
	return a.Service.UpdateRemoteSnapshotSettings(ctx, siteID, settings)
}

func (a *SiteServiceAdapter) GetRemoteSnapshotProviders(ctx context.Context, siteID int64) ([]wordpress.SnapshotProvider, error) {
	return a.Service.GetRemoteSnapshotProviders(ctx, siteID)
}

func (a *SiteServiceAdapter) GetRemoteAvailableTables(ctx context.Context, siteID int64) ([]wordpress.AvailableTable, error) {
	return a.Service.GetRemoteAvailableTables(ctx, siteID)
}

func (a *SiteServiceAdapter) FullBackupRemoteSnapshot(ctx context.Context, siteID int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, error) {
	return a.Service.FullBackupRemoteSnapshot(ctx, siteID, opts)
}

func (a *SiteServiceAdapter) IncrementalBackupRemoteSnapshot(ctx context.Context, siteID int64, opts wordpress.SnapshotBackupOptions) (*wordpress.SnapshotBackupResult, error) {
	return a.Service.IncrementalBackupRemoteSnapshot(ctx, siteID, opts)
}

func (a *SiteServiceAdapter) ImportRemoteSnapshot(ctx context.Context, siteID int64, zipPath string) (*wordpress.SnapshotImportResult, error) {
	return a.Service.ImportRemoteSnapshot(ctx, siteID, zipPath)
}

func (a *SiteServiceAdapter) CleanupRemoteSnapshots(ctx context.Context, siteID int64, opts wordpress.SnapshotCleanupOptions) (*wordpress.SnapshotCleanupResult, error) {
	return a.Service.CleanupRemoteSnapshots(ctx, siteID, opts)
}

func (a *SiteServiceAdapter) ClearErrorLogHashes() int {
	return a.Service.ClearErrorLogHashes()
}
