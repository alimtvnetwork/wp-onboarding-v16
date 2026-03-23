// Package handlers provides remote user management HTTP handlers
package handlers

import (
	"context"
	"encoding/json"
	"net/http"

	"github.com/gorilla/mux"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// ListRemoteUsers returns all users from a remote WordPress site
var ListRemoteUsers = handleSiteActionByIdWithQuery(
	apperror.ErrWPConnection,
	func(ctx context.Context, siteId int64, query string) (any, *apperror.AppError) {
		return Services.SiteService.ListRemoteUsers(ctx, siteId, query)
	},
)

// GetRemoteUser returns a single user from a remote WordPress site
func GetRemoteUser(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, err := getIdParam(r, "id")
	if err != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid site ID")
		return
	}

	userId := mux.Vars(r)["userId"]
	isUserIdMissing := userId == ""

	if isUserIdMissing {
		respondBadRequest(w, apperror.ErrConfigParse, "User ID is required")
		return
	}

	result, appErr := Services.SiteService.GetRemoteUser(r.Context(), siteId, userId)
	if appErr != nil {
		respondErrorWithDelegated(w, resolveHttpStatus(appErr, wordpress.HttpStatusServerError), apperror.ErrWPConnection, appErr.Error(), appErr)
		return
	}

	respondSuccess(w, result)
}

// CreateRemoteUser creates a new user on a remote WordPress site
func CreateRemoteUser(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, err := getIdParam(r, "id")
	if err != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid site ID")
		return
	}

	var input wordpress.UserCreateRequest

	decodeErr := json.NewDecoder(r.Body).Decode(&input)
	if decodeErr != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid request body")
		return
	}

	result, appErr := Services.SiteService.CreateRemoteUser(r.Context(), siteId, input)
	if appErr != nil {
		respondErrorWithDelegated(w, resolveHttpStatus(appErr, wordpress.HttpStatusServerError), apperror.ErrWPConnection, appErr.Error(), appErr)
		return
	}

	respondSuccess(w, result)
}

// UpdateRemoteUser updates a user on a remote WordPress site
func UpdateRemoteUser(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, err := getIdParam(r, "id")
	if err != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid site ID")
		return
	}

	userId := mux.Vars(r)["userId"]
	isUserIdMissing := userId == ""

	if isUserIdMissing {
		respondBadRequest(w, apperror.ErrConfigParse, "User ID is required")
		return
	}

	var input wordpress.UserUpdateRequest

	decodeErr := json.NewDecoder(r.Body).Decode(&input)
	if decodeErr != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid request body")
		return
	}

	result, appErr := Services.SiteService.UpdateRemoteUser(r.Context(), siteId, userId, input)
	if appErr != nil {
		respondErrorWithDelegated(w, resolveHttpStatus(appErr, wordpress.HttpStatusServerError), apperror.ErrWPConnection, appErr.Error(), appErr)
		return
	}

	respondSuccess(w, result)
}

// DeleteRemoteUser deletes a user on a remote WordPress site
func DeleteRemoteUser(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, err := getIdParam(r, "id")
	if err != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid site ID")
		return
	}

	userId := mux.Vars(r)["userId"]
	isUserIdMissing := userId == ""

	if isUserIdMissing {
		respondBadRequest(w, apperror.ErrConfigParse, "User ID is required")
		return
	}

	reassign := r.URL.Query().Get("reassign")

	result, appErr := Services.SiteService.DeleteRemoteUser(r.Context(), siteId, userId, reassign)
	if appErr != nil {
		respondErrorWithDelegated(w, resolveHttpStatus(appErr, wordpress.HttpStatusServerError), apperror.ErrWPConnection, appErr.Error(), appErr)
		return
	}

	respondSuccess(w, result)
}

// CreateRemoteAppPassword creates an app password for a user on a remote site
func CreateRemoteAppPassword(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, err := getIdParam(r, "id")
	if err != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid site ID")
		return
	}

	var input wordpress.AppPasswordCreateRequest

	decodeErr := json.NewDecoder(r.Body).Decode(&input)
	if decodeErr != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid request body")
		return
	}

	result, appErr := Services.SiteService.CreateRemoteAppPassword(r.Context(), siteId, input)
	if appErr != nil {
		respondErrorWithDelegated(w, resolveHttpStatus(appErr, wordpress.HttpStatusServerError), apperror.ErrWPConnection, appErr.Error(), appErr)
		return
	}

	respondSuccess(w, result)
}

// RevokeRemoteAppPassword revokes an app password on a remote site
func RevokeRemoteAppPassword(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, err := getIdParam(r, "id")
	if err != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid site ID")
		return
	}

	var input wordpress.AppPasswordRevokeRequest

	decodeErr := json.NewDecoder(r.Body).Decode(&input)
	if decodeErr != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid request body")
		return
	}

	result, appErr := Services.SiteService.RevokeRemoteAppPassword(r.Context(), siteId, input)
	if appErr != nil {
		respondErrorWithDelegated(w, resolveHttpStatus(appErr, wordpress.HttpStatusServerError), apperror.ErrWPConnection, appErr.Error(), appErr)
		return
	}

	respondSuccess(w, result)
}

// ExportRemoteUsersCsv exports users as CSV from a remote WordPress site
var ExportRemoteUsersCsv = handleSiteActionByIdWithQuery(
	apperror.ErrWPConnection,
	func(ctx context.Context, siteId int64, query string) (any, *apperror.AppError) {
		return Services.SiteService.ExportRemoteUsersCsv(ctx, siteId, query)
	},
)

// ExportRemoteUsersSqlite exports users as SQLite ZIP from a remote WordPress site
var ExportRemoteUsersSqlite = handleSiteActionById(
	apperror.ErrWPConnection,
	func(ctx context.Context, siteId int64) (any, *apperror.AppError) {
		return Services.SiteService.ExportRemoteUsersSqlite(ctx, siteId)
	},
)
