// Package handlers - PublishHistory and SiteHealth service interfaces and adapters
package handlers

import (
	"context"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/publishhistory"
	"wp-plugin-publish/internal/services/sitehealth"
)

// PublishHistoryServiceInterface defines publish history service methods
type PublishHistoryServiceInterface interface {
	Record(entry models.PublishHistory) (*models.PublishHistory, error)
	List(limit, offset int, filters models.PublishHistoryFilters) ([]models.PublishHistory, int, error)
	GetByID(id int64) (*models.PublishHistory, error)
	GetStats() (*models.PublishHistoryStats, error)
	Delete(id int64) error
	Clear() (int64, error)
}

// SiteHealthServiceInterface defines health check service methods
type SiteHealthServiceInterface interface {
	CheckSite(ctx context.Context, siteID int64) (*models.SiteHealthCheck, error)
	CheckAllSites(ctx context.Context) ([]models.SiteHealthCheck, error)
	GetHistory(siteID int64, limit int) ([]models.SiteHealthCheck, error)
	GetSummaries(ctx context.Context) ([]models.SiteHealthSummary, error)
	GetStats(ctx context.Context) (*models.SiteHealthStats, error)
	ClearHistory(olderThanDays int) (int64, error)
}

// PublishHistoryServiceAdapter wraps *publishhistory.Service
type PublishHistoryServiceAdapter struct {
	*publishhistory.Service
}

func (a *PublishHistoryServiceAdapter) Record(entry models.PublishHistory) (*models.PublishHistory, error) {
	result := a.Service.Record(entry)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *PublishHistoryServiceAdapter) List(limit, offset int, filters models.PublishHistoryFilters) ([]models.PublishHistory, int, error) {
	result := a.Service.List(limit, offset, filters)
	if result.HasError() {
		return nil, 0, result.AppError()
	}
	v := result.Value()
	return v.Items, v.Total, nil
}

func (a *PublishHistoryServiceAdapter) GetByID(id int64) (*models.PublishHistory, error) {
	result := a.Service.GetByID(id)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *PublishHistoryServiceAdapter) GetStats() (*models.PublishHistoryStats, error) {
	result := a.Service.GetStats()
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *PublishHistoryServiceAdapter) Delete(id int64) error {
	return a.Service.Delete(id)
}

func (a *PublishHistoryServiceAdapter) Clear() (int64, error) {
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

func (a *SiteHealthServiceAdapter) CheckSite(ctx context.Context, siteID int64) (*models.SiteHealthCheck, error) {
	result := a.Service.CheckSite(ctx, siteID)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *SiteHealthServiceAdapter) CheckAllSites(ctx context.Context) ([]models.SiteHealthCheck, error) {
	result := a.Service.CheckAllSites(ctx)
	if result.HasError() {
		return nil, result.AppError()
	}
	return result.Items(), nil
}

func (a *SiteHealthServiceAdapter) GetHistory(siteID int64, limit int) ([]models.SiteHealthCheck, error) {
	result := a.Service.GetHistory(siteID, limit)
	if result.HasError() {
		return nil, result.AppError()
	}
	return result.Items(), nil
}

func (a *SiteHealthServiceAdapter) GetSummaries(ctx context.Context) ([]models.SiteHealthSummary, error) {
	result := a.Service.GetSummaries(ctx)
	if result.HasError() {
		return nil, result.AppError()
	}
	return result.Items(), nil
}

func (a *SiteHealthServiceAdapter) GetStats(ctx context.Context) (*models.SiteHealthStats, error) {
	result := a.Service.GetStats(ctx)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *SiteHealthServiceAdapter) ClearHistory(olderThanDays int) (int64, error) {
	result := a.Service.ClearHistory(olderThanDays)
	if result.HasError() {
		return 0, result.AppError()
	}
	return result.Value(), nil
}
