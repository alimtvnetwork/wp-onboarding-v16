// Package main — E2E test service adapter.
package main

import (
	"context"

	"wp-plugin-publish/internal/services/e2e"
	"wp-plugin-publish/pkg/apperror"
)

// E2EServiceAdapter wraps e2e.Service to implement handlers.E2EServiceInterface
type E2EServiceAdapter struct {
	svc e2e.Service
}

func (a *E2EServiceAdapter) ListSuites(ctx context.Context) ([]e2e.TestSuite, *apperror.AppError) {
	return a.svc.ListSuites(ctx)
}

func (a *E2EServiceAdapter) GetCases(ctx context.Context, suiteId string) ([]e2e.TestCase, *apperror.AppError) {
	return a.svc.GetCases(ctx, suiteId)
}

func (a *E2EServiceAdapter) StartRun(ctx context.Context, opts e2e.RunOptions) (*e2e.TestRun, *apperror.AppError) {
	return a.svc.StartRun(ctx, opts)
}

func (a *E2EServiceAdapter) AbortRun(ctx context.Context, runId string) *apperror.AppError {
	return a.svc.AbortRun(ctx, runId)
}

func (a *E2EServiceAdapter) ListRuns(ctx context.Context, limit int) ([]e2e.TestRun, *apperror.AppError) {
	return a.svc.ListRuns(ctx, limit)
}

func (a *E2EServiceAdapter) GetRun(ctx context.Context, runId string) (*e2e.RunSummary, *apperror.AppError) {
	return a.svc.GetRun(ctx, runId)
}

func (a *E2EServiceAdapter) DeleteRun(ctx context.Context, runId string) *apperror.AppError {
	return a.svc.DeleteRun(ctx, runId)
}
