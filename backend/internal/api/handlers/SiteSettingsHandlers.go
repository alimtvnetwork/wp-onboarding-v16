// Package handlers — Remote site settings and health summary HTTP handlers
package handlers

import (
	"context"
	"encoding/json"
	"net/http"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// GetRemoteSiteSettings returns site settings from a remote WordPress site
var GetRemoteSiteSettings = handleSiteActionById(
	apperror.ErrWPConnection,
	func(ctx context.Context, siteId int64) (*wordpress.SiteSettingsData, *apperror.AppError) {
		return Services.SiteService.GetRemoteSiteSettings(ctx, siteId)
	},
)

// UpdateRemoteSiteSettings updates site settings on a remote WordPress site
func UpdateRemoteSiteSettings(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	var body map[string]any // justified: dynamic PHP settings input

	decodeErr := json.NewDecoder(r.Body).Decode(&body)
	if decodeErr != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid request body")
		return
	}

	result, appErr := Services.SiteService.UpdateRemoteSiteSettings(r.Context(), siteId, body)
	if appErr != nil {
		respondErrorWithDelegated(w, resolveHttpStatus(appErr, wordpress.HttpStatusServerError), apperror.ErrWPConnection, appErr.Error(), appErr)
		return
	}

	respondSuccess(w, result)
}

// GetRemoteSiteHealthSummary returns health summary from a remote WordPress site
var GetRemoteSiteHealthSummary = handleSiteActionById(
	apperror.ErrWPConnection,
	func(ctx context.Context, siteId int64) (*wordpress.HealthSummaryData, *apperror.AppError) {
		return Services.SiteService.GetRemoteSiteHealthSummary(ctx, siteId)
	},
)
