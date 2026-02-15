// Package handlers - Generic handler factories to eliminate CRUD boilerplate
//
// All factories use lazy service resolution (func() any) because the global
// Services registry is nil at package init time and only populated during server startup.
package handlers

import (
	"context"
	"net/http"

	"wp-plugin-publish/internal/wordpress"
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
			respondError(w, wordpress.HttpStatusServerError, errCode, err.Error())
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
			respondError(w, wordpress.HttpStatusBadRequest, errCode, err.Error())
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
			respondError(w, wordpress.HttpStatusServerError, errCode, err.Error())
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
			respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", wordpress.ResponseMessageServiceNotAvailable.String())
			return
		}
		siteID, err := getIDParam(r, "id")
		if err != nil {
			respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
			return
		}
		result, err := fn(r.Context(), siteID)
		if err != nil {
			respondError(w, wordpress.HttpStatusServerError, errCode, err.Error())
			return
		}
		respondSuccess(w, result)
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
			respondError(w, wordpress.HttpStatusServerError, errCode, err.Error())
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
			respondError(w, wordpress.HttpStatusServerError, errCode, err.Error())
			return
		}
		respondSuccess(w, result)
	}
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
