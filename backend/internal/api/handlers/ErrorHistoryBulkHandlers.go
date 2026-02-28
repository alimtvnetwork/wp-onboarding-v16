// Package handlers - Error History bulk and stats handlers
package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"

	"wp-plugin-publish/pkg/apperror"
)

// DeleteErrorHistory removes an error from history
func DeleteErrorHistory(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.ErrorHistoryService, "ErrorHistory service") {
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid ID parameter")

		return
	}

	if err := Services.ErrorHistoryService.Delete(id); err != nil {
		respondNotFound(w, apperror.ErrNotFound, err.Error())

		return
	}

	respondSuccess(w, ActionResponse{
		IsDeleted: true,
		ID:        strconv.FormatInt(id, 10),
	})
}

// ClearErrorHistory removes all error history
func ClearErrorHistory(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.ErrorHistoryService, "ErrorHistory service") {
		return
	}

	deleted, err := Services.ErrorHistoryService.Clear()
	if err != nil {
		respondServerError(w, apperror.ErrNotImplemented, err.Error())

		return
	}

	respondSuccess(w, ActionResponse{
		IsCleared: true,
		Count:     int(deleted),
	})
}

// bulkExportInput is the JSON body for BulkExportErrorHistory.
type bulkExportInput struct {
	IDs []int64 `json:"ids"`
}

// BulkExportErrorHistory generates a combined markdown report
func BulkExportErrorHistory(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.ErrorHistoryService, "ErrorHistory service") {
		return
	}

	input, ok := parseBulkExportInput(w, r)
	if !ok {
		return
	}

	report, err := Services.ErrorHistoryService.BulkExport(input.IDs)
	if err != nil {
		respondServerError(w, apperror.ErrNotImplemented, err.Error())

		return
	}

	respondSuccess(w, ErrorReportResponse{
		Report: report,
		Count:  len(input.IDs),
	})
}

// parseBulkExportInput decodes and validates the bulk export JSON body.
func parseBulkExportInput(w http.ResponseWriter, r *http.Request) (*bulkExportInput, bool) {
	var input bulkExportInput
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondBadRequest(w, apperror.ErrConfigLoad, "Invalid request body")

		return nil, false
	}

	if len(input.IDs) == 0 {
		respondBadRequest(w, apperror.ErrConfigParse, "At least one error ID is required")

		return nil, false
	}

	return &input, true
}

// GetErrorHistoryStats returns error statistics
func GetErrorHistoryStats(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.ErrorHistoryService, "ErrorHistory service") {
		return
	}

	stats, err := Services.ErrorHistoryService.GetStats()
	if err != nil {
		respondServerError(w, apperror.ErrNotImplemented, err.Error())

		return
	}

	respondSuccess(w, stats)
}
