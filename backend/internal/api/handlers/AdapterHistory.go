// Package handlers - PublishHistory and SiteHealth service interfaces and adapters
package handlers

import (
	"context"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/publish_history"
	"wp-plugin-publish/internal/services/site_health"
	"wp-plugin-publish/pkg/apperror"
)

// PublishHistoryServiceInterface defines publish history service methods
type PublishHistoryServiceInterface interface {
	Record(entry models.PublishHistory) (*models.PublishHistory, *apperror.AppError)
	List(limit, offset int, filters models.PublishHistoryFilters) (*PublishHistoryListResult, *apperror.AppError)
	GetById(id int64) (*models.PublishHistory, *apperror.AppError)
	GetStats() (*models.PublishHistoryStats, *apperror.AppError)
	Delete(id int64) *apperror.AppError
	Clear() (int64, *apperror.AppError)
}

// PublishHistoryListResult holds paginated publish history results.
type PublishHistoryListResult struct {
	Items []models.PublishHistory
	Total int
}

// SiteHealthServiceInterface defines health check service methods
type SiteHealthServiceInterface interface {
	CheckSite(ctx context.Context, siteID int64) (*models.SiteHealthCheck, *apperror.AppError)
	CheckAllSites(ctx context.Context) ([]models.SiteHealthCheck, *apperror.AppError)
	GetHistory(siteID int64, limit int) ([]models.SiteHealthCheck, *apperror.AppError)
	GetSummaries(ctx context.Context) ([]models.SiteHealthSummary, *apperror.AppError)
	GetStats(ctx context.Context) (*models.SiteHealthStats, *apperror.AppError)
	ClearHistory(olderThanDays int) (int64, *apperror.AppError)
}

// PublishHistoryServiceAdapter wraps *publishhistory.Service
type PublishHistoryServiceAdapter struct {
	*publishhistory.Service
}

func (a *PublishHistoryServiceAdapter) Record(entry models.PublishHistory) (*models.PublishHistory, *apperror.AppError) {
	result := a.Service.Record(entry)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *PublishHistoryServiceAdapter) List(limit, offset int, filters models.PublishHistoryFilters) (*PublishHistoryListResult, *apperror.AppError) {
	result := a.Service.List(limit, offset, filters)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &PublishHistoryListResult{Items: v.Items, Total: v.Total}, nil
}

func (a *PublishHistoryServiceAdapter) GetById(id int64) (*models.PublishHistory, *apperror.AppError) {
	result := a.Service.GetById(id)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *PublishHistoryServiceAdapter) GetStats() (*models.PublishHistoryStats, *apperror.AppError) {
	result := a.Service.GetStats()
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *PublishHistoryServiceAdapter) Delete(id int64) *apperror.AppError {
	return a.Service.Delete(id)
}

func (a *PublishHistoryServiceAdapter) Clear() (int64, *apperror.AppError) {
	result := a.Service.Clear()
	if result.HasError() {
		return 0, result.AppError()
	}

	return result.Value(), nil
}

// SiteHealthServiceAdapter wraps *sitehealth.Service
type SiteHealthServiceAdapter struct {
	*sitehealth.Service
}

func (a *SiteHealthServiceAdapter) CheckSite(ctx context.Context, siteID int64) (*models.SiteHealthCheck, *apperror.AppError) {
	result := a.Service.CheckSite(ctx, siteID)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *SiteHealthServiceAdapter) CheckAllSites(ctx context.Context) ([]models.SiteHealthCheck, *apperror.AppError) {
	result := a.Service.CheckAllSites(ctx)
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Items(), nil
}

func (a *SiteHealthServiceAdapter) GetHistory(siteID int64, limit int) ([]models.SiteHealthCheck, *apperror.AppError) {
	result := a.Service.GetHistory(siteID, limit)
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Items(), nil
}

func (a *SiteHealthServiceAdapter) GetSummaries(ctx context.Context) ([]models.SiteHealthSummary, *apperror.AppError) {
	result := a.Service.GetSummaries(ctx)
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Items(), nil
}

func (a *SiteHealthServiceAdapter) GetStats(ctx context.Context) (*models.SiteHealthStats, *apperror.AppError) {
	result := a.Service.GetStats(ctx)
	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()

	return &v, nil
}

func (a *SiteHealthServiceAdapter) ClearHistory(olderThanDays int) (int64, *apperror.AppError) {
	result := a.Service.ClearHistory(olderThanDays)
	if result.HasError() {
		return 0, result.AppError()
	}

	return result.Value(), nil
}
