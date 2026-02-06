// Package handlers - Plugin service adapter
package handlers

import (
	"context"

	"wp-plugin-publish/internal/services/plugin"
)

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
