// Package handlers - Generic handler factories to eliminate CRUD boilerplate
//
// All factories use lazy service resolution via IsReady func() bool because the global
// Services registry is nil at package init time and only populated during server startup.
//
// Factory functions are generic [T any] — the type parameter T is inferred from the
// callback return type. This eliminates any from all callback signatures while keeping
// the final http.HandlerFunc non-generic (Go requirement for package-level vars).
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
	IsReady     func() bool
	ServiceName string
	ParamName   string
	ErrCode     apperror.ErrorCode
}

// handleActionById creates a handler: isServiceNotReady → parseId → fn(ctx, id) → respondSuccess
func handleActionById[T any](
	cfg handlerIdConfig,
	fn func(ctx context.Context, id int64) (T, *apperror.AppError),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if isServiceNotReady(w, cfg.IsReady, cfg.ServiceName) {
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

// handleDeleteById creates a handler: isServiceNotReady → parseId → fn(ctx, id) → respondDeleted
func handleDeleteById(
	cfg handlerIdConfig,
	fn func(ctx context.Context, id int64) *apperror.AppError,
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if isServiceNotReady(w, cfg.IsReady, cfg.ServiceName) {
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
func handleListNilSafe[T any](
	isReady func() bool,
	errCode apperror.ErrorCode,
	fn func(ctx context.Context) (T, *apperror.AppError),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if !isReady() {
			respondSuccess(w, []struct{}{})

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
func handleSiteActionById[T any](
	errCode apperror.ErrorCode,
	fn func(ctx context.Context, siteId int64) (T, *apperror.AppError),
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
			respondErrorWithDelegated(
				w,
				resolveHttpStatus(appErr, wordpress.HttpStatusServerError),
				errCode,
				appErr.Error(),
				appErr,
			)

			return
		}

		respondSuccess(w, result)
	}
}

// handleSiteActionByIdWithQuery creates a handler that passes query string to the action.
func handleSiteActionByIdWithQuery[T any](
	errCode apperror.ErrorCode,
	fn func(ctx context.Context, siteId int64, query string) (T, *apperror.AppError),
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

		query := r.URL.RawQuery

		result, appErr := fn(r.Context(), siteId, query)
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
	IsReady     func() bool
	ServiceName string
	ErrCode     apperror.ErrorCode
}

// handleNoArgs creates a handler: isServiceNotReady → fn(ctx) → respondSuccess
func handleNoArgs[T any](
	cfg noArgsConfig,
	fn func(ctx context.Context) (T, *apperror.AppError),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if isServiceNotReady(w, cfg.IsReady, cfg.ServiceName) {
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
	IsReady     func() bool
	ServiceName string
	Param1Name  string
	Param2Name  string
	ErrCode     apperror.ErrorCode
}

// handleTwoIds creates a handler: isServiceNotReady → parseId(param1) → parseId(param2) → fn(ctx, id1, id2) → respondSuccess
func handleTwoIds[T any](
	cfg twoIdConfig,
	fn func(ctx context.Context, id1, id2 int64) (T, *apperror.AppError),
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if isServiceNotReady(w, cfg.IsReady, cfg.ServiceName) {
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
