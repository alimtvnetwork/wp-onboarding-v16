// Package handlers - Publish History API handlers
package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"

	"github.com/gorilla/mux"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// ListPublishHistory returns paginated publish history
func ListPublishHistory(w http.ResponseWriter, r *http.Request) {
	isMissing := Services == nil || Services.PublishHistoryService == nil
	if isMissing {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			"Publish history service not available",
		)

		return
	}

	limit, _ := strconv.Atoi(r.URL.Query().Get("limit"))
	isInvalidLimit := limit <= 0 || limit > 100
	if isInvalidLimit {
		limit = 25
	}

	parsedOffset, offsetErr := strconv.Atoi(r.URL.Query().Get("offset"))
	offset := 0
	if offsetErr == nil {
		offset = parsedOffset
	}

	filters := models.PublishHistoryFilters{
		Status: r.URL.Query().Get("status"),
		Search: r.URL.Query().Get("search"),
	}

	pid := r.URL.Query().Get("pluginId")
	hasPluginFilter := pid != ""

	if hasPluginFilter {
		parsedPid, pidErr := strconv.ParseInt(pid, 10, 64)
		if pidErr == nil {
			filters.PluginId = parsedPid
		}
	}

	sid := r.URL.Query().Get("siteId")
	hasSiteFilter := sid != ""

	if hasSiteFilter {
		parsedSid, sidErr := strconv.ParseInt(sid, 10, 64)
		if sidErr == nil {
			filters.SiteId = parsedSid
		}
	}

	listResult, err := Services.PublishHistoryService.List(limit, offset, filters)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E8001",
			err.Error(),
		)

		return
	}

	respondSuccess(w, PaginatedEntries{
		Entries: listResult.Items,
		Total:   listResult.Total,
		Limit:   limit,
		Offset:  offset,
	})
}

// GetPublishHistoryById returns a single publish history entry
func GetPublishHistoryById(w http.ResponseWriter, r *http.Request) {
	isMissing := Services == nil || Services.PublishHistoryService == nil
	if isMissing {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			"Publish history service not available",
		)

		return
	}

	id, err := strconv.ParseInt(mux.Vars(r)["id"], 10, 64)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Invalid ID",
		)

		return
	}

	entry, err := Services.PublishHistoryService.GetById(id)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusNotFound,
			"E8002",
			err.Error(),
		)

		return
	}

	respondSuccess(w, entry)
}

// GetPublishHistoryStats returns aggregate statistics
func GetPublishHistoryStats(w http.ResponseWriter, r *http.Request) {
	isMissing := Services == nil || Services.PublishHistoryService == nil
	if isMissing {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			"Publish history service not available",
		)

		return
	}

	stats, err := Services.PublishHistoryService.GetStats()
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E8003",
			err.Error(),
		)

		return
	}

	respondSuccess(w, stats)
}

// DeletePublishHistoryEntry removes a single entry
func DeletePublishHistoryEntry(w http.ResponseWriter, r *http.Request) {
	isMissing := Services == nil || Services.PublishHistoryService == nil
	if isMissing {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			"Publish history service not available",
		)

		return
	}

	id, err := strconv.ParseInt(mux.Vars(r)["id"], 10, 64)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Invalid ID",
		)

		return
	}

	deleteErr := Services.PublishHistoryService.Delete(id)
	if deleteErr != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E8004",
			deleteErr.Error(),
		)

		return
	}

	respondSuccess(w, ActionResponse{IsDeleted: true})
}

// ClearPublishHistory removes all entries
func ClearPublishHistory(w http.ResponseWriter, r *http.Request) {
	isMissing := Services == nil || Services.PublishHistoryService == nil
	if isMissing {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			"Publish history service not available",
		)

		return
	}

	// Require confirmation in body
	var input struct {
		Confirm bool `json:"confirm"` // external key (frontend request body)
	}
	decodeErr := json.NewDecoder(r.Body).Decode(&input)

	if decodeErr != nil {
		respondError(w, wordpress.HttpStatusBadRequest, apperror.ErrValidation, "Invalid request body: "+decodeErr.Error())

		return
	}

	isConfirmed := input.Confirm
	if !isConfirmed {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Confirmation required",
		)

		return
	}

	count, err := Services.PublishHistoryService.Clear()
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E8005",
			err.Error(),
		)

		return
	}

	response := ActionResponse{
		IsCleared: true,
		Count:     int(count),
	}
	respondSuccess(w, response)
}
