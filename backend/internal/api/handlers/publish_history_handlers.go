// Package handlers - Publish History API handlers
package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"

	"github.com/gorilla/mux"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
)

// ListPublishHistory returns paginated publish history
func ListPublishHistory(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PublishHistoryService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", "Publish history service not available")
		return
	}

	limit, _ := strconv.Atoi(r.URL.Query().Get("limit"))
	if limit <= 0 || limit > 100 {
		limit = 25
	}
	offset, _ := strconv.Atoi(r.URL.Query().Get("offset"))

	filters := models.PublishHistoryFilters{
		Status: r.URL.Query().Get("status"),
		Search: r.URL.Query().Get("search"),
	}
	if pid := r.URL.Query().Get("pluginId"); pid != "" {
		filters.PluginID, _ = strconv.ParseInt(pid, 10, 64)
	}
	if sid := r.URL.Query().Get("siteId"); sid != "" {
		filters.SiteID, _ = strconv.ParseInt(sid, 10, 64)
	}

	entries, total, err := Services.PublishHistoryService.List(limit, offset, filters)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E8001", err.Error())
		return
	}

	respondSuccess(w, PaginatedEntries{
		Entries: entries,
		Total:   total,
		Limit:   limit,
		Offset:  offset,
	})
}

// GetPublishHistoryByID returns a single publish history entry
func GetPublishHistoryByID(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PublishHistoryService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", "Publish history service not available")
		return
	}

	id, err := strconv.ParseInt(mux.Vars(r)["id"], 10, 64)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "Invalid ID")
		return
	}

	entry, err := Services.PublishHistoryService.GetByID(id)
	if err != nil {
		respondError(w, wordpress.HttpStatusNotFound, "E8002", err.Error())
		return
	}
	respondSuccess(w, entry)
}

// GetPublishHistoryStats returns aggregate statistics
func GetPublishHistoryStats(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PublishHistoryService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", "Publish history service not available")
		return
	}

	stats, err := Services.PublishHistoryService.GetStats()
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E8003", err.Error())
		return
	}
	respondSuccess(w, stats)
}

// DeletePublishHistoryEntry removes a single entry
func DeletePublishHistoryEntry(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PublishHistoryService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", "Publish history service not available")
		return
	}

	id, err := strconv.ParseInt(mux.Vars(r)["id"], 10, 64)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "Invalid ID")
		return
	}

	if err := Services.PublishHistoryService.Delete(id); err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E8004", err.Error())
		return
	}
	respondSuccess(w, ActionResponse{Deleted: true})
}

// ClearPublishHistory removes all entries
func ClearPublishHistory(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PublishHistoryService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", "Publish history service not available")
		return
	}

	// Require confirmation in body
	var input struct {
		Confirm bool `json:"confirm"`
	}
	_ = json.NewDecoder(r.Body).Decode(&input)
	if !input.Confirm {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "Confirmation required")
		return
	}

	count, err := Services.PublishHistoryService.Clear()
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E8005", err.Error())
		return
	}
	respondSuccess(w, ActionResponse{Cleared: true, Count: int(count)})
}
