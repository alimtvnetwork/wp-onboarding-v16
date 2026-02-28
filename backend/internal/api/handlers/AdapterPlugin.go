// Package handlers - Plugin service interface and adapter
package handlers

import (
	"context"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/pkg/apperror"
)

// PluginServiceInterface defines plugin service methods needed by handlers.
type PluginServiceInterface interface {
	List(ctx context.Context) ([]models.Plugin, *apperror.AppError)
	GetById(ctx context.Context, id int64) (*models.Plugin, *apperror.AppError)
	Create(ctx context.Context, input plugin.CreateInput) (*models.Plugin, *apperror.AppError)
	Update(ctx context.Context, id int64, input plugin.UpdateInput) (*models.Plugin, *apperror.AppError)
	Delete(ctx context.Context, id int64) *apperror.AppError
	GetMappings(ctx context.Context, pluginID int64) ([]models.PluginMapping, *apperror.AppError)
	GetMappingsBySite(ctx context.Context, siteID int64) ([]models.PluginMapping, *apperror.AppError)
	CreateMapping(ctx context.Context, pluginID int64, input plugin.CreateMappingInput) (*models.PluginMapping, *apperror.AppError)
	DeleteMapping(ctx context.Context, id int64) *apperror.AppError
	UpdateMappingsForPlugin(ctx context.Context, pluginID int64, siteIDs []int64, remoteSlug string) *apperror.AppError
	UpdateMappingsForSite(ctx context.Context, siteID int64, pluginIDs []int64) *apperror.AppError
	ScanDirectory(ctx context.Context, path string) (*plugin.ScanResult, *apperror.AppError)
	WritePluginDetected(ctx context.Context, path string) *apperror.AppError
	RefreshFileCount(ctx context.Context, id int64) *apperror.AppError
}

// PluginServiceAdapter wraps *plugin.Service to implement PluginServiceInterface.
type PluginServiceAdapter struct {
	*plugin.Service
}

func (a *PluginServiceAdapter) List(ctx context.Context) ([]models.Plugin, *apperror.AppError) {
	result := a.Service.List(ctx)
	if result.HasError() {

		return nil, result.AppError()
	}

	return result.Items(), nil
}

func (a *PluginServiceAdapter) GetById(ctx context.Context, id int64) (*models.Plugin, *apperror.AppError) {
	result := a.Service.GetById(ctx, id)
	if result.HasError() {

		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *PluginServiceAdapter) Create(ctx context.Context, input plugin.CreateInput) (*models.Plugin, *apperror.AppError) {
	result := a.Service.Create(ctx, input)
	if result.HasError() {

		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *PluginServiceAdapter) Update(ctx context.Context, id int64, input plugin.UpdateInput) (*models.Plugin, *apperror.AppError) {
	result := a.Service.Update(ctx, id, input)
	if result.HasError() {

		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *PluginServiceAdapter) Delete(ctx context.Context, id int64) *apperror.AppError {

	return a.Service.Delete(ctx, id)
}

func (a *PluginServiceAdapter) GetMappings(ctx context.Context, pluginID int64) ([]models.PluginMapping, *apperror.AppError) {
	result := a.Service.GetMappings(ctx, pluginID)
	if result.HasError() {

		return nil, result.AppError()
	}

	return result.Items(), nil
}

func (a *PluginServiceAdapter) CreateMapping(ctx context.Context, pluginID int64, input plugin.CreateMappingInput) (*models.PluginMapping, *apperror.AppError) {
	result := a.Service.CreateMapping(ctx, input)
	if result.HasError() {

		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *PluginServiceAdapter) DeleteMapping(ctx context.Context, id int64) *apperror.AppError {

	return a.Service.DeleteMapping(ctx, id)
}

func (a *PluginServiceAdapter) GetMappingsBySite(ctx context.Context, siteID int64) ([]models.PluginMapping, *apperror.AppError) {
	result := a.Service.GetMappingsBySite(ctx, siteID)
	if result.HasError() {

		return nil, result.AppError()
	}

	return result.Items(), nil
}

func (a *PluginServiceAdapter) UpdateMappingsForPlugin(ctx context.Context, pluginID int64, siteIDs []int64, remoteSlug string) *apperror.AppError {

	return a.Service.UpdateMappingsForPlugin(ctx, pluginID, siteIDs, remoteSlug)
}

func (a *PluginServiceAdapter) UpdateMappingsForSite(ctx context.Context, siteID int64, pluginIDs []int64) *apperror.AppError {

	return a.Service.UpdateMappingsForSite(ctx, siteID, pluginIDs)
}

func (a *PluginServiceAdapter) ScanDirectory(ctx context.Context, path string) (*plugin.ScanResult, *apperror.AppError) {
	result := a.Service.ScanDirectory(ctx, path)
	if result.HasError() {

		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *PluginServiceAdapter) WritePluginDetected(ctx context.Context, path string) *apperror.AppError {

	return a.Service.WritePluginDetected(ctx, path)
}

func (a *PluginServiceAdapter) RefreshFileCount(ctx context.Context, id int64) *apperror.AppError {

	return a.Service.RefreshFileCount(ctx, id)
}
