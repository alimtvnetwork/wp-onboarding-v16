// Package handlers provides site bootstrap HTTP request handlers
package handlers

import (
	"encoding/json"
	"net/http"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// bootstrapInput is the optional JSON body for BootstrapUploader.
type bootstrapInput struct {
	UploaderPath string `json:",omitempty"`
}

// BootstrapUploader deploys the Riseup Asia Uploader plugin to a site
func BootstrapUploader(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.SiteService, "Site service") {
		return
	}

	id, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	var input bootstrapInput
	_ = json.NewDecoder(r.Body).Decode(&input)

	result, err := Services.SiteService.BootstrapUploader(r.Context(), id, input.UploaderPath)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			apperror.ErrDatabaseBootstrap,
			err.Error(),
		)

		return
	}

	respondSuccess(w, result)
}

// bulkBootstrapInput is the JSON body for BulkBootstrapUploader.
type bulkBootstrapInput struct {
	SiteIds      []int64
	UploaderPath string `json:",omitempty"`
}

// BulkBootstrapUploader deploys the Riseup Asia Uploader to multiple sites
func BulkBootstrapUploader(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.SiteService, "Site service") {
		return
	}

	var input bulkBootstrapInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	if len(input.SiteIds) == 0 {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			apperror.ErrConfigParse,
			"At least one site ID is required",
		)

		return
	}

	results := make([]BulkBootstrapSiteResult, 0, len(input.SiteIds))

	for _, siteId := range input.SiteIds {
		results = append(results, bootstrapSingleSite(r, siteId, input.UploaderPath))
	}

	respondSuccess(w, BulkBootstrapResponse{Results: results})
}

// bootstrapSingleSite deploys the uploader to one site, returning a result entry.
func bootstrapSingleSite(r *http.Request, siteId int64, uploaderPath string) BulkBootstrapSiteResult {
	result, err := Services.SiteService.BootstrapUploader(r.Context(), siteId, uploaderPath)
	if err != nil {
		return buildBootstrapFailure(r, siteId, err)
	}

	return BulkBootstrapSiteResult{
		SiteId:      result.SiteId,
		SiteName:    result.SiteName,
		IsSuccess:   result.IsSuccess,
		Message:     result.Message,
		IsActivated: result.IsActivated,
	}
}

// buildBootstrapFailure constructs a failure result for a single bootstrap attempt.
func buildBootstrapFailure(r *http.Request, siteId int64, err error) BulkBootstrapSiteResult {
	siteInfo, _ := Services.SiteService.GetById(r.Context(), siteId)

	siteName := ""
	if siteInfo != nil {
		siteName = siteInfo.Name
	}

	return BulkBootstrapSiteResult{
		SiteId:    siteId,
		SiteName:  siteName,
		IsSuccess: false,
		Message:   "Deployment failed",
		Error:     err.Error(),
	}
}
