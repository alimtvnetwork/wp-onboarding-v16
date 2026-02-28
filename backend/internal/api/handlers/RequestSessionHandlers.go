// Package handlers - Request Session API handlers
package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"

	"github.com/gorilla/mux"
	"wp-plugin-publish/internal/api/middleware"
	"wp-plugin-publish/internal/wordpress"
)

// RequestSessionStore is the global request session store (set during server initialization)
var RequestSessionStore middleware.SessionStore

// GetRequestSessions returns paginated request sessions
func GetRequestSessions(w http.ResponseWriter, r *http.Request) {
	if RequestSessionStore == nil {
		respondSuccess(w, PaginatedSessions{
			Sessions: []*middleware.RequestSession{},
			Total:    0,
		})

		return
	}

	limit, _ := strconv.Atoi(r.URL.Query().Get("limit"))
	offset, _ := strconv.Atoi(r.URL.Query().Get("offset"))

	isInvalidLimit := limit <= 0
	if isInvalidLimit {
		limit = 50
	}

	listResult, err := RequestSessionStore.ListRequestSessions(limit, offset)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E9004",
			err.Error(),
		)

		return
	}

	respondSuccess(w, PaginatedSessions{
		Sessions: listResult.Sessions,
		Total:    listResult.Total,
		Limit:    limit,
		Offset:   offset,
	})
}

// GetRequestSession returns a single request session by ID
func GetRequestSession(w http.ResponseWriter, r *http.Request) {
	if RequestSessionStore == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			"Request session store not available",
		)

		return
	}

	vars := mux.Vars(r)
	id := vars["id"]

	session, err := RequestSessionStore.GetRequestSession(id)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusNotFound,
			"E9001",
			err.Error(),
		)

		return
	}

	respondSuccess(w, session)
}

// DeleteRequestSession removes a request session
func DeleteRequestSession(w http.ResponseWriter, r *http.Request) {
	if RequestSessionStore == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			"Request session store not available",
		)

		return
	}

	vars := mux.Vars(r)
	id := vars["id"]

	if err := RequestSessionStore.DeleteRequestSession(id); err != nil {
		respondError(
			w,
			wordpress.HttpStatusNotFound,
			"E9001",
			err.Error(),
		)

		return
	}

	respondSuccess(w, ActionResponse{IsDeleted: true, ID: id})
}

// ClearRequestSessions removes all request sessions
func ClearRequestSessions(w http.ResponseWriter, r *http.Request) {
	if RequestSessionStore == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			"Request session store not available",
		)

		return
	}

	if err := RequestSessionStore.ClearRequestSessions(); err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E9004",
			err.Error(),
		)

		return
	}

	respondSuccess(w, ActionResponse{IsCleared: true})
}

// GetRequestSessionsByError returns request sessions that resulted in errors
func GetRequestSessionsByError(w http.ResponseWriter, r *http.Request) {
	if RequestSessionStore == nil {
		respondSuccess(w, PaginatedSessions{
			Sessions: []*middleware.RequestSession{},
			Total:    0,
		})

		return
	}

	limit, _ := strconv.Atoi(r.URL.Query().Get("limit"))
	isInvalidLimit := limit <= 0
	if isInvalidLimit {
		limit = 50
	}

	// Get all sessions and filter by error
	allResult, err := RequestSessionStore.ListRequestSessions(1000, 0)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E9004",
			err.Error(),
		)

		return
	}

	// Filter sessions with errors
	var errorSessions []*middleware.RequestSession
	for _, s := range allResult.Sessions {
		isErrorResponse := s.StatusCode >= 400 || s.Error != ""
		if isErrorResponse {
			errorSessions = append(errorSessions, s)
			isAtLimit := len(errorSessions) >= limit
			if isAtLimit {
				break
			}
		}
	}

	respondSuccess(w, PaginatedSessions{
		Sessions: errorSessions,
		Total:    len(errorSessions),
	})
}

// ExportRequestSession exports a session as JSON for debugging
func ExportRequestSession(w http.ResponseWriter, r *http.Request) {
	if RequestSessionStore == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			"Request session store not available",
		)

		return
	}

	vars := mux.Vars(r)
	id := vars["id"]

	session, err := RequestSessionStore.GetRequestSession(id)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusNotFound,
			"E9001",
			err.Error(),
		)

		return
	}

	// Set headers for download
	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Content-Disposition", "attachment; filename=\"request-session-"+id+".json\"")

	encoder := json.NewEncoder(w)
	encoder.SetIndent("", "  ")
	encoder.Encode(session)
}
