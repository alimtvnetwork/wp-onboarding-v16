// Package handlers provides E2E test HTTP request handlers
package handlers

import (
	"context"
	"net/http"
	"strconv"

	"github.com/gorilla/mux"

	"wp-plugin-publish/internal/services/e2e"
	"wp-plugin-publish/internal/wordpress"
)

// E2EServiceInterface defines E2E test service methods
type E2EServiceInterface interface {
	ListSuites(ctx context.Context) ([]e2e.TestSuite, error)
	GetCases(ctx context.Context, suiteId string) ([]e2e.TestCase, error)
	StartRun(ctx context.Context, opts e2e.RunOptions) (*e2e.TestRun, error)
	AbortRun(ctx context.Context, runId string) error
	ListRuns(ctx context.Context, limit int) ([]e2e.TestRun, error)
	GetRun(ctx context.Context, runId string) (*e2e.RunSummary, error)
	DeleteRun(ctx context.Context, runId string) error
}

// E2EService holds the E2E service instance
var E2EService E2EServiceInterface

// GetE2ESuites returns all test suites
var GetE2ESuites = handleListNilSafe(e2eServiceGetter, "E7001",
	func(ctx context.Context) (any, error) {
		return E2EService.ListSuites(ctx)
	},
)

// GetE2ECases returns test cases for a suite
func GetE2ECases(w http.ResponseWriter, r *http.Request) {
	isServiceMissing := E2EService == nil

	if isServiceMissing {
		respondSuccess(w, []e2e.TestCase{})

		return
	}

	vars := mux.Vars(r)
	suiteId := vars["id"]

	cases, err := E2EService.GetCases(r.Context(), suiteId)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E7002",
			err.Error(),
		)

		return
	}

	respondSuccess(w, cases)
}

// StartE2ERun begins a new test run
func StartE2ERun(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, E2EService, "E2E service") {
		return
	}

	var opts e2e.RunOptions
	if isBodyInvalid(w, r, &opts) {
		return
	}

	run, err := E2EService.StartRun(r.Context(), opts)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E7003",
			err.Error(),
		)

		return
	}

	respondCreated(w, run)
}

// GetE2ERuns returns past test runs
func GetE2ERuns(w http.ResponseWriter, r *http.Request) {
	isServiceMissing := E2EService == nil

	if isServiceMissing {
		respondSuccess(w, []e2e.TestRun{})

		return
	}

	limit := 20

	l := r.URL.Query().Get("limit")
	hasLimitParam := l != ""

	if hasLimitParam {
		parsed, err := strconv.Atoi(l)
		if err == nil {
			limit = parsed
		}
	}

	runs, err := E2EService.ListRuns(r.Context(), limit)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E7001",
			err.Error(),
		)

		return
	}

	respondSuccess(w, runs)
}

// GetE2ERun returns a specific test run with results
func GetE2ERun(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, E2EService, "E2E service") {
		return
	}

	vars := mux.Vars(r)
	runId := vars["id"]

	run, err := E2EService.GetRun(r.Context(), runId)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusNotFound,
			"E7001",
			err.Error(),
		)

		return
	}

	respondSuccess(w, run)
}

// AbortE2ERun stops a running test
func AbortE2ERun(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, E2EService, "E2E service") {
		return
	}

	vars := mux.Vars(r)
	runId := vars["id"]

	err := E2EService.AbortRun(r.Context(), runId)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E7003",
			err.Error(),
		)

		return
	}

	respondSuccess(w, ActionResponse{IsAborted: true})
}

// DeleteE2ERun removes a test run
func DeleteE2ERun(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, E2EService, "E2E service") {
		return
	}

	vars := mux.Vars(r)
	runId := vars["id"]

	err := E2EService.DeleteRun(r.Context(), runId)

	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E7001",
			err.Error(),
		)

		return
	}

	respondDeleted(w)
}
