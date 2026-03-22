// Package handlers — deploy pre-flight check handlers
package handlers

import (
	"net/http"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// deployPreflightInput is the JSON body for DeployPreflight.
type deployPreflightInput struct {
	SiteIds []int64
}

// DeployPreflightSiteResult is the pre-flight result for a single site.
type DeployPreflightSiteResult struct {
	SiteId              int64
	SiteName            string
	SiteUrl             string
	IsReachable         bool
	RiseupAsiaAvailable bool
	RiseupAsiaNamespace string `json:",omitempty"`
	QUploadAvailable    bool
	QUploadNamespace    string `json:",omitempty"`
	Error               string `json:",omitempty"`
}

// DeployPreflightResponse wraps pre-flight results for all sites.
type DeployPreflightResponse struct {
	Results []DeployPreflightSiteResult
}

// DeployPreflight checks endpoint availability on all requested sites before deploy.
func DeployPreflight(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.SiteService, "Site service") {
		return
	}

	var input deployPreflightInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	if len(input.SiteIds) == 0 {
		respondError(w, wordpress.HttpStatusBadRequest, apperror.ErrConfigParse, "At least one site ID is required")

		return
	}

	results, err := Services.SiteService.DeployPreflight(r.Context(), input.SiteIds)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, apperror.ErrInternal, err.Error())

		return
	}

	respondSuccess(w, DeployPreflightResponse{Results: results})
}
