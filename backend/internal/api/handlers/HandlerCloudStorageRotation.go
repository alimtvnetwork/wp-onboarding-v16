// Package handlers — Cloud storage rotation status and manual trigger handlers.
package handlers

import (
	"net/http"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// GetCloudStorageRotationStatus returns the rotation status for a cloud storage account via the remote site.
var GetCloudStorageRotationStatus = handleSiteActionByIdWithQuery[*wordpress.RotationStatus](
	apperror.ErrWPConnection,
	func(ctx context.Context, siteId int64, query string) (*wordpress.RotationStatus, *apperror.AppError) {
		return Services.SiteService.GetCloudStorageRotationStatus(ctx, siteId, query)
	},
)

// TriggerCloudStorageRotation triggers manual rotation for a cloud storage account via the remote site.
var TriggerCloudStorageRotation http.HandlerFunc = func(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, err := getIdParam(r, "id")
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, apperror.ErrConfigParse, "invalid site id")
		return
	}

	var body wordpress.CloudStorageRotateRequest
	if !decodeBody(w, r, &body) {
		return
	}

	result, appErr := Services.SiteService.TriggerCloudStorageRotation(ctx, siteId, body)
	if appErr != nil {
		respondErrorWithDelegated(w, resolveHttpStatus(appErr, wordpress.HttpStatusServerError), apperror.ErrWPConnection, appErr.Error(), appErr)
		return
	}

	respondSuccess(w, result)
}
