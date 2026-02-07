// Package handlers - Error History API handlers
package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"

	"github.com/gorilla/mux"
	"wp-plugin-publish/internal/models"
)

// SaveErrorHistory persists a new error to history
func SaveErrorHistory(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.ErrorHistoryService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Error history service not available")
		return
	}

	var input models.ErrorHistoryInput
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
		return
	}

	if input.Code == "" {
		respondError(w, http.StatusBadRequest, "E1002", "Error code is required")
		return
	}
	if input.Message == "" {
		respondError(w, http.StatusBadRequest, "E1002", "Error message is required")
		return
	}

	result, err := Services.ErrorHistoryService.Save(input)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E9004", err.Error())
		return
	}

	respondCreated(w, result)
}

// ListErrorHistory returns paginated error history
func ListErrorHistory(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.ErrorHistoryService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Error history service not available")
		return
	}

	// Parse query parameters
	limit, _ := strconv.Atoi(r.URL.Query().Get("limit"))
	offset, _ := strconv.Atoi(r.URL.Query().Get("offset"))

	filters := models.ErrorHistoryFilters{
		Code:      r.URL.Query().Get("code"),
		Level:     r.URL.Query().Get("level"),
		StartDate: r.URL.Query().Get("startDate"),
		EndDate:   r.URL.Query().Get("endDate"),
		Search:    r.URL.Query().Get("search"),
	}

	errors, total, err := Services.ErrorHistoryService.List(limit, offset, filters)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E9004", err.Error())
		return
	}

	respondSuccess(w, map[string]interface{}{
		"errors": errors,
		"total":  total,
		"limit":  limit,
		"offset": offset,
	})
}

// GetErrorHistoryByID returns a single error by database ID
func GetErrorHistoryByID(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.ErrorHistoryService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Error history service not available")
		return
	}

	vars := mux.Vars(r)
	idStr := vars["id"]

	// Try to parse as int64 first (database ID)
	id, err := strconv.ParseInt(idStr, 10, 64)
	if err != nil {
		// Try as errorId string
		errHistory, err := Services.ErrorHistoryService.GetByErrorID(idStr)
		if err != nil {
			respondError(w, http.StatusNotFound, "E9001", err.Error())
			return
		}
		respondSuccess(w, errHistory)
		return
	}

	errHistory, err := Services.ErrorHistoryService.GetByID(id)
	if err != nil {
		respondError(w, http.StatusNotFound, "E9001", err.Error())
		return
	}

	respondSuccess(w, errHistory)
}

// DeleteErrorHistory removes an error from history
func DeleteErrorHistory(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.ErrorHistoryService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Error history service not available")
		return
	}

	vars := mux.Vars(r)
	id, err := strconv.ParseInt(vars["id"], 10, 64)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid error ID")
		return
	}

	if err := Services.ErrorHistoryService.Delete(id); err != nil {
		respondError(w, http.StatusNotFound, "E9001", err.Error())
		return
	}

	respondSuccess(w, map[string]interface{}{"deleted": true, "id": id})
}

// ClearErrorHistory removes all error history
func ClearErrorHistory(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.ErrorHistoryService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Error history service not available")
		return
	}

	deleted, err := Services.ErrorHistoryService.Clear()
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E9004", err.Error())
		return
	}

	respondSuccess(w, map[string]interface{}{"cleared": true, "deleted": deleted})
}

// BulkExportErrorHistory generates a combined markdown report
func BulkExportErrorHistory(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.ErrorHistoryService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Error history service not available")
		return
	}

	var input struct {
		IDs []int64 `json:"ids"`
	}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
		return
	}

	if len(input.IDs) == 0 {
		respondError(w, http.StatusBadRequest, "E1002", "At least one error ID is required")
		return
	}

	report, err := Services.ErrorHistoryService.BulkExport(input.IDs)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E9004", err.Error())
		return
	}

	respondSuccess(w, map[string]interface{}{
		"report": report,
		"count":  len(input.IDs),
	})
}

// GetErrorHistoryStats returns error statistics
func GetErrorHistoryStats(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.ErrorHistoryService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Error history service not available")
		return
	}

	stats, err := Services.ErrorHistoryService.GetStats()
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E9004", err.Error())
		return
	}

	respondSuccess(w, stats)
}
