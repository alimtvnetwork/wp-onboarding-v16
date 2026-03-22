// Package handlers provides site bootstrap HTTP request handlers
package handlers

import (
	"encoding/json"
	"errors"
	"net/http"
	"sync"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
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
	decodeErr := json.NewDecoder(r.Body).Decode(&input)

	if decodeErr != nil {
		respondError(w, wordpress.HttpStatusBadRequest, apperror.ErrValidation, "Invalid request body: "+decodeErr.Error())

		return
	}

	result, err := Services.SiteService.BootstrapUploader(r.Context(), id, input.UploaderPath)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, apperror.ErrDatabaseBootstrap, err.Error())

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
		respondError(w, wordpress.HttpStatusBadRequest, apperror.ErrConfigParse, "At least one site ID is required")

		return
	}

	zipPath, zipErr := Services.SiteService.CreateUploaderZipOnce(input.UploaderPath)
	if zipErr != nil {
		respondError(w, wordpress.HttpStatusServerError, apperror.ErrFSZip, zipErr.Error())

		return
	}
	defer pathutil.RemoveFileUnchecked(zipPath)

	results := bootstrapSitesParallel(r, input.SiteIds, zipPath)

	respondSuccess(w, BulkBootstrapResponse{Results: results})
}

// bootstrapSitesParallel deploys to all sites concurrently using a pre-built ZIP.
func bootstrapSitesParallel(r *http.Request, siteIds []int64, zipPath string) []BulkBootstrapSiteResult {
	results := make([]BulkBootstrapSiteResult, len(siteIds))
	var wg sync.WaitGroup

	for i, siteId := range siteIds {
		wg.Add(1)

		go func(idx int, id int64) {
			defer wg.Done()
			results[idx] = bootstrapSingleSiteWithZip(r, id, zipPath)
		}(i, siteId)
	}

	wg.Wait()

	return results
}

// bootstrapSingleSiteWithZip deploys using a pre-built ZIP and cross-upload strategy.
func bootstrapSingleSiteWithZip(r *http.Request, siteId int64, zipPath string) BulkBootstrapSiteResult {
	result, err := Services.SiteService.BootstrapUploaderWithZip(r.Context(), siteId, zipPath)
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
// Extracts remote response body from the ApiError cause chain for delegated error diagnostics.
func buildBootstrapFailure(r *http.Request, siteId int64, err error) BulkBootstrapSiteResult {
	siteResult, siteErr := Services.SiteService.GetById(r.Context(), siteId)

	siteName := ""
	if siteErr == nil && siteResult != nil {
		siteName = siteResult.Name
	}

	failResult := BulkBootstrapSiteResult{
		SiteId:    siteId,
		SiteName:  siteName,
		IsSuccess: false,
		Message:   "Deployment failed",
		Error:     err.Error(),
	}

	// Extract remote response body from ApiError in the cause chain
	var apiErr *wordpress.ApiError
	if errors.As(err, &apiErr) {
		failResult.RemoteResponseBody = apiErr.ResponseBody
		failResult.RemoteStatusCode = apiErr.StatusCode
		failResult.RemoteUrl = apiErr.Url
	} else {
		// Try via AppError diagnostic
		var appErr *apperror.AppError
		if errors.As(err, &appErr) && appErr.Diagnostic.StatusCode > 0 {
			failResult.RemoteStatusCode = appErr.Diagnostic.StatusCode
			failResult.RemoteUrl = appErr.Diagnostic.Url
		}
	}

	return failResult
}
