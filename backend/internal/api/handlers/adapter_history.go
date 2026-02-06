// Package handlers - PublishHistory and SiteHealth service adapters
package handlers

import (
	"context"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/publishhistory"
	"wp-plugin-publish/internal/services/sitehealth"
)

// PublishHistoryServiceAdapter wraps *publishhistory.Service
type PublishHistoryServiceAdapter struct {
	*publishhistory.Service
}

func (a *PublishHistoryServiceAdapter) Record(entry models.PublishHistory) (*models.PublishHistory, error) {
	return a.Service.Record(entry)
}

func (a *PublishHistoryServiceAdapter) List(limit, offset int, filters models.PublishHistoryFilters) ([]models.PublishHistory, int, error) {
	return a.Service.List(limit, offset, filters)
}

func (a *PublishHistoryServiceAdapter) GetByID(id int64) (*models.PublishHistory, error) {
	return a.Service.GetByID(id)
}

func (a *PublishHistoryServiceAdapter) GetStats() (*models.PublishHistoryStats, error) {
	return a.Service.GetStats()
}

func (a *PublishHistoryServiceAdapter) Delete(id int64) error {
	return a.Service.Delete(id)
}

func (a *PublishHistoryServiceAdapter) Clear() (int64, error) {
	return a.Service.Clear()
}

// SiteHealthServiceAdapter wraps *sitehealth.Service
type SiteHealthServiceAdapter struct {
	*sitehealth.Service
}

func (a *SiteHealthServiceAdapter) CheckSite(ctx context.Context, siteID int64) (*models.SiteHealthCheck, error) {
	return a.Service.CheckSite(ctx, siteID)
}

func (a *SiteHealthServiceAdapter) CheckAllSites(ctx context.Context) ([]models.SiteHealthCheck, error) {
	return a.Service.CheckAllSites(ctx)
}

func (a *SiteHealthServiceAdapter) GetHistory(siteID int64, limit int) ([]models.SiteHealthCheck, error) {
	return a.Service.GetHistory(siteID, limit)
}

func (a *SiteHealthServiceAdapter) GetSummaries(ctx context.Context) ([]models.SiteHealthSummary, error) {
	return a.Service.GetSummaries(ctx)
}

func (a *SiteHealthServiceAdapter) GetStats(ctx context.Context) (*models.SiteHealthStats, error) {
	return a.Service.GetStats(ctx)
}

func (a *SiteHealthServiceAdapter) ClearHistory(olderThanDays int) (int64, error) {
	return a.Service.ClearHistory(olderThanDays)
}
