// Package handlers — bulk publish HTTP request handler
package handlers

import (
	"net/http"

	"wp-plugin-publish/internal/enums/publishtype"
	"wp-plugin-publish/internal/services/publish"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// BulkPublishInput represents the request body for bulk publishing.
type BulkPublishInput struct {
	PluginIds    []int64  `json:"pluginIds"`
	SiteIds      []int64  `json:"siteIds"`
	Mode         string   `json:"mode"`
	CreateBackup bool     `json:"createBackup"`
	KeepZipFiles bool     `json:"keepZipFiles"`
}

// BulkPublishPlugin publishes multiple plugins to multiple sites sequentially.
func BulkPublishPlugin(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.PublishService, "Publish service") {
		return
	}

	var input BulkPublishInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	validationErr := validateBulkPublishInput(input)
	if validationErr != nil {
		respondBadRequest(w, apperror.ErrValidation, validationErr.Error())

		return
	}

	mode := resolveBulkPublishMode(input.Mode)

	serviceInput := publish.BulkPublishInput{
		PluginIds: input.PluginIds,
		SiteIds:   input.SiteIds,
		Options: publish.PublishOptions{
			Mode:                mode,
			IsCreateBackup:      input.CreateBackup,
			IsKeepZipFiles:      input.KeepZipFiles,
			IsRollbackOnFailure: true,
		},
	}

	result, appErr := Services.PublishService.BulkPublish(r.Context(), serviceInput)
	if appErr != nil {
		respondError(w, wordpress.HttpStatusServerError, "E5010", appErr.Error())

		return
	}

	respondSuccess(w, result)
}

// validateBulkPublishInput checks required fields for bulk publish.
func validateBulkPublishInput(input BulkPublishInput) *apperror.AppError {
	isPluginsMissing := len(input.PluginIds) == 0
	isSitesMissing := len(input.SiteIds) == 0

	if isPluginsMissing {
		return apperror.New(apperror.ErrValidation, "pluginIds is required and must contain at least one ID")
	}

	if isSitesMissing {
		return apperror.New(apperror.ErrValidation, "siteIds is required and must contain at least one ID")
	}

	return nil
}

// resolveBulkPublishMode parses the mode string or defaults to Full.
func resolveBulkPublishMode(modeStr string) publishtype.Variant {
	mode, parseErr := publishtype.Parse(modeStr)
	isInvalidMode := parseErr != nil || mode.IsUndefined()

	if isInvalidMode {
		return publishtype.Full
	}

	return mode
}
