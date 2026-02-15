// Package handlers - Plugin service interface and adapter
package handlers

import (
	"context"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/plugin"
)

// PluginServiceInterface defines plugin service methods needed by handlers
type PluginServiceInterface interface {
	List(ctx context.Context) ([]models.Plugin, error)
	GetByID(ctx context.Context, id int64) (*models.Plugin, error)
	Create(ctx context.Context, input plugin.CreateInput) (*models.Plugin, error)
	Update(ctx context.Context, id int64, input plugin.UpdateInput) (*models.Plugin, error)
	Delete(ctx context.Context, id int64) error
	GetMappings(ctx context.Context, pluginID int64) ([]models.PluginMapping, error)
	GetMappingsBySite(ctx context.Context, siteID int64) ([]models.PluginMapping, error)
	CreateMapping(ctx context.Context, pluginID int64, input plugin.CreateMappingInput) (*models.PluginMapping, error)
	DeleteMapping(ctx context.Context, id int64) error
	UpdateMappingsForPlugin(ctx context.Context, pluginID int64, siteIDs []int64, remoteSlug string) error
	UpdateMappingsForSite(ctx context.Context, siteID int64, pluginIDs []int64) error
	ScanDirectory(ctx context.Context, path string) (*plugin.ScanResult, error)
	WritePluginDetected(ctx context.Context, path string) error
	RefreshFileCount(ctx context.Context, id int64) error
}

// PluginServiceAdapter wraps *plugin.Service to implement PluginServiceInterface
type PluginServiceAdapter struct {
	*plugin.Service
}

func (a *PluginServiceAdapter) List(ctx context.Context) ([]models.Plugin, error) {
	return a.Service.List(ctx)
}

func (a *PluginServiceAdapter) GetByID(ctx context.Context, id int64) (*models.Plugin, error) {
	return a.Service.GetByID(ctx, id)
}

func (a *PluginServiceAdapter) Create(ctx context.Context, input plugin.CreateInput) (*models.Plugin, error) {
	return a.Service.Create(ctx, input)
}

func (a *PluginServiceAdapter) Update(ctx context.Context, id int64, input plugin.UpdateInput) (*models.Plugin, error) {
	return a.Service.Update(ctx, id, input)
}

func (a *PluginServiceAdapter) Delete(ctx context.Context, id int64) error {
	return a.Service.Delete(ctx, id)
}

func (a *PluginServiceAdapter) GetMappings(ctx context.Context, pluginID int64) ([]models.PluginMapping, error) {
	return a.Service.GetMappings(ctx, pluginID)
}

func (a *PluginServiceAdapter) CreateMapping(ctx context.Context, pluginID int64, input plugin.CreateMappingInput) (*models.PluginMapping, error) {
	return a.Service.CreateMapping(ctx, input)
}

func (a *PluginServiceAdapter) DeleteMapping(ctx context.Context, id int64) error {
	return a.Service.DeleteMapping(ctx, id)
}

func (a *PluginServiceAdapter) GetMappingsBySite(ctx context.Context, siteID int64) ([]models.PluginMapping, error) {
	return a.Service.GetMappingsBySite(ctx, siteID)
}

func (a *PluginServiceAdapter) UpdateMappingsForPlugin(ctx context.Context, pluginID int64, siteIDs []int64, remoteSlug string) error {
	return a.Service.UpdateMappingsForPlugin(ctx, pluginID, siteIDs, remoteSlug)
}

func (a *PluginServiceAdapter) UpdateMappingsForSite(ctx context.Context, siteID int64, pluginIDs []int64) error {
	return a.Service.UpdateMappingsForSite(ctx, siteID, pluginIDs)
}

func (a *PluginServiceAdapter) ScanDirectory(ctx context.Context, path string) (*plugin.ScanResult, error) {
	return a.Service.ScanDirectory(ctx, path)
}

func (a *PluginServiceAdapter) WritePluginDetected(ctx context.Context, path string) error {
	return a.Service.WritePluginDetected(ctx, path)
}

func (a *PluginServiceAdapter) RefreshFileCount(ctx context.Context, id int64) error {
	return a.Service.RefreshFileCount(ctx, id)
}
