// Package handlers - Error History API handlers
package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"

	"github.com/gorilla/mux"
	"wp-plugin-publish/internal/enums/response_message"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
)

// SaveErrorHistory persists a new error to history
func SaveErrorHistory(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.ErrorHistoryService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	var input models.ErrorHistoryInput
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1001",
			responsemessage.InvalidRequestBody.String(),
		)

		return
	}

	if input.Code == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Error code is required",
		)

		return
	}

	if input.Message == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Error message is required",
		)

		return
	}

	result, err := Services.ErrorHistoryService.Save(input)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E9004",
			err.Error(),
		)

		return
	}

	respondCreated(w, result)
}

// ListErrorHistory returns paginated error history
func ListErrorHistory(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.ErrorHistoryService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

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


	listResult, err := Services.ErrorHistoryService.List(limit, offset, filters)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E9004",
			err.Error(),
		)

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

// GetErrorHistoryByID returns a single error by database ID
func GetErrorHistoryByID(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.ErrorHistoryService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	vars := mux.Vars(r)
	idStr := vars["id"]

	// Try to parse as int64 first (database ID)
	id, err := strconv.ParseInt(idStr, 10, 64)
	if err != nil {
		// Try as errorId string
		errHistory, err := Services.ErrorHistoryService.GetByErrorId(idStr)
		if err != nil {
			respondError(
				w,
				wordpress.HttpStatusNotFound,
				"E9001",
				err.Error(),
			)

			return
		}

		respondSuccess(w, errHistory)

		return
	}

	errHistory, err := Services.ErrorHistoryService.GetById(id)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusNotFound,
			"E9001",
			err.Error(),
		)

		return
	}

	respondSuccess(w, errHistory)
}
