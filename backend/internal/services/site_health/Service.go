// Package sitehealth provides site health monitoring
package sitehealth

import (
	"context"
	"fmt"
	"net/http"
	"sync"
	"time"

	"wp-plugin-publish/internal/database"
	endpoint "wp-plugin-publish/internal/enums/endpointtype"
	healthstatus "wp-plugin-publish/internal/enums/healthstatustype"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// Config holds configuration for the site health service
type Config struct {
	DB             *database.DB
	Logger         *logger.Logger
	TimeoutSeconds int // HTTP client timeout; defaults to 15s
}

// Service manages site health checks
type Service struct {
	db     *database.DB
	log    *logger.Logger
	client *http.Client
	mu     sync.Mutex
}

// New creates a new health check service
func New(cfg Config) *Service {
	timeout := 15 * time.Second

	if cfg.TimeoutSeconds > 0 {
		timeout = time.Duration(cfg.TimeoutSeconds) * time.Second
	}

	return &Service{
		db:  cfg.DB,
		log: cfg.Logger,
		client: &http.Client{
			Timeout: timeout,
		},
	}
}

// CheckSite performs a health check on a single site
func (s *Service) CheckSite(ctx context.Context, siteId int64) apperror.Result[models.SiteHealthCheck] {
	site, siteErr := s.querySiteForCheck(ctx, siteId)
	if siteErr != nil {

		return apperror.Fail[models.SiteHealthCheck](siteErr)
	}

	check := s.performHealthProbe(ctx, site)
	s.saveCheck(&check)

	return apperror.Ok(check)
}

// querySiteForCheck fetches the site info needed for a health check.
func (s *Service) querySiteForCheck(ctx context.Context, siteId int64) (*siteCheckInfo, *apperror.AppError) {
	var m siteCheckInfo
	m.Id = siteId

	err := s.db.QueryRowContext(ctx,
		"SELECT Name, Url, Username, PasswordEncrypted FROM Sites WHERE Id = ?", siteId,
	).Scan(
		&m.Name,
		&m.Url,
		&m.Username,
		&m.PasswordEncrypted,
	)

	if err != nil {

		return nil, apperror.Wrap(err, apperror.ErrFSRead, "site not found").
			WithSiteId(siteId)
	}

	return &m, nil
}

// performHealthProbe performs the HTTP probe and returns the check result.
func (s *Service) performHealthProbe(ctx context.Context, site *siteCheckInfo) models.SiteHealthCheck {
	check := models.SiteHealthCheck{
		SiteId:   site.Id,
		SiteName: site.Name,
		SiteUrl:  site.Url,
	}

	statusUrl := wordpress.BuildWpPluginUrl(site.Url, wordpress.RiseupAsiaNamespace, endpoint.Status)
	req, err := http.NewRequestWithContext(ctx, "GET", statusUrl, nil)

	if err != nil {
		check.Status = healthstatus.Down.DBValue()
		check.ErrorMessage = err.Error()

		return check
	}

	start := time.Now()
	resp, httpErr := s.client.Do(req)
	elapsed := time.Since(start).Milliseconds()
	check.ResponseMs = elapsed

	if httpErr != nil {
		check.Status = healthstatus.Down.DBValue()
		check.ErrorMessage = httpErr.Error()

		return check
	}
	defer resp.Body.Close()

	applyHttpStatus(&check, resp.StatusCode, elapsed)

	return check
}

// applyHttpStatus sets the check status based on HTTP response code and latency.
func applyHttpStatus(check *models.SiteHealthCheck, statusCode int, elapsed int64) {
	check.StatusCode = statusCode

	httpStatus := wordpress.HttpStatusType(statusCode)

	switch {
	case httpStatus.IsSuccess():
		check.Status = healthstatus.Healthy.DBValue()
		check.UploaderOk = true
	case statusCode == wordpress.HttpStatusUnauthorized.Int() || statusCode == wordpress.HttpStatusForbidden.Int():
		check.Status = healthstatus.Healthy.DBValue()
		check.UploaderOk = false
	case httpStatus.IsServerError():
		check.Status = healthstatus.Down.DBValue()
		check.ErrorMessage = fmt.Sprintf("HTTP %d", statusCode)
	default:
		check.Status = healthstatus.Degraded.DBValue()
		check.ErrorMessage = fmt.Sprintf("HTTP %d", statusCode)
	}

	isSlowButHealthy :=
		check.Status == healthstatus.Healthy.DBValue() &&
			elapsed > 5000

	if isSlowButHealthy {
		check.Status = healthstatus.Degraded.DBValue()
	}
}

// CheckAllSites performs health checks on all registered sites
func (s *Service) CheckAllSites(ctx context.Context) apperror.ResultSlice[models.SiteHealthCheck] {
	siteIds, err := s.listSiteIds(ctx)
	if err != nil {

		return apperror.FailSlice[models.SiteHealthCheck](err)
	}

	results := make([]models.SiteHealthCheck, 0, len(siteIds))

	for _, id := range siteIds {
		result := s.CheckSite(ctx, id)

		if result.HasError() {
			s.log.Warn("Health check failed", "siteId", id, "error", result.AppError())

			continue
		}

		results = append(results, result.Value())
	}

	return apperror.OkSlice(results)
}

// listSiteIds returns all site IDs from the database.
func (s *Service) listSiteIds(ctx context.Context) ([]int64, *apperror.AppError) {
	rows, err := s.db.QueryContext(ctx, "SELECT Id FROM Sites")
	if err != nil {

		return nil, apperror.Wrap(err, apperror.ErrFSWrite, "failed to list sites")
	}
	defer rows.Close()

	var siteIds []int64

	for rows.Next() {
		var id int64
		scanErr := rows.Scan(&id)

		if scanErr != nil {

			continue
		}

		siteIds = append(siteIds, id)
	}

	return siteIds, nil
}

// GetHistory returns health check history
func (s *Service) GetHistory(siteId int64, limit int) apperror.ResultSlice[models.SiteHealthCheck] {
	isLimitUnset := limit <= 0

	if isLimitUnset {
		limit = 50
	}

	rows, err := s.db.Query(healthHistorySQL, siteId, siteId, limit)
	if err != nil {

		return apperror.FailSliceWrap[models.SiteHealthCheck](err, apperror.ErrFSDelete, "failed to query health history")
	}
	defer rows.Close()

	checks := scanHealthCheckRows(rows)

	return apperror.OkSlice(checks)
}

// GetSummaries returns health summaries for all sites
func (s *Service) GetSummaries(ctx context.Context) apperror.ResultSlice[models.SiteHealthSummary] {
	rows, err := s.db.QueryContext(ctx, buildSummarySQL())
	if err != nil {

		return apperror.FailSliceWrap[models.SiteHealthSummary](err, apperror.ErrFSNotFound, "failed to query health summaries")
	}
	defer rows.Close()

	summaries := scanSummaryRows(rows)

	return apperror.OkSlice(summaries)
}

// GetStats returns overall health statistics
func (s *Service) GetStats(ctx context.Context) apperror.Result[models.SiteHealthStats] {
	summariesResult := s.GetSummaries(ctx)

	if summariesResult.HasError() {

		return apperror.Fail[models.SiteHealthStats](summariesResult.AppError())
	}

	stats := computeStats(summariesResult.Items())

	return apperror.Ok(stats)
}

// ClearHistory removes old health check records
func (s *Service) ClearHistory(olderThanDays int) apperror.Result[int64] {
	isDaysUnset := olderThanDays <= 0

	if isDaysUnset {
		olderThanDays = 30
	}

	cutoff := time.Now().AddDate(0, 0, -olderThanDays).Format("2006-01-02 15:04:05")
	result, err := s.db.Exec("DELETE FROM SiteHealthChecks WHERE CreatedAt < ?", cutoff)

	if err != nil {

		return apperror.FailWrap[int64](err, apperror.ErrFSPermission, "failed to clear health history")
	}

	deleted, _ := result.RowsAffected()

	return apperror.Ok(deleted)
}

func (s *Service) saveCheck(check *models.SiteHealthCheck) {
	check.CreatedAt = time.Now()

	_, err := s.db.Exec(
		insertCheckSQL,
		check.SiteId,
		check.Status,
		check.ResponseMs,
		check.StatusCode,
		check.ErrorMessage,
		check.UploaderOk,
		check.CreatedAt.Format("2006-01-02 15:04:05"),
	)

	if err != nil {
		s.log.Error("Failed to save health check", "siteId", check.SiteId, "error", err)
	}
}
