// Package handlers - Site Health Monitor API handlers
package handlers

import (
	"context"
	"net/http"
	"strconv"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// CheckSiteHealth performs a health check on a single site
var CheckSiteHealth = handleSiteActionById("E4001",
	func(ctx context.Context, siteId int64) (any, *apperror.AppError) {
		return Services.SiteHealthService.CheckSite(ctx, siteId)
	},
)

// CheckAllSitesHealth performs health checks on all sites
func CheckAllSitesHealth(w http.ResponseWriter, r *http.Request) {
	isMissing :=
		Services == nil ||
		Services.SiteHealthService == nil
	if isMissing {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			"Site health service not available",
		)

		return
	}

	checks, appErr := Services.SiteHealthService.CheckAllSites(r.Context())
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E4002",
			appErr.Error(),
		)

		return
	}

	respondSuccess(w, checks)
}

// GetSiteHealthHistory returns health check history
func GetSiteHealthHistory(w http.ResponseWriter, r *http.Request) {
	isMissing :=
		Services == nil ||
		Services.SiteHealthService == nil
	if isMissing {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			"Site health service not available",
		)

		return
	}

	siteId, _ := strconv.ParseInt(r.URL.Query().Get("siteId"), 10, 64)
	limit, _ := strconv.Atoi(r.URL.Query().Get("limit"))

	history, appErr := Services.SiteHealthService.GetHistory(siteId, limit)
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E4003",
			appErr.Error(),
		)

		return
	}

	respondSuccess(w, history)
}

// GetSiteHealthSummaries returns health summaries for all sites
func GetSiteHealthSummaries(w http.ResponseWriter, r *http.Request) {
	isMissing :=
		Services == nil ||
		Services.SiteHealthService == nil
	if isMissing {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			"Site health service not available",
		)

		return
	}

	summaries, appErr := Services.SiteHealthService.GetSummaries(r.Context())
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E4004",
			appErr.Error(),
		)

		return
	}

	respondSuccess(w, summaries)
}

// GetSiteHealthStats returns overall health statistics
func GetSiteHealthStats(w http.ResponseWriter, r *http.Request) {
	isMissing :=
		Services == nil ||
		Services.SiteHealthService == nil
	if isMissing {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			"Site health service not available",
		)

		return
	}

	stats, appErr := Services.SiteHealthService.GetStats(r.Context())
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E4005",
			appErr.Error(),
		)

		return
	}

	respondSuccess(w, stats)
}

// ClearSiteHealthHistory removes old health check records
func ClearSiteHealthHistory(w http.ResponseWriter, r *http.Request) {
	isMissing :=
		Services == nil ||
		Services.SiteHealthService == nil
	if isMissing {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			"Site health service not available",
		)

		return
	}

	days, _ := strconv.Atoi(r.URL.Query().Get("days"))

	deleted, appErr := Services.SiteHealthService.ClearHistory(days)
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E4005",
			appErr.Error(),
		)

		return
	}

	response := ActionResponse{
		IsDeleted: true,
		Count:     int(deleted),
	}
	respondSuccess(w, response)
}
