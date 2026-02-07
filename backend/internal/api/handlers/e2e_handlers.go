// Package handlers provides E2E test HTTP request handlers
package handlers

import (
	"context"
	"net/http"
	"strconv"

	"github.com/gorilla/mux"
)

// E2EServiceInterface defines E2E test service methods
type E2EServiceInterface interface {
	ListSuites(ctx context.Context) (interface{}, error)
	GetCases(ctx context.Context, suiteID string) (interface{}, error)
	StartRun(ctx context.Context, opts interface{}) (interface{}, error)
	AbortRun(ctx context.Context, runID string) error
	ListRuns(ctx context.Context, limit int) (interface{}, error)
	GetRun(ctx context.Context, runID string) (interface{}, error)
	DeleteRun(ctx context.Context, runID string) error
}

// E2EService holds the E2E service instance
var E2EService E2EServiceInterface

// GetE2ESuites returns all test suites
var GetE2ESuites = handleListNilSafe(e2eServiceGetter, "E7001",
	func(ctx context.Context) (interface{}, error) {
		return E2EService.ListSuites(ctx)
	},
)

// GetE2ECases returns test cases for a suite
func GetE2ECases(w http.ResponseWriter, r *http.Request) {
	if E2EService == nil {
		respondSuccess(w, []interface{}{})
		return
	}
	vars := mux.Vars(r)
	suiteID := vars["id"]
	cases, err := E2EService.GetCases(r.Context(), suiteID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E7002", err.Error())
		return
	}
	respondSuccess(w, cases)
}

// StartE2ERun begins a new test run
func StartE2ERun(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, E2EService, "E2E service") {
		return
	}

	var opts map[string]interface{}
	if !decodeJSON(w, r, &opts) {
		return
	}

	run, err := E2EService.StartRun(r.Context(), opts)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E7003", err.Error())
		return
	}
	respondCreated(w, run)
}

// GetE2ERuns returns past test runs
func GetE2ERuns(w http.ResponseWriter, r *http.Request) {
	if E2EService == nil {
		respondSuccess(w, []interface{}{})
		return
	}

	limit := 20
	if l := r.URL.Query().Get("limit"); l != "" {
		if parsed, err := strconv.Atoi(l); err == nil {
			limit = parsed
		}
	}

	runs, err := E2EService.ListRuns(r.Context(), limit)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E7001", err.Error())
		return
	}
	respondSuccess(w, runs)
}

// GetE2ERun returns a specific test run with results
func GetE2ERun(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, E2EService, "E2E service") {
		return
	}
	vars := mux.Vars(r)
	runID := vars["id"]

	run, err := E2EService.GetRun(r.Context(), runID)
	if err != nil {
		respondError(w, http.StatusNotFound, "E7001", err.Error())
		return
	}
	respondSuccess(w, run)
}

// AbortE2ERun stops a running test
func AbortE2ERun(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, E2EService, "E2E service") {
		return
	}
	vars := mux.Vars(r)
	runID := vars["id"]

	if err := E2EService.AbortRun(r.Context(), runID); err != nil {
		respondError(w, http.StatusBadRequest, "E7003", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"aborted": true})
}

// DeleteE2ERun removes a test run
func DeleteE2ERun(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, E2EService, "E2E service") {
		return
	}
	vars := mux.Vars(r)
	runID := vars["id"]

	if err := E2EService.DeleteRun(r.Context(), runID); err != nil {
		respondError(w, http.StatusBadRequest, "E7001", err.Error())
		return
	}
	respondDeleted(w)
}
