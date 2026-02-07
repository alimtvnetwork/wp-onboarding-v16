// Package handlers - Site Health Monitor API handlers
package handlers

import (
	"context"
	"net/http"
	"strconv"
)

// CheckSiteHealth performs a health check on a single site
var CheckSiteHealth = handleSiteActionByID("E4001",
	func(ctx context.Context, siteID int64) (interface{}, error) {
		return Services.SiteHealthService.CheckSite(ctx, siteID)
	},
)

// CheckAllSitesHealth performs health checks on all sites
func CheckAllSitesHealth(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteHealthService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site health service not available")
		return
	}

	checks, err := Services.SiteHealthService.CheckAllSites(r.Context())
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E4002", err.Error())
		return
	}
	respondSuccess(w, checks)
}

// GetSiteHealthHistory returns health check history
func GetSiteHealthHistory(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteHealthService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site health service not available")
		return
	}

	siteID, _ := strconv.ParseInt(r.URL.Query().Get("siteId"), 10, 64)
	limit, _ := strconv.Atoi(r.URL.Query().Get("limit"))

	history, err := Services.SiteHealthService.GetHistory(siteID, limit)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E4003", err.Error())
		return
	}
	respondSuccess(w, history)
}

// GetSiteHealthSummaries returns health summaries for all sites
func GetSiteHealthSummaries(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteHealthService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site health service not available")
		return
	}

	summaries, err := Services.SiteHealthService.GetSummaries(r.Context())
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E4004", err.Error())
		return
	}
	respondSuccess(w, summaries)
}

// GetSiteHealthStats returns overall health statistics
func GetSiteHealthStats(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteHealthService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site health service not available")
		return
	}

	stats, err := Services.SiteHealthService.GetStats(r.Context())
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E4005", err.Error())
		return
	}
	respondSuccess(w, stats)
}

// ClearSiteHealthHistory removes old health check records
func ClearSiteHealthHistory(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteHealthService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site health service not available")
		return
	}

	days, _ := strconv.Atoi(r.URL.Query().Get("days"))
	deleted, err := Services.SiteHealthService.ClearHistory(days)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E4005", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"deleted": deleted})
}
