// Package handlers provides remote log management HTTP handlers
package handlers

import (
	"context"
	"encoding/json"
	"net/http"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// --- Remote Log Status ---

// GetRemoteLogs returns log file metadata from a remote WordPress site
var GetRemoteLogs = handleSiteActionById(
	apperror.ErrWPConnection,
	func(ctx context.Context, siteId int64) (any, *apperror.AppError) {
		return Services.SiteService.GetRemoteLogsStatus(ctx, siteId)
	},
)

// ClearRemoteLogs initiates Step 1 of the two-step log clearing flow
var ClearRemoteLogs = handleSiteActionById(
	apperror.ErrWPConnection,
	func(ctx context.Context, siteId int64) (any, *apperror.AppError) {
		return Services.SiteService.RequestRemoteLogsClear(ctx, siteId)
	},
)

// ConfirmClearRemoteLogs executes Step 2 of the two-step log clearing flow
func ConfirmClearRemoteLogs(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, err := getIdParam(r, "id")
	if err != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid site ID")
		return
	}

	var body wordpress.ClearTokenRequest

	decodeErr := json.NewDecoder(r.Body).Decode(&body)
	if decodeErr != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid request body")
		return
	}

	isTokenMissing := body.Token == ""
	if isTokenMissing {
		respondBadRequest(w, apperror.ErrConfigParse, "Token is required")
		return
	}

	result, appErr := Services.SiteService.ConfirmRemoteLogsClear(r.Context(), siteId, body.Token)
	if appErr != nil {
		respondError(w, appErr.HttpStatus(), apperror.ErrWPConnection, appErr.Error())
		return
	}

	respondSuccess(w, result)
}

// --- Email Logs ---

// EmailRemoteLogs sends log files as email attachments from a remote WordPress site
func EmailRemoteLogs(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, err := getIdParam(r, "id")
	if err != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid site ID")
		return
	}

	var body wordpress.EmailLogsRequest

	decodeErr := json.NewDecoder(r.Body).Decode(&body)
	if decodeErr != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid request body")
		return
	}

	result, appErr := Services.SiteService.EmailRemoteLogs(r.Context(), siteId, body)
	if appErr != nil {
		respondError(w, appErr.HttpStatus(), apperror.ErrWPConnection, appErr.Error())
		return
	}

	respondSuccess(w, result)
}
