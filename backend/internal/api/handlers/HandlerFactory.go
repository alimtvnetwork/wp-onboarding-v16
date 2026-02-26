// Package handlers - Generic handler factories to eliminate CRUD boilerplate
//
// All factories use lazy service resolution (func() any) because the global
// Services registry is nil at package init time and only populated during server startup.
package handlers

import (
	"context"
	"net/http"

	"wp-plugin-publish/internal/enums/response_message"
	"wp-plugin-publish/internal/wordpress"
)

// --- Generic Handler Factories ---

// handlerIDConfig bundles parameters for single-ID handler factories.
type handlerIDConfig struct {
	GetService  func() any
	ServiceName string
	ParamName   string
	ErrCode     string
}

// handleActionByID creates a handler: requireService → parseID → fn(ctx, id) → respondSuccess
func handleActionByID(
	cfg handlerIDConfig,
	fn func(ctx context.Context, id int64) (any, error),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if !requireService(w, cfg.GetService(), cfg.ServiceName) {
			return
		}

		id, ok := parseID(w, r, cfg.ParamName)
		if !ok {
			return
		}

		result, err := fn(r.Context(), id)
		if err != nil {
			respondError(w, wordpress.HttpStatusServerError, cfg.ErrCode, err.Error())
			return
		}

		respondSuccess(w, result)
	}
}

// handleDeleteByID creates a handler: requireService → parseID → fn(ctx, id) → respondDeleted
func handleDeleteByID(
	cfg handlerIDConfig,
	fn func(ctx context.Context, id int64) error,
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if !requireService(w, cfg.GetService(), cfg.ServiceName) {
			return
		}

		id, ok := parseID(w, r, cfg.ParamName)
		if !ok {
			return
		}

		if err := fn(r.Context(), id); err != nil {
			respondError(w, wordpress.HttpStatusBadRequest, cfg.ErrCode, err.Error())
			return
		}

		respondDeleted(w)
	}
}

// handleListNilSafe creates a handler: nil-safe service check → fn(ctx) → respondSuccess
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

// handleSiteActionByID creates a handler for site-scoped actions.
func handleSiteActionByID(
	errCode string,
	fn func(ctx context.Context, siteID int64) (any, error),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if Services == nil || Services.SiteService == nil {
			respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", responsemessage.ServiceNotAvailable.String())
			return
		}

		siteID, err := getIDParam(r, "id")
		if err != nil {
			respondError(w, wordpress.HttpStatusBadRequest, "E1002", responsemessage.InvalidId.String())
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

// noArgsConfig bundles parameters for no-args handler factories.
type noArgsConfig struct {
	GetService  func() any
	ServiceName string
	ErrCode     string
}

// handleNoArgs creates a handler: requireService → fn(ctx) → respondSuccess
func handleNoArgs(
	cfg noArgsConfig,
	fn func(ctx context.Context) (any, error),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if !requireService(w, cfg.GetService(), cfg.ServiceName) {
			return
		}

		result, err := fn(r.Context())
		if err != nil {
			respondError(w, wordpress.HttpStatusServerError, cfg.ErrCode, err.Error())
			return
		}

		respondSuccess(w, result)
	}
}

// twoIDConfig bundles parameters for two-ID handler factories.
type twoIDConfig struct {
	GetService  func() any
	ServiceName string
	Param1Name  string
	Param2Name  string
	ErrCode     string
}

// handleTwoIDs creates a handler: requireService → parseID(param1) → parseID(param2) → fn(ctx, id1, id2) → respondSuccess
func handleTwoIDs(
	cfg twoIDConfig,
	fn func(ctx context.Context, id1, id2 int64) (any, error),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if !requireService(w, cfg.GetService(), cfg.ServiceName) {
			return
		}

		id1, ok := parseID(w, r, cfg.Param1Name)
		if !ok {
			return
		}

		id2, ok := parseID(w, r, cfg.Param2Name)
		if !ok {
			return
		}

		result, err := fn(r.Context(), id1, id2)
		if err != nil {
			respondError(w, wordpress.HttpStatusServerError, cfg.ErrCode, err.Error())
			return
		}

		respondSuccess(w, result)
	}
}
