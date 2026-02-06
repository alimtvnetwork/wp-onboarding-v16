// Package handlers provides HTTP request handlers
package handlers

import (
	"context"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/backup"
	"wp-plugin-publish/internal/services/errorhistory"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/services/publish"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/services/site"
	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/services/watcher"
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
	// Convert interface{} to site.CreateInput
	in, ok := input.(SiteCreateInput)
	if !ok {
		// Try map conversion for JSON decoded input
		if m, ok := input.(map[string]interface{}); ok {
			in = SiteCreateInput{
				Name:     getString(m, "name"),
				URL:      getString(m, "url"),
				Username: getString(m, "username"),
				// Accept both "password" and "applicationPassword" keys
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
	// Convert interface{} to site.UpdateInput
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
		// Accept both "password" and "applicationPassword" keys
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

func (a *SiteServiceAdapter) GetRemoteSnapshotSettings(ctx context.Context, siteID int64) (interface{}, error) {
	return a.Service.GetRemoteSnapshotSettings(ctx, siteID)
}

func (a *SiteServiceAdapter) UpdateRemoteSnapshotSettings(ctx context.Context, siteID int64, settings map[string]interface{}) (interface{}, error) {
	return a.Service.UpdateRemoteSnapshotSettings(ctx, siteID, settings)
}

func (a *SiteServiceAdapter) GetRemoteSnapshotProviders(ctx context.Context, siteID int64) (interface{}, error) {
	return a.Service.GetRemoteSnapshotProviders(ctx, siteID)
}

// PluginServiceAdapter wraps *plugin.Service to implement PluginServiceInterface
type PluginServiceAdapter struct {
	*plugin.Service
}

func (a *PluginServiceAdapter) List(ctx context.Context) (interface{}, error) {
	return a.Service.List(ctx)
}

func (a *PluginServiceAdapter) GetByID(ctx context.Context, id int64) (interface{}, error) {
	return a.Service.GetByID(ctx, id)
}

func (a *PluginServiceAdapter) Create(ctx context.Context, input interface{}) (interface{}, error) {
	// Convert interface{} to plugin.CreateInput (frontend uses camelCase keys)
	createInput := plugin.CreateInput{}
	if m, ok := input.(map[string]interface{}); ok {
		createInput.Name = getStringAny(m, "name")
		createInput.Path = getStringAny(m, "path", "localPath", "local_path")
		createInput.WatchEnabled = getBoolAny(m, true, "watchEnabled", "watch_enabled")
		createInput.ExcludePatterns = getStringSliceAny(m, "excludePatterns", "exclude_patterns")
		createInput.GitEnabled = getBoolAny(m, false, "gitEnabled", "git_enabled")
		createInput.GitRemoteURL = getStringAny(m, "gitRemoteUrl", "git_remote_url")
		createInput.BuildCommand = getStringAny(m, "buildCommand", "build_command")
		createInput.ForceCreate = getBoolAny(m, false, "forceCreate", "force_create")
	}
	return a.Service.Create(ctx, createInput)
}

func (a *PluginServiceAdapter) Update(ctx context.Context, id int64, input interface{}) (interface{}, error) {
	updateInput := plugin.UpdateInput{}
	if m, ok := input.(map[string]interface{}); ok {
		if v, ok := m["name"].(string); ok {
			updateInput.Name = &v
		}
		if v, ok := firstString(m, "path", "localPath", "local_path"); ok {
			updateInput.Path = &v
		}
		if v, ok := firstBool(m, "watchEnabled", "watch_enabled"); ok {
			updateInput.WatchEnabled = &v
		}
		if v, ok := firstStringSlice(m, "excludePatterns", "exclude_patterns"); ok {
			updateInput.ExcludePatterns = &v
		}
		if v, ok := firstBool(m, "gitEnabled", "git_enabled"); ok {
			updateInput.GitEnabled = &v
		}
		if v, ok := firstString(m, "gitRemoteUrl", "git_remote_url"); ok {
			updateInput.GitRemoteURL = &v
		}
		if v, ok := firstString(m, "buildCommand", "build_command"); ok {
			updateInput.BuildCommand = &v
		}
	}
	return a.Service.Update(ctx, id, updateInput)
}

func (a *PluginServiceAdapter) Delete(ctx context.Context, id int64) error {
	return a.Service.Delete(ctx, id)
}

func (a *PluginServiceAdapter) GetMappings(ctx context.Context, pluginID int64) (interface{}, error) {
	return a.Service.GetMappings(ctx, pluginID)
}

func (a *PluginServiceAdapter) CreateMapping(ctx context.Context, pluginID int64, input interface{}) (interface{}, error) {
	createInput := plugin.CreateMappingInput{}
	createInput.PluginID = pluginID
	if m, ok := input.(map[string]interface{}); ok {
		if v, ok := m["siteId"].(float64); ok {
			createInput.SiteID = int64(v)
		} else if v, ok := m["site_id"].(float64); ok {
			createInput.SiteID = int64(v)
		}
		createInput.RemoteSlug = getStringAny(m, "remoteSlug", "remote_slug", "remoteSlug")
	}
	return a.Service.CreateMapping(ctx, createInput)
}

func (a *PluginServiceAdapter) DeleteMapping(ctx context.Context, id int64) error {
	return a.Service.DeleteMapping(ctx, id)
}

func (a *PluginServiceAdapter) GetMappingsBySite(ctx context.Context, siteID int64) (interface{}, error) {
	return a.Service.GetMappingsBySite(ctx, siteID)
}

func (a *PluginServiceAdapter) UpdateMappingsForPlugin(ctx context.Context, pluginID int64, siteIDs []int64, remoteSlug string) error {
	return a.Service.UpdateMappingsForPlugin(ctx, pluginID, siteIDs, remoteSlug)
}

func (a *PluginServiceAdapter) UpdateMappingsForSite(ctx context.Context, siteID int64, pluginIDs []int64) error {
	return a.Service.UpdateMappingsForSite(ctx, siteID, pluginIDs)
}

func (a *PluginServiceAdapter) ScanDirectory(ctx context.Context, path string) (interface{}, error) {
	return a.Service.ScanDirectory(ctx, path)
}

func (a *PluginServiceAdapter) WritePluginDetected(ctx context.Context, path string) error {
	return a.Service.WritePluginDetected(ctx, path)
}

func (a *PluginServiceAdapter) RefreshFileCount(ctx context.Context, id int64) error {
	return a.Service.RefreshFileCount(ctx, id)
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

// PublishServiceAdapter wraps *publish.Service to implement PublishServiceInterface
type PublishServiceAdapter struct {
	*publish.Service
}

func (a *PublishServiceAdapter) Publish(ctx context.Context, pluginID, siteID int64, opts interface{}) (interface{}, error) {
	return a.Service.Publish(ctx, pluginID, siteID, opts)
}

func (a *PublishServiceAdapter) PublishFiles(ctx context.Context, pluginID, siteID int64, files []string) (interface{}, error) {
	return a.Service.PublishFiles(ctx, pluginID, siteID, files)
}

func (a *PublishServiceAdapter) PreviewPublish(ctx context.Context, pluginID, siteID int64) (interface{}, error) {
	return a.Service.PreviewPublish(ctx, pluginID, siteID)
}

func (a *PublishServiceAdapter) GetFileDiff(ctx context.Context, pluginID, siteID int64, filePath string) (interface{}, error) {
	return a.Service.GetFileDiff(ctx, pluginID, siteID, filePath)
}

// BackupServiceAdapter wraps *backup.Service to implement BackupServiceInterface
type BackupServiceAdapter struct {
	*backup.Service
}

func (a *BackupServiceAdapter) List(ctx context.Context, pluginID int64) (interface{}, error) {
	return a.Service.List(ctx, pluginID)
}

func (a *BackupServiceAdapter) Create(ctx context.Context, pluginID, siteID int64) (interface{}, error) {
	// The actual backup.Service.Create only takes pluginID
	// siteID is not used in the current implementation
	return a.Service.Create(ctx, pluginID)
}

func (a *BackupServiceAdapter) Restore(ctx context.Context, backupID int64) error {
	_, err := a.Service.Restore(ctx, backupID)
	return err
}

func (a *BackupServiceAdapter) Delete(ctx context.Context, backupID int64) error {
	return a.Service.Delete(ctx, backupID)
}

// SessionServiceAdapter wraps *session.Service to implement SessionServiceInterface
type SessionServiceAdapter struct {
	*session.Service
}

func (a *SessionServiceAdapter) ListSessions(limit int) (interface{}, error) {
	return a.Service.ListSessions(limit)
}

func (a *SessionServiceAdapter) GetSession(sessionID string) (interface{}, error) {
	return a.Service.GetSession(sessionID)
}

func (a *SessionServiceAdapter) GetSessionLogs(sessionID string) (string, error) {
	return a.Service.GetSessionLogs(sessionID)
}

func (a *SessionServiceAdapter) DeleteSession(sessionID string) error {
	return a.Service.DeleteSession(sessionID)
}

// NewServiceRegistry creates a ServiceRegistry from concrete service implementations
func NewServiceRegistry(
	siteService *site.Service,
	pluginService *plugin.Service,
	syncService sync.Service,
	gitService GitServiceInterface,
	watcherService *watcher.Service,
	publishService *publish.Service,
	backupService *backup.Service,
	sessionService *session.Service,
	errorHistoryService *errorhistory.Service,
) *ServiceRegistry {
	var sessionAdapter SessionServiceInterface
	if sessionService != nil {
		sessionAdapter = &SessionServiceAdapter{sessionService}
	}
	
	var errorHistoryAdapter ErrorHistoryServiceInterface
	if errorHistoryService != nil {
		errorHistoryAdapter = &ErrorHistoryServiceAdapter{errorHistoryService}
	}
	
	return &ServiceRegistry{
		SiteService:         &SiteServiceAdapter{siteService},
		PluginService:       &PluginServiceAdapter{pluginService},
		SyncService:         &SyncServiceAdapter{syncService},
		GitService:          gitService,
		WatcherService:      &WatcherServiceAdapter{watcherService},
		PublishService:      &PublishServiceAdapter{publishService},
		BackupService:       &BackupServiceAdapter{backupService},
		SessionService:      sessionAdapter,
		ErrorHistoryService: errorHistoryAdapter,
	}
}

// ErrorHistoryServiceAdapter wraps *errorhistory.Service to implement ErrorHistoryServiceInterface
type ErrorHistoryServiceAdapter struct {
	*errorhistory.Service
}

func (a *ErrorHistoryServiceAdapter) Save(input models.ErrorHistoryInput) (*models.ErrorHistory, error) {
	return a.Service.Save(input)
}

func (a *ErrorHistoryServiceAdapter) List(limit, offset int, filters models.ErrorHistoryFilters) ([]models.ErrorHistory, int, error) {
	return a.Service.List(limit, offset, filters)
}

func (a *ErrorHistoryServiceAdapter) GetByID(id int64) (*models.ErrorHistory, error) {
	return a.Service.GetByID(id)
}

func (a *ErrorHistoryServiceAdapter) GetByErrorID(errorID string) (*models.ErrorHistory, error) {
	return a.Service.GetByErrorID(errorID)
}

func (a *ErrorHistoryServiceAdapter) Delete(id int64) error {
	return a.Service.Delete(id)
}

func (a *ErrorHistoryServiceAdapter) Clear() (int64, error) {
	return a.Service.Clear()
}

func (a *ErrorHistoryServiceAdapter) BulkExport(ids []int64) (string, error) {
	return a.Service.BulkExport(ids)
}

func (a *ErrorHistoryServiceAdapter) GetStats() (map[string]interface{}, error) {
	return a.Service.GetStats()
}

// Helper functions
func getString(m map[string]interface{}, key string) string {
	if v, ok := m[key].(string); ok {
		return v
	}
	return ""
}

func getBool(m map[string]interface{}, key string, defaultVal bool) bool {
	if v, ok := m[key].(bool); ok {
		return v
	}
	return defaultVal
}

func getStringAny(m map[string]interface{}, keys ...string) string {
	for _, k := range keys {
		if v, ok := m[k].(string); ok {
			return v
		}
	}
	return ""
}

func getBoolAny(m map[string]interface{}, defaultVal bool, keys ...string) bool {
	for _, k := range keys {
		if v, ok := m[k].(bool); ok {
			return v
		}
	}
	return defaultVal
}

func getStringSliceAny(m map[string]interface{}, keys ...string) []string {
	for _, k := range keys {
		if raw, ok := m[k]; ok {
			if ss, ok := raw.([]string); ok {
				return ss
			}
			if arr, ok := raw.([]interface{}); ok {
				out := make([]string, 0, len(arr))
				for _, it := range arr {
					if s, ok := it.(string); ok {
						out = append(out, s)
					}
				}
				return out
			}
		}
	}
	return nil
}

// Optional getters for Update inputs
func firstString(m map[string]interface{}, keys ...string) (string, bool) {
	for _, k := range keys {
		if v, ok := m[k].(string); ok {
			return v, true
		}
	}
	return "", false
}

func firstBool(m map[string]interface{}, keys ...string) (bool, bool) {
	for _, k := range keys {
		if v, ok := m[k].(bool); ok {
			return v, true
		}
	}
	return false, false
}

func firstStringSlice(m map[string]interface{}, keys ...string) ([]string, bool) {
	for _, k := range keys {
		if raw, ok := m[k]; ok {
			if ss, ok := raw.([]string); ok {
				return ss, true
			}
			if arr, ok := raw.([]interface{}); ok {
				out := make([]string, 0, len(arr))
				for _, it := range arr {
					if s, ok := it.(string); ok {
						out = append(out, s)
					}
				}
				return out, true
			}
		}
	}
	return nil, false
}

// Ensure adapters implement their interfaces
var _ SiteServiceInterface = (*SiteServiceAdapter)(nil)
var _ PluginServiceInterface = (*PluginServiceAdapter)(nil)
var _ SyncServiceInterface = (*SyncServiceAdapter)(nil)
var _ WatcherServiceInterface = (*WatcherServiceAdapter)(nil)
var _ PublishServiceInterface = (*PublishServiceAdapter)(nil)
var _ BackupServiceInterface = (*BackupServiceAdapter)(nil)
var _ SessionServiceInterface = (*SessionServiceAdapter)(nil)
var _ ErrorHistoryServiceInterface = (*ErrorHistoryServiceAdapter)(nil)

// Placeholder types to satisfy imports (actual types come from models package)
var _ = models.Site{}
