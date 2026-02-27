// Package handlers - Error History bulk and stats handlers
package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"

	"wp-plugin-publish/internal/enums/response_message"
	"wp-plugin-publish/internal/wordpress"
)

// DeleteErrorHistory removes an error from history
func DeleteErrorHistory(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.ErrorHistoryService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	if err := Services.ErrorHistoryService.Delete(id); err != nil {
		respondError(
			w,
			wordpress.HttpStatusNotFound,
			"E9001",
			err.Error(),
		)

		return
	}

	response := ActionResponse{
		IsDeleted: true,
		ID:        strconv.FormatInt(id, 10),
	}
	respondSuccess(w, response)
}

// ClearErrorHistory removes all error history
func ClearErrorHistory(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.ErrorHistoryService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	deleted, err := Services.ErrorHistoryService.Clear()
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E9004",
			err.Error(),
		)

		return
	}

	response := ActionResponse{
		IsCleared: true,
		Count:     int(deleted),
	}
	respondSuccess(w, response)
}

// BulkExportErrorHistory generates a combined markdown report
func BulkExportErrorHistory(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.ErrorHistoryService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	var input struct {
		IDs []int64 `json:"ids"` // external key (frontend request body)
	}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1001",
			responsemessage.InvalidRequestBody.String(),
		)

		return
	}

	if len(input.IDs) == 0 {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"At least one error ID is required",
		)

		return
	}

	report, err := Services.ErrorHistoryService.BulkExport(input.IDs)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E9004",
			err.Error(),
		)

		return
	}

	response := ErrorReportResponse{
		Report: report,
		Count:  len(input.IDs),
	}
	respondSuccess(w, response)
}

// GetErrorHistoryStats returns error statistics
func GetErrorHistoryStats(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.ErrorHistoryService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	stats, err := Services.ErrorHistoryService.GetStats()
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E9004",
			err.Error(),
		)

		return
	}

	respondSuccess(w, stats)
}
