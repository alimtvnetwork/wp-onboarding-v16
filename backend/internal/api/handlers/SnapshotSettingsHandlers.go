// Package handlers - Snapshot settings and provider HTTP handlers
package handlers

import (
	"context"
	"encoding/json"
	"net/http"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// GetRemoteSnapshotSettings fetches snapshot settings from a remote WordPress site.
var GetRemoteSnapshotSettings = handleSiteActionByID("E3025",
	func(ctx context.Context, siteId int64) (any, *apperror.AppError) {
		return Services.SiteService.GetRemoteSnapshotSettings(ctx, siteId)
	},
)

// UpdateRemoteSnapshotSettings updates snapshot settings on a remote WordPress site.
func UpdateRemoteSnapshotSettings(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, ok := parseSiteIdOrFail(w, r)
	if !ok {
		return
	}

	var settings wordpress.SnapshotSettings
	decodeErr := json.NewDecoder(r.Body).Decode(&settings)
	if decodeErr != nil {
		respondBadRequest(w, "E1001", "Invalid request body")
		return
	}

	result, appErr := Services.SiteService.UpdateRemoteSnapshotSettings(r.Context(), siteId, settings)
	if appErr != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3026", appErr.Error())
		return
	}

	respondSuccess(w, result)
}

// GetRemoteSnapshotProviders returns available snapshot providers on a remote WordPress site.
var GetRemoteSnapshotProviders = handleSiteActionByID("E3027",
	func(ctx context.Context, siteId int64) (any, *apperror.AppError) {
		return Services.SiteService.GetRemoteSnapshotProviders(ctx, siteId)
	},
)

// GetRemoteAvailableTables returns the list of database tables available for snapshotting.
var GetRemoteAvailableTables = handleSiteActionByID("E3029",
	func(ctx context.Context, siteId int64) (any, *apperror.AppError) {
		return Services.SiteService.GetRemoteAvailableTables(ctx, siteId)
	},
)
