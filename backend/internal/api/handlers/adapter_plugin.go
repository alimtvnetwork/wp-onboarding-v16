// Package handlers - Plugin service interface and adapter
package handlers

import (
	"context"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/plugin"
)

// PluginServiceInterface defines plugin service methods needed by handlers.
// Returns (T, error) tuples — the adapter unwraps Result types from the service layer.
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

// PluginServiceAdapter wraps *plugin.Service to implement PluginServiceInterface.
// It unwraps Result[T] and ResultSlice[T] into (T, error) tuples for handler consumption.
type PluginServiceAdapter struct {
	*plugin.Service
}

func (a *PluginServiceAdapter) List(ctx context.Context) ([]models.Plugin, error) {
	result := a.Service.List(ctx)
	if result.HasError() {
		return nil, result.Error()
	}
	return result.Items(), nil
}

func (a *PluginServiceAdapter) GetByID(ctx context.Context, id int64) (*models.Plugin, error) {
	result := a.Service.GetByID(ctx, id)
	if result.HasError() {
		return nil, result.Error()
	}
	v := result.Value()
	return &v, nil
}

func (a *PluginServiceAdapter) Create(ctx context.Context, input plugin.CreateInput) (*models.Plugin, error) {
	result := a.Service.Create(ctx, input)
	if result.HasError() {
		return nil, result.Error()
	}
	v := result.Value()
	return &v, nil
}

func (a *PluginServiceAdapter) Update(ctx context.Context, id int64, input plugin.UpdateInput) (*models.Plugin, error) {
	result := a.Service.Update(ctx, id, input)
	if result.HasError() {
		return nil, result.Error()
	}
	v := result.Value()
	return &v, nil
}

func (a *PluginServiceAdapter) Delete(ctx context.Context, id int64) error {
	return a.Service.Delete(ctx, id)
}

func (a *PluginServiceAdapter) GetMappings(ctx context.Context, pluginID int64) ([]models.PluginMapping, error) {
	result := a.Service.GetMappings(ctx, pluginID)
	if result.HasError() {
		return nil, result.Error()
	}
	return result.Items(), nil
}

func (a *PluginServiceAdapter) CreateMapping(ctx context.Context, pluginID int64, input plugin.CreateMappingInput) (*models.PluginMapping, error) {
	result := a.Service.CreateMapping(ctx, input)
	if result.HasError() {
		return nil, result.Error()
	}
	v := result.Value()
	return &v, nil
}

func (a *PluginServiceAdapter) DeleteMapping(ctx context.Context, id int64) error {
	return a.Service.DeleteMapping(ctx, id)
}

func (a *PluginServiceAdapter) GetMappingsBySite(ctx context.Context, siteID int64) ([]models.PluginMapping, error) {
	result := a.Service.GetMappingsBySite(ctx, siteID)
	if result.HasError() {
		return nil, result.Error()
	}
	return result.Items(), nil
}

func (a *PluginServiceAdapter) UpdateMappingsForPlugin(ctx context.Context, pluginID int64, siteIDs []int64, remoteSlug string) error {
	return a.Service.UpdateMappingsForPlugin(ctx, pluginID, siteIDs, remoteSlug)
}

func (a *PluginServiceAdapter) UpdateMappingsForSite(ctx context.Context, siteID int64, pluginIDs []int64) error {
	return a.Service.UpdateMappingsForSite(ctx, siteID, pluginIDs)
}

func (a *PluginServiceAdapter) ScanDirectory(ctx context.Context, path string) (*plugin.ScanResult, error) {
	result := a.Service.ScanDirectory(ctx, path)
	if result.HasError() {
		return nil, result.Error()
	}
	v := result.Value()
	return &v, nil
}

func (a *PluginServiceAdapter) WritePluginDetected(ctx context.Context, path string) error {
	return a.Service.WritePluginDetected(ctx, path)
}

func (a *PluginServiceAdapter) RefreshFileCount(ctx context.Context, id int64) error {
	return a.Service.RefreshFileCount(ctx, id)
}
