// Package handlers provides plugin-related HTTP request handlers
package handlers

import (
	"context"
	"net/http"

	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/wordpress"
)

// GetPlugins returns all registered plugins
var GetPlugins = handleListNilSafe(pluginService, "E3001",
	func(ctx context.Context) (any, error) {
		return Services.PluginService.List(ctx)
	},
)

// CreatePlugin registers a new local plugin directory
func CreatePlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}
	var input plugin.CreateInput
	if !decodeJSON(w, r, &input) {
		return
	}
	p, err := Services.PluginService.Create(r.Context(), input)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E3002", err.Error())
		return
	}
	respondCreated(w, p)
}

// GetPlugin returns a specific plugin by ID
var GetPlugin = handleActionByID(
	handlerIDConfig{
		GetService:  pluginService,
		ServiceName: "Plugin service",
		ParamName:   "id",
		ErrCode:     "E3003",
	},
	func(ctx context.Context, id int64) (any, error) {
		return Services.PluginService.GetById(ctx, id)
	},
)

// UpdatePlugin updates an existing plugin
func UpdatePlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}
	id, ok := parseID(w, r, "id")
	if !ok {
		return
	}
	var input plugin.UpdateInput
	if !decodeJSON(w, r, &input) {
		return
	}
	p, err := Services.PluginService.Update(r.Context(), id, input)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E3004", err.Error())
		return
	}
	respondSuccess(w, p)
}

// DeletePlugin removes a plugin registration
var DeletePlugin = handleDeleteByID(
	handlerIDConfig{
		GetService:  pluginService,
		ServiceName: "Plugin service",
		ParamName:   "id",
		ErrCode:     "E3005",
	},
	func(ctx context.Context, id int64) error {
		return Services.PluginService.Delete(ctx, id)
	},
)

// GetPluginMappings returns plugin-site mappings
var GetPluginMappings = handleActionByID(
	handlerIDConfig{
		GetService:  pluginService,
		ServiceName: "Plugin service",
		ParamName:   "id",
		ErrCode:     "E3006",
	},
	func(ctx context.Context, id int64) (any, error) {
		return Services.PluginService.GetMappings(ctx, id)
	},
)

// CreatePluginMapping creates a new plugin-site mapping
func CreatePluginMapping(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}
	id, ok := parseID(w, r, "id")
	if !ok {
		return
	}
	var input plugin.CreateMappingInput
	if !decodeJSON(w, r, &input) {
		return
	}
	input.PluginID = id

	mapping, err := Services.PluginService.CreateMapping(r.Context(), id, input)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E3007", err.Error())
		return
	}
	respondCreated(w, mapping)
}

// DeletePluginMapping removes a plugin-site mapping
var DeletePluginMapping = handleDeleteByID(
	handlerIDConfig{
		GetService:  pluginService,
		ServiceName: "Plugin service",
		ParamName:   "id",
		ErrCode:     "E3008",
	},
	func(ctx context.Context, id int64) error {
		return Services.PluginService.DeleteMapping(ctx, id)
	},
)

// pluginMappingsInput is the JSON body for UpdatePluginMappings.
type pluginMappingsInput struct {
	SiteIDs    []int64 `json:"siteIds"`    // external key (frontend request body)
	RemoteSlug string  `json:"remoteSlug"` // external key
}

// UpdatePluginMappings bulk-updates all site mappings for a plugin
func UpdatePluginMappings(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}
	id, ok := parseID(w, r, "id")
	if !ok {
		return
	}
	var input pluginMappingsInput
	if !decodeJSON(w, r, &input) {
		return
	}
	if err := Services.PluginService.UpdateMappingsForPlugin(r.Context(), id, input.SiteIDs, input.RemoteSlug); err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E3009", err.Error())
		return
	}
	mappings, _ := Services.PluginService.GetMappings(r.Context(), id)
	respondSuccess(w, mappings)
}

// GetSiteMappings returns all plugin mappings for a site
var GetSiteMappings = handleActionByID(
	handlerIDConfig{
		GetService:  pluginService,
		ServiceName: "Plugin service",
		ParamName:   "id",
		ErrCode:     "E3010",
	},
	func(ctx context.Context, id int64) (any, error) {
		return Services.PluginService.GetMappingsBySite(ctx, id)
	},
)

// siteMappingsInput is the JSON body for UpdateSiteMappings.
type siteMappingsInput struct {
	PluginIDs []int64 `json:"pluginIds"` // external key (frontend request body)
}

// UpdateSiteMappings bulk-updates all plugin mappings for a site
func UpdateSiteMappings(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}
	siteID, ok := parseID(w, r, "id")
	if !ok {
		return
	}
	var input siteMappingsInput
	if !decodeJSON(w, r, &input) {
		return
	}
	if err := Services.PluginService.UpdateMappingsForSite(r.Context(), siteID, input.PluginIDs); err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E3011", err.Error())
		return
	}
	mappings, _ := Services.PluginService.GetMappingsBySite(r.Context(), siteID)
	respondSuccess(w, mappings)
}
