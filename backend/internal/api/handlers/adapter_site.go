// Package handlers - Site service adapter
package handlers

import (
	"context"
	"net/http"

	"wp-plugin-publish/internal/services/site"
)

// SiteServiceAdapter wraps *site.Service to implement SiteServiceInterface
type SiteServiceAdapter struct {
	*site.Service
}

func (a *SiteServiceAdapter) List(ctx context.Context) (interface{}, error) {
	return a.Service.List(ctx)
}

func (a *SiteServiceAdapter) GetByID(ctx context.Context, id int64) (interface{}, error) {
	return a.Service.GetByID(ctx, id)
}

func (a *SiteServiceAdapter) Create(ctx context.Context, input interface{}) (interface{}, error) {
	in, ok := input.(SiteCreateInput)
	if !ok {
		if m, ok := input.(map[string]interface{}); ok {
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

func (a *SiteServiceAdapter) Update(ctx context.Context, id int64, input interface{}) (interface{}, error) {
	updateInput := site.UpdateInput{}
	if in, ok := input.(SiteUpdateInput); ok {
		updateInput.Name = in.Name
		updateInput.URL = in.URL
		updateInput.Username = in.Username
		updateInput.Password = in.Password
	} else if m, ok := input.(map[string]interface{}); ok {
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

func (a *SiteServiceAdapter) TestConnection(ctx context.Context, id int64) (interface{}, error) {
	return a.Service.TestConnection(ctx, id)
}

func (a *SiteServiceAdapter) TestConnectionWithCredentials(ctx context.Context, url, username, password string) (interface{}, error) {
	return a.Service.TestConnectionWithCredentials(ctx, url, username, password)
}

func (a *SiteServiceAdapter) BootstrapUploader(ctx context.Context, id int64, uploaderPath string) (interface{}, error) {
	return a.Service.BootstrapUploader(ctx, id, uploaderPath)
}

func (a *SiteServiceAdapter) GetRemotePlugins(ctx context.Context, siteID int64) (interface{}, error) {
	return a.Service.GetRemotePlugins(ctx, siteID)
}

func (a *SiteServiceAdapter) ForceSyncRemotePlugins(ctx context.Context, siteID int64) (interface{}, error) {
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

func (a *SiteServiceAdapter) DeleteRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error {
	return a.Service.DeleteRemotePlugin(ctx, siteID, pluginSlug)
}

func (a *SiteServiceAdapter) GetRemotePluginFiles(ctx context.Context, siteID int64, pluginSlug string) (interface{}, error) {
	return a.Service.GetRemotePluginFiles(ctx, siteID, pluginSlug)
}

func (a *SiteServiceAdapter) GetRemotePluginFileContent(ctx context.Context, siteID int64, pluginSlug, filePath string) (string, error) {
	return a.Service.GetRemotePluginFileContent(ctx, siteID, pluginSlug, filePath)
}

func (a *SiteServiceAdapter) GetCredentials(ctx context.Context, siteID int64) (interface{}, error) {
	return a.Service.GetCredentials(ctx, siteID)
}

func (a *SiteServiceAdapter) GetRemoteSnapshots(ctx context.Context, siteID int64) (interface{}, error) {
	return a.Service.GetRemoteSnapshots(ctx, siteID)
}

func (a *SiteServiceAdapter) GetRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) (interface{}, error) {
	return a.Service.GetRemoteSnapshot(ctx, siteID, snapshotID)
}

func (a *SiteServiceAdapter) CreateRemoteSnapshot(ctx context.Context, siteID int64, opts map[string]interface{}) (interface{}, error) {
	return a.Service.CreateRemoteSnapshot(ctx, siteID, opts)
}

func (a *SiteServiceAdapter) DeleteRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) error {
	return a.Service.DeleteRemoteSnapshot(ctx, siteID, snapshotID)
}

func (a *SiteServiceAdapter) RestoreRemoteSnapshot(ctx context.Context, siteID, snapshotID int64, opts map[string]interface{}) (interface{}, error) {
	return a.Service.RestoreRemoteSnapshot(ctx, siteID, snapshotID, opts)
}

func (a *SiteServiceAdapter) ExportRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) (*http.Response, error) {
	return a.Service.ExportRemoteSnapshot(ctx, siteID, snapshotID)
}

func (a *SiteServiceAdapter) GetRemoteSnapshotSettings(ctx context.Context, siteID int64) (interface{}, error) {
	return a.Service.GetRemoteSnapshotSettings(ctx, siteID)
}

func (a *SiteServiceAdapter) UpdateRemoteSnapshotSettings(ctx context.Context, siteID int64, settings map[string]interface{}) (interface{}, error) {
	return a.Service.UpdateRemoteSnapshotSettings(ctx, siteID, settings)
}

func (a *SiteServiceAdapter) GetRemoteSnapshotProviders(ctx context.Context, siteID int64) (interface{}, error) {
	return a.Service.GetRemoteSnapshotProviders(ctx, siteID)
}

func (a *SiteServiceAdapter) GetRemoteAvailableTables(ctx context.Context, siteID int64) (interface{}, error) {
	return a.Service.GetRemoteAvailableTables(ctx, siteID)
}

func (a *SiteServiceAdapter) FullBackupRemoteSnapshot(ctx context.Context, siteID int64, opts map[string]interface{}) (interface{}, error) {
	return a.Service.FullBackupRemoteSnapshot(ctx, siteID, opts)
}

func (a *SiteServiceAdapter) IncrementalBackupRemoteSnapshot(ctx context.Context, siteID int64, opts map[string]interface{}) (interface{}, error) {
	return a.Service.IncrementalBackupRemoteSnapshot(ctx, siteID, opts)
}
