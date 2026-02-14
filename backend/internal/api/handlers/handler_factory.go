// Package handlers - Generic handler factories to eliminate CRUD boilerplate
//
// All factories use lazy service resolution (func() any) because the global
// Services registry is nil at package init time and only populated during server startup.
package handlers

import (
	"context"
	"net/http"
)

// --- Generic Handler Factories ---

// handleActionByID creates a handler: requireService → parseID → fn(ctx, id) → respondSuccess
// Use for endpoints that take a single URL ID param and return (any, error).
func handleActionByID(
	getService func() any,
	serviceName string,
	paramName string,
	paramLabel string,
	errCode string,
	fn func(ctx context.Context, id int64) (any, error),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if !requireService(w, getService(), serviceName) {
			return
		}
		id, ok := parseID(w, r, paramName, paramLabel)
		if !ok {
			return
		}
		result, err := fn(r.Context(), id)
		if err != nil {
			respondError(w, http.StatusInternalServerError, errCode, err.Error())
			return
		}
		respondSuccess(w, result)
	}
}

// handleDeleteByID creates a handler: requireService → parseID → fn(ctx, id) → respondDeleted
// Use for endpoints that delete a resource by its URL ID param.
func handleDeleteByID(
	getService func() any,
	serviceName string,
	paramName string,
	paramLabel string,
	errCode string,
	fn func(ctx context.Context, id int64) error,
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if !requireService(w, getService(), serviceName) {
			return
		}
		id, ok := parseID(w, r, paramName, paramLabel)
		if !ok {
			return
		}
		if err := fn(r.Context(), id); err != nil {
			respondError(w, http.StatusBadRequest, errCode, err.Error())
			return
		}
		respondDeleted(w)
	}
}

// handleListNilSafe creates a handler: nil-safe service check → fn(ctx) → respondSuccess
// If service is nil, returns an empty array. Use for list-all endpoints.
func handleListNilSafe(
	getService func() any,
	errCode string,
	fn func(ctx context.Context) (any, error),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if getService() == nil {
			respondSuccess(w, []any{})
			return
		}
		result, err := fn(r.Context())
		if err != nil {
			respondError(w, http.StatusInternalServerError, errCode, err.Error())
			return
		}
		respondSuccess(w, result)
	}
}

// handleSiteActionByID creates a handler for site-scoped actions:
// nil-safe site service check → getIDParam("id") → fn(ctx, siteID) → respondSuccess
// Use for remote plugin/snapshot endpoints that only need a site ID.
func handleSiteActionByID(
	errCode string,
	fn func(ctx context.Context, siteID int64) (any, error),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if Services == nil || Services.SiteService == nil {
			respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
			return
		}
		siteID, err := getIDParam(r, "id")
		if err != nil {
			respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
			return
		}
		result, err := fn(r.Context(), siteID)
		if err != nil {
			respondError(w, http.StatusInternalServerError, errCode, err.Error())
			return
		}
		respondSuccess(w, result)
	}
}

// handleSiteActionByIDWithOpts creates a handler for site-scoped actions with optional JSON body:
// nil-safe site service check → getIDParam("id") → decode optional opts → fn(ctx, siteID, opts) → respondCreated
func handleSiteActionByIDWithOpts(
	errCode string,
	fn func(ctx context.Context, siteID int64, opts map[string]any) (any, error),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if Services == nil || Services.SiteService == nil {
			respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
			return
		}
		siteID, err := getIDParam(r, "id")
		if err != nil {
			respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
			return
		}
		opts := decodeOptionalOpts(r)
		result, err := fn(r.Context(), siteID, opts)
		if err != nil {
			respondError(w, http.StatusInternalServerError, errCode, err.Error())
			return
		}
		respondCreated(w, result)
	}
}

// handleNoArgs creates a handler: requireService → fn(ctx) → respondSuccess
// Use for endpoints with no parameters (e.g., check-all, pull-all).
func handleNoArgs(
	getService func() any,
	serviceName string,
	errCode string,
	fn func(ctx context.Context) (any, error),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if !requireService(w, getService(), serviceName) {
			return
		}
		result, err := fn(r.Context())
		if err != nil {
			respondError(w, http.StatusInternalServerError, errCode, err.Error())
			return
		}
		respondSuccess(w, result)
	}
}

// handleTwoIDs creates a handler: requireService → parseID(param1) → parseID(param2) → fn(ctx, id1, id2) → respondSuccess
// Use for endpoints that take two URL ID params (e.g., pluginID + siteID).
func handleTwoIDs(
	getService func() any,
	serviceName string,
	param1Name, param1Label string,
	param2Name, param2Label string,
	errCode string,
	fn func(ctx context.Context, id1, id2 int64) (any, error),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if !requireService(w, getService(), serviceName) {
			return
		}
		id1, ok := parseID(w, r, param1Name, param1Label)
		if !ok {
			return
		}
		id2, ok := parseID(w, r, param2Name, param2Label)
		if !ok {
			return
		}
		result, err := fn(r.Context(), id1, id2)
		if err != nil {
			respondError(w, http.StatusInternalServerError, errCode, err.Error())
			return
		}
		respondSuccess(w, result)
	}
}

// decodeOptionalOpts reads an optional JSON body into a map. Returns empty map on failure.
func decodeOptionalOpts(r *http.Request) map[string]any {
	var opts map[string]any
	if r.Body != nil {
		_ = decodeJSONSilent(r, &opts)
	}
	if opts == nil {
		opts = map[string]any{}
	}
	return opts
}

// --- Service getters for lazy resolution ---
// These return nil (not panic) when Services is nil.

func siteService() any {
	if Services == nil { return nil }
	return Services.SiteService
}
func pluginService() any {
	if Services == nil { return nil }
	return Services.PluginService
}
func syncService() any {
	if Services == nil { return nil }
	return Services.SyncService
}
func gitService() any {
	if Services == nil { return nil }
	return Services.GitService
}
func watcherService() any {
	if Services == nil { return nil }
	return Services.WatcherService
}
func publishService() any {
	if Services == nil { return nil }
	return Services.PublishService
}
func backupService() any {
	if Services == nil { return nil }
	return Services.BackupService
}
func versionServiceGetter() any { return VersionService }
func e2eServiceGetter() any     { return E2EService }
