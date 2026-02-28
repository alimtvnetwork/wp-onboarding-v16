// Package handlers provides E2E test HTTP request handlers
package handlers

import (
	"context"
	"net/http"
	"strconv"

	"github.com/gorilla/mux"

	"wp-plugin-publish/internal/services/e2e"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// E2EServiceInterface defines E2E test service methods
type E2EServiceInterface interface {
	ListSuites(ctx context.Context) ([]e2e.TestSuite, *apperror.AppError)
	GetCases(ctx context.Context, suiteId string) ([]e2e.TestCase, *apperror.AppError)
	StartRun(ctx context.Context, opts e2e.RunOptions) (*e2e.TestRun, *apperror.AppError)
	AbortRun(ctx context.Context, runId string) *apperror.AppError
	ListRuns(ctx context.Context, limit int) ([]e2e.TestRun, *apperror.AppError)
	GetRun(ctx context.Context, runId string) (*e2e.RunSummary, *apperror.AppError)
	DeleteRun(ctx context.Context, runId string) *apperror.AppError
}

// E2EService holds the E2E service instance
var E2EService E2EServiceInterface

// GetE2ESuites returns all test suites
var GetE2ESuites = handleListNilSafe(e2eServiceGetter, "E7001",
	func(ctx context.Context) (any, *apperror.AppError) {
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

	cases, appErr := E2EService.GetCases(r.Context(), suiteId)

	if appErr != nil {
		respondError(w, wordpress.HttpStatusServerError, "E7002", appErr.Error())

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

	run, appErr := E2EService.StartRun(r.Context(), opts)

	if appErr != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E7003", appErr.Error())

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

	runs, appErr := E2EService.ListRuns(r.Context(), limit)

	if appErr != nil {
		respondError(w, wordpress.HttpStatusServerError, "E7001", appErr.Error())

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

	run, appErr := E2EService.GetRun(r.Context(), runId)

	if appErr != nil {
		respondError(w, wordpress.HttpStatusNotFound, "E7001", appErr.Error())

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

	appErr := E2EService.AbortRun(r.Context(), runId)

	if appErr != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E7003", appErr.Error())

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

	appErr := E2EService.DeleteRun(r.Context(), runId)

	if appErr != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E7001", appErr.Error())

		return
	}

	respondDeleted(w)
}
