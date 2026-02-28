// Package handlers - Generic handler factories to eliminate CRUD boilerplate
//
// All factories use lazy service resolution (func() any) because the global
// Services registry is nil at package init time and only populated during server startup.
package handlers

import (
	"context"
	"net/http"

	responsemessage "wp-plugin-publish/internal/enums/responsemessagetype"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// --- Generic Handler Factories ---

// handlerIdConfig bundles parameters for single-ID handler factories.
type handlerIdConfig struct {
	GetService  func() any
	ServiceName string
	ParamName   string
	ErrCode     apperror.ErrorCode
}

// handleActionById creates a handler: isServiceMissing → parseId → fn(ctx, id) → respondSuccess
func handleActionById(
	cfg handlerIdConfig,
	fn func(ctx context.Context, id int64) (any, *apperror.AppError),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if isServiceMissing(w, cfg.GetService(), cfg.ServiceName) {
			return
		}

		id, ok := parseId(w, r, cfg.ParamName)
		if !ok {
			return
		}

		result, appErr := fn(r.Context(), id)
		if appErr != nil {
			respondError(
				w,
				wordpress.HttpStatusServerError,
				cfg.ErrCode,
				appErr.Error(),
			)

			return
		}

		respondSuccess(w, result)
	}
}

// handleDeleteById creates a handler: isServiceMissing → parseId → fn(ctx, id) → respondDeleted
func handleDeleteById(
	cfg handlerIdConfig,
	fn func(ctx context.Context, id int64) *apperror.AppError,
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if isServiceMissing(w, cfg.GetService(), cfg.ServiceName) {
			return
		}

		id, ok := parseId(w, r, cfg.ParamName)
		if !ok {
			return
		}

		appErr := fn(r.Context(), id)
		if appErr != nil {
			respondError(
				w,
				wordpress.HttpStatusBadRequest,
				cfg.ErrCode,
				appErr.Error(),
			)

			return
		}

		respondDeleted(w)
	}
}

// handleListNilSafe creates a handler: nil-safe service check → fn(ctx) → respondSuccess
func handleListNilSafe(
	getService func() any,
	errCode apperror.ErrorCode,
	fn func(ctx context.Context) (any, *apperror.AppError),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if getService() == nil {
			respondSuccess(w, []any{})

			return
		}

		result, appErr := fn(r.Context())
		if appErr != nil {
			respondError(
				w,
				wordpress.HttpStatusServerError,
				errCode,
				appErr.Error(),
			)

			return
		}

		respondSuccess(w, result)
	}
}

// handleSiteActionById creates a handler for site-scoped actions.
func handleSiteActionById(
	errCode apperror.ErrorCode,
	fn func(ctx context.Context, siteId int64) (any, *apperror.AppError),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if isSiteServiceMissing(w) {
			return
		}

		siteId, err := getIdParam(r, "id")
		if err != nil {
			respondError(
				w,
				wordpress.HttpStatusBadRequest,
				apperror.ErrConfigParse,
				responsemessage.InvalidId.String(),
			)

			return
		}

		result, appErr := fn(r.Context(), siteId)
		if appErr != nil {
			respondError(
				w,
				wordpress.HttpStatusServerError,
				errCode,
				appErr.Error(),
			)

			return
		}

		respondSuccess(w, result)
	}
}

// noArgsConfig bundles parameters for no-args handler factories.
type noArgsConfig struct {
	GetService  func() any
	ServiceName string
	ErrCode     apperror.ErrorCode
}

// handleNoArgs creates a handler: isServiceMissing → fn(ctx) → respondSuccess
func handleNoArgs(
	cfg noArgsConfig,
	fn func(ctx context.Context) (any, *apperror.AppError),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if isServiceMissing(w, cfg.GetService(), cfg.ServiceName) {
			return
		}

		result, appErr := fn(r.Context())
		if appErr != nil {
			respondError(
				w,
				wordpress.HttpStatusServerError,
				cfg.ErrCode,
				appErr.Error(),
			)

			return
		}

		respondSuccess(w, result)
	}
}

// twoIdConfig bundles parameters for two-ID handler factories.
type twoIdConfig struct {
	GetService  func() any
	ServiceName string
	Param1Name  string
	Param2Name  string
	ErrCode     apperror.ErrorCode
}

// handleTwoIds creates a handler: isServiceMissing → parseId(param1) → parseId(param2) → fn(ctx, id1, id2) → respondSuccess
func handleTwoIds(
	cfg twoIdConfig,
	fn func(ctx context.Context, id1, id2 int64) (any, *apperror.AppError),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if isServiceMissing(w, cfg.GetService(), cfg.ServiceName) {
			return
		}

		id1, ok := parseId(w, r, cfg.Param1Name)
		if !ok {
			return
		}

		id2, ok := parseId(w, r, cfg.Param2Name)
		if !ok {
			return
		}

		result, appErr := fn(r.Context(), id1, id2)
		if appErr != nil {
			respondError(
				w,
				wordpress.HttpStatusServerError,
				cfg.ErrCode,
				appErr.Error(),
			)

			return
		}

		respondSuccess(w, result)
	}
}
