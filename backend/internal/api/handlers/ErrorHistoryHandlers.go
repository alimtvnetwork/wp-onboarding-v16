// Package handlers - Error History API handlers
package handlers

import (
	"net/http"
	"strconv"

	"github.com/gorilla/mux"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
)

// SaveErrorHistory persists a new error to history
func SaveErrorHistory(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.ErrorHistoryService, "ErrorHistory service") {
		return
	}

	var input models.ErrorHistoryInput
	if !decodeJSON(w, r, &input) {
		return
	}

	if msg := validateSaveErrorInput(input); msg != "" {
		respondBadRequest(w, apperror.ErrConfigParse, msg)

		return
	}

	saveErrorHistoryOrFail(w, input)
}

// saveErrorHistoryOrFail persists the error input and writes the response.
func saveErrorHistoryOrFail(w http.ResponseWriter, input models.ErrorHistoryInput) {
	result, err := Services.ErrorHistoryService.Save(input)
	if err != nil {
		respondServerError(w, apperror.ErrNotImplemented, err.Error())

		return
	}

	respondCreated(w, result)
}

// validateSaveErrorInput returns an error message if any required field is missing.
func validateSaveErrorInput(input models.ErrorHistoryInput) string {
	if input.Code == "" {
		return "Error code is required"
	}

	if input.Message == "" {
		return "Error message is required"
	}

	return ""
}

// ListErrorHistory returns paginated error history
func ListErrorHistory(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.ErrorHistoryService, "ErrorHistory service") {
		return
	}

	limit, offset := parsePagination(r)
	filters := parseErrorHistoryFilters(r)

	listErrorHistoryOrFail(w, limit, offset, filters)
}

// listErrorHistoryOrFail queries error history and writes the paginated response.
func listErrorHistoryOrFail(w http.ResponseWriter, limit, offset int, filters models.ErrorHistoryFilters) {
	listResult, err := Services.ErrorHistoryService.List(limit, offset, filters)
	if err != nil {
		respondServerError(w, apperror.ErrNotImplemented, err.Error())

		return
	}

	response := PaginatedErrors{
		Errors: listResult.Items,
		Total:  listResult.Total,
		Limit:  limit,
		Offset: offset,
	}
	respondSuccess(w, response)
}

// parsePagination extracts limit and offset from query parameters.
func parsePagination(r *http.Request) (int, int) {
	limit, _ := strconv.Atoi(r.URL.Query().Get("limit"))
	offset, _ := strconv.Atoi(r.URL.Query().Get("offset"))

	return limit, offset
}

// parseErrorHistoryFilters extracts filter values from query parameters.
func parseErrorHistoryFilters(r *http.Request) models.ErrorHistoryFilters {
	return models.ErrorHistoryFilters{
		Code:      r.URL.Query().Get("code"),
		Level:     r.URL.Query().Get("level"),
		StartDate: r.URL.Query().Get("startDate"),
		EndDate:   r.URL.Query().Get("endDate"),
		Search:    r.URL.Query().Get("search"),
	}
}

// GetErrorHistoryByID returns a single error by database ID
func GetErrorHistoryByID(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.ErrorHistoryService, "ErrorHistory service") {
		return
	}

	idStr := mux.Vars(r)["id"]

	id, err := strconv.ParseInt(idStr, 10, 64)
	if err != nil {
		getErrorHistoryByErrorId(w, idStr)

		return
	}

	getErrorHistoryByDatabaseId(w, id)
}

// getErrorHistoryByErrorId looks up an error by its string error ID.
func getErrorHistoryByErrorId(w http.ResponseWriter, errorId string) {
	errHistory, err := Services.ErrorHistoryService.GetByErrorId(errorId)
	if err != nil {
		respondNotFound(w, apperror.ErrNotFound, err.Error())

		return
	}

	respondSuccess(w, errHistory)
}

// getErrorHistoryByDatabaseId looks up an error by its numeric database ID.
func getErrorHistoryByDatabaseId(w http.ResponseWriter, id int64) {
	errHistory, err := Services.ErrorHistoryService.GetById(id)
	if err != nil {
		respondNotFound(w, apperror.ErrNotFound, err.Error())

		return
	}

	respondSuccess(w, errHistory)
}
