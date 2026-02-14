// Package handlers provides HTTP request handlers for session management
package handlers

import (
	"net/http"
	"strconv"

	"github.com/gorilla/mux"
	"wp-plugin-publish/internal/wordpress"
)

// GetSessions returns a list of recent sessions
func GetSessions(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SessionService == nil {
		respondSuccess(w, []any{})
		return
	}

	// Parse optional limit parameter
	limit := 100
	if limitStr := r.URL.Query().Get("limit"); limitStr != "" {
		if l, err := strconv.Atoi(limitStr); err == nil && l > 0 {
			limit = l
		}
	}

	sessions, err := Services.SessionService.ListSessions(limit)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E8001", err.Error())
		return
	}
	respondSuccess(w, sessions)
}

// GetSession returns details for a specific session
func GetSession(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SessionService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", "Session service not available")
		return
	}

	vars := mux.Vars(r)
	sessionID := vars["id"]
	if sessionID == "" {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "Session ID is required")
		return
	}

	session, err := Services.SessionService.GetSession(sessionID)
	if err != nil {
		respondError(w, wordpress.HttpStatusNotFound, "E8002", err.Error())
		return
	}
	respondSuccess(w, session)
}

// GetSessionLogs returns the full log content for a session
func GetSessionLogs(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SessionService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", "Session service not available")
		return
	}

	vars := mux.Vars(r)
	sessionID := vars["id"]
	if sessionID == "" {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "Session ID is required")
		return
	}

	logs, err := Services.SessionService.GetSessionLogs(sessionID)
	if err != nil {
		respondError(w, wordpress.HttpStatusNotFound, "E8002", err.Error())
		return
	}

	// Check if client wants plain text
	accept := r.Header.Get("Accept")
	if accept == "text/plain" {
		w.Header().Set("Content-Type", "text/plain; charset=utf-8")
		w.WriteHeader(wordpress.HttpStatusOk.Int())
		w.Write([]byte(logs))
		return
	}

	respondSuccess(w, SessionLogsResponse{
		SessionID: sessionID,
		Logs:      logs,
	})
}

// DeleteSession removes a session's log file
func DeleteSession(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SessionService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", "Session service not available")
		return
	}

	vars := mux.Vars(r)
	sessionID := vars["id"]
	if sessionID == "" {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "Session ID is required")
		return
	}

	if err := Services.SessionService.DeleteSession(sessionID); err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E8003", err.Error())
		return
	}
	respondSuccess(w, ActionResponse{Deleted: true})
}

// GetSessionDiagnostics returns structured request/response/stackTrace for a session
func GetSessionDiagnostics(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SessionService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", "Session service not available")
		return
	}

	vars := mux.Vars(r)
	sessionID := vars["id"]
	if sessionID == "" {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "Session ID is required")
		return
	}

	diag, err := Services.SessionService.GetSessionDiagnostics(sessionID)
	if err != nil {
		respondError(w, wordpress.HttpStatusNotFound, "E8002", err.Error())
		return
	}
	respondSuccess(w, diag)
}
