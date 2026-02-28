// Package handlers provides plugin-related HTTP request handlers
package handlers

import (
	"context"
	"net/http"

	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// GetPlugins returns all registered plugins
var GetPlugins = handleListNilSafe(
	pluginService,
	apperror.ErrWPConnection,
	func(ctx context.Context) (any, *apperror.AppError) {
		return Services.PluginService.List(ctx)
	},
)

// CreatePlugin registers a new local plugin directory
func CreatePlugin(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.PluginService, "Plugin service") {
		return
	}

	var input plugin.CreateInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	p, appErr := Services.PluginService.Create(r.Context(), input)
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			apperror.ErrWPAuth,
			appErr.Error(),
		)

		return
	}

	respondCreated(w, p)
}

// GetPlugin returns a specific plugin by ID
var GetPlugin = handleActionById(
	handlerIdConfig{
		GetService:  pluginService,
		ServiceName: "Plugin service",
		ParamName:   "id",
		ErrCode:     apperror.ErrWPAPIDisabled,
	},
	func(ctx context.Context, id int64) (any, *apperror.AppError) {
		return Services.PluginService.GetById(ctx, id)
	},
)

// UpdatePlugin updates an existing plugin
func UpdatePlugin(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.PluginService, "Plugin service") {
		return
	}

	id, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	var input plugin.UpdateInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	p, appErr := Services.PluginService.Update(r.Context(), id, input)
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			apperror.ErrWPPluginList,
			appErr.Error(),
		)

		return
	}

	respondSuccess(w, p)
}

// DeletePlugin removes a plugin registration
var DeletePlugin = handleDeleteById(
	handlerIdConfig{
		GetService:  pluginService,
		ServiceName: "Plugin service",
		ParamName:   "id",
		ErrCode:     apperror.ErrWPPluginGet,
	},
	func(ctx context.Context, id int64) *apperror.AppError {
		return Services.PluginService.Delete(ctx, id)
	},
)

// GetPluginMappings returns plugin-site mappings
var GetPluginMappings = handleActionById(
	handlerIdConfig{
		GetService:  pluginService,
		ServiceName: "Plugin service",
		ParamName:   "id",
		ErrCode:     apperror.ErrWPPluginUpload,
	},
	func(ctx context.Context, id int64) (any, *apperror.AppError) {
		return Services.PluginService.GetMappings(ctx, id)
	},
)

// CreatePluginMapping creates a new plugin-site mapping
func CreatePluginMapping(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.PluginService, "Plugin service") {
		return
	}

	id, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	var input plugin.CreateMappingInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	input.PluginId = id

	mapping, appErr := Services.PluginService.CreateMapping(r.Context(), id, input)
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			apperror.ErrWPPluginActivate,
			appErr.Error(),
		)

		return
	}

	respondCreated(w, mapping)
}

// DeletePluginMapping removes a plugin-site mapping
var DeletePluginMapping = handleDeleteById(
	handlerIdConfig{
		GetService:  pluginService,
		ServiceName: "Plugin service",
		ParamName:   "id",
		ErrCode:     apperror.ErrWPTimeout,
	},
	func(ctx context.Context, id int64) *apperror.AppError {
		return Services.PluginService.DeleteMapping(ctx, id)
	},
)

// pluginMappingsInput is the JSON body for UpdatePluginMappings.
type pluginMappingsInput struct {
	SiteIds    []int64 `json:"siteIds"`    // external key (frontend request body)
	RemoteSlug string  `json:"remoteSlug"` // external key
}

// UpdatePluginMappings bulk-updates all site mappings for a plugin
func UpdatePluginMappings(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.PluginService, "Plugin service") {
		return
	}

	id, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	var input pluginMappingsInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	mappingsInput := plugin.UpdatePluginMappingsInput{
		PluginId:   id,
		SiteIds:    input.SiteIds,
		RemoteSlug: input.RemoteSlug,
	}

	appErr := Services.PluginService.UpdateMappingsForPlugin(r.Context(), mappingsInput)
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			apperror.ErrWPUploadFailed,
			appErr.Error(),
		)

		return
	}

	mappings, _ := Services.PluginService.GetMappings(r.Context(), id)

	respondSuccess(w, mappings)
}

// GetSiteMappings returns all plugin mappings for a site
var GetSiteMappings = handleActionById(
	handlerIdConfig{
		GetService:  pluginService,
		ServiceName: "Plugin service",
		ParamName:   "id",
		ErrCode:     apperror.ErrWPPluginDelete,
	},
	func(ctx context.Context, id int64) (any, *apperror.AppError) {
		return Services.PluginService.GetMappingsBySite(ctx, id)
	},
)

// siteMappingsInput is the JSON body for UpdateSiteMappings.
type siteMappingsInput struct {
	PluginIds []int64 `json:"pluginIds"` // external key (frontend request body)
}

// UpdateSiteMappings bulk-updates all plugin mappings for a site
func UpdateSiteMappings(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.PluginService, "Plugin service") {
		return
	}

	siteId, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	var input siteMappingsInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	appErr := Services.PluginService.UpdateMappingsForSite(r.Context(), siteId, input.PluginIds)
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			apperror.ErrWPPluginFiles,
			appErr.Error(),
		)

		return
	}

	mappings, _ := Services.PluginService.GetMappingsBySite(r.Context(), siteId)

	respondSuccess(w, mappings)
}
