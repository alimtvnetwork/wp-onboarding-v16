// Package sitehealth provides site health monitoring
package sitehealth

import (
	"context"
	"fmt"
	"net/http"
	"sync"
	"time"

	"wp-plugin-publish/internal/database"
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
func (s *Service) CheckSite(ctx context.Context, siteID int64) (*models.SiteHealthCheck, error) {
	// Get site info
	var siteName, siteURL, username string
	var passwordEncrypted []byte
	err := s.db.QueryRowContext(ctx,
		"SELECT Name, Url, Username, PasswordEncrypted FROM Sites WHERE Id = ?", siteID,
	).Scan(&siteName, &siteURL, &username, &passwordEncrypted)
	if err != nil {
		return nil, apperror.Wrap(err, "E4001", "site not found").WithSiteID(siteID)
	}

	check := &models.SiteHealthCheck{
		SiteID:   siteID,
		SiteName: siteName,
		SiteURL:  siteURL,
	}

	// Measure response time to the WP REST API
	start := time.Now()
	statusURL := fmt.Sprintf("%s/wp-json/%s%s", siteURL, wordpress.RiseupAsiaNamespace, wordpress.EndpointStatus)
	req, err := http.NewRequestWithContext(ctx, "GET", statusURL, nil)
	if err != nil {
		check.Status = "down"
		check.ErrorMessage = err.Error()
		s.saveCheck(check)
		return check, nil
	}

	resp, err := s.client.Do(req)
	elapsed := time.Since(start).Milliseconds()
	check.ResponseMs = elapsed

	if err != nil {
		check.Status = "down"
		check.ErrorMessage = err.Error()
		s.saveCheck(check)
		return check, nil
	}
	defer resp.Body.Close()

	check.StatusCode = resp.StatusCode

	switch {
	case resp.StatusCode >= 200 && resp.StatusCode < 300:
		check.Status = "healthy"
		check.UploaderOk = true
	case resp.StatusCode == 401 || resp.StatusCode == 403:
		// Auth required but site is up
		check.Status = "healthy"
		check.UploaderOk = false
	case resp.StatusCode >= 500:
		check.Status = "down"
		check.ErrorMessage = fmt.Sprintf("HTTP %d", resp.StatusCode)
	default:
		check.Status = "degraded"
		check.ErrorMessage = fmt.Sprintf("HTTP %d", resp.StatusCode)
	}

	// Slow response = degraded
	if check.Status == "healthy" && elapsed > 5000 {
		check.Status = "degraded"
	}

	s.saveCheck(check)
	return check, nil
}

// CheckAllSites performs health checks on all registered sites
func (s *Service) CheckAllSites(ctx context.Context) ([]models.SiteHealthCheck, error) {
	rows, err := s.db.QueryContext(ctx, "SELECT Id FROM Sites")
	if err != nil {
		return nil, apperror.Wrap(err, "E4002", "failed to list sites")
	}
	defer rows.Close()

	var siteIDs []int64
	for rows.Next() {
		var id int64
		if err := rows.Scan(&id); err != nil {
			continue
		}
		siteIDs = append(siteIDs, id)
	}

	results := make([]models.SiteHealthCheck, 0, len(siteIDs))
	for _, id := range siteIDs {
		check, err := s.CheckSite(ctx, id)
		if err != nil {
			s.log.Warn("Health check failed", "siteId", id, "error", err)
			continue
		}
		results = append(results, *check)
	}

	return results, nil
}

// GetHistory returns health check history
func (s *Service) GetHistory(siteID int64, limit int) ([]models.SiteHealthCheck, error) {
	if limit <= 0 {
		limit = 50
	}

	rows, err := s.db.Query(`
		SELECT h.Id, h.SiteId, s.Name, s.Url, h.Status, h.ResponseMs, h.StatusCode, h.ErrorMessage, h.UploaderOk, h.CreatedAt
		FROM SiteHealthChecks h
		JOIN Sites s ON s.Id = h.SiteId
		WHERE (? = 0 OR h.SiteId = ?)
		ORDER BY h.CreatedAt DESC
		LIMIT ?
	`, siteID, siteID, limit)
	if err != nil {
		return nil, apperror.Wrap(err, "E4003", "failed to query health history")
	}
	defer rows.Close()

	var checks []models.SiteHealthCheck
	for rows.Next() {
		var c models.SiteHealthCheck
		var createdAt string
		var errMsg *string
		if err := rows.Scan(&c.ID, &c.SiteID, &c.SiteName, &c.SiteURL, &c.Status, &c.ResponseMs, &c.StatusCode, &errMsg, &c.UploaderOk, &createdAt); err != nil {
			continue
		}
		if errMsg != nil {
			c.ErrorMessage = *errMsg
		}
		c.CreatedAt, _ = time.Parse("2006-01-02 15:04:05", createdAt)
		checks = append(checks, c)
	}
	return checks, nil
}

// GetSummaries returns health summaries for all sites
func (s *Service) GetSummaries(ctx context.Context) ([]models.SiteHealthSummary, error) {
	rows, err := s.db.QueryContext(ctx, `
		SELECT 
			s.Id, s.Name, s.Url,
			COALESCE((SELECT Status FROM SiteHealthChecks WHERE SiteId = s.Id ORDER BY CreatedAt DESC LIMIT 1), 'unknown') as CurrentStatus,
			(SELECT MAX(CreatedAt) FROM SiteHealthChecks WHERE SiteId = s.Id) as LastCheckedAt,
			COALESCE((SELECT AVG(ResponseMs) FROM SiteHealthChecks WHERE SiteId = s.Id), 0) as AvgResponseMs,
			COALESCE((SELECT COUNT(*) FROM SiteHealthChecks WHERE SiteId = s.Id), 0) as TotalChecks,
			COALESCE((SELECT COUNT(*) FROM SiteHealthChecks WHERE SiteId = s.Id AND Status = 'healthy'), 0) as HealthyChecks,
			COALESCE((SELECT COUNT(*) FROM SiteHealthChecks WHERE SiteId = s.Id AND Status = 'down'), 0) as DownChecks,
			(SELECT MAX(CreatedAt) FROM SiteHealthChecks WHERE SiteId = s.Id AND Status = 'down') as LastErrorAt,
			COALESCE((SELECT ErrorMessage FROM SiteHealthChecks WHERE SiteId = s.Id AND Status = 'down' ORDER BY CreatedAt DESC LIMIT 1), '') as LastError
		FROM Sites s
		ORDER BY s.Name
	`)
	if err != nil {
		return nil, apperror.Wrap(err, "E4004", "failed to query health summaries")
	}
	defer rows.Close()

	var summaries []models.SiteHealthSummary
	for rows.Next() {
		var sm models.SiteHealthSummary
		if err := rows.Scan(&sm.SiteID, &sm.SiteName, &sm.SiteURL, &sm.CurrentStatus, &sm.LastCheckedAt,
			&sm.AvgResponseMs, &sm.TotalChecks, &sm.HealthyChecks, &sm.DownChecks, &sm.LastErrorAt, &sm.LastError); err != nil {
			continue
		}
		if sm.TotalChecks > 0 {
			sm.UptimePercent = float64(sm.HealthyChecks) / float64(sm.TotalChecks) * 100
		}
		summaries = append(summaries, sm)
	}
	return summaries, nil
}

// GetStats returns overall health statistics
func (s *Service) GetStats(ctx context.Context) (*models.SiteHealthStats, error) {
	summaries, err := s.GetSummaries(ctx)
	if err != nil {
		return nil, err
	}

	stats := &models.SiteHealthStats{TotalSites: len(summaries)}
	var totalResponse float64
	var totalUptime float64

	for _, sm := range summaries {
		switch sm.CurrentStatus {
		case "healthy":
			stats.HealthySites++
		case "degraded":
			stats.DegradedSites++
		case "down":
			stats.DownSites++
		default:
			stats.UnknownSites++
		}
		totalResponse += sm.AvgResponseMs
		totalUptime += sm.UptimePercent
	}

	if stats.TotalSites > 0 {
		stats.AvgResponseMs = totalResponse / float64(stats.TotalSites)
		stats.AvgUptime = totalUptime / float64(stats.TotalSites)
	}

	return stats, nil
}

// ClearHistory removes old health check records
func (s *Service) ClearHistory(olderThanDays int) (int64, error) {
	if olderThanDays <= 0 {
		olderThanDays = 30
	}
	cutoff := time.Now().AddDate(0, 0, -olderThanDays).Format("2006-01-02 15:04:05")
	result, err := s.db.Exec("DELETE FROM SiteHealthChecks WHERE CreatedAt < ?", cutoff)
	if err != nil {
		return 0, apperror.Wrap(err, "E4005", "failed to clear health history")
	}
	return result.RowsAffected()
}

func (s *Service) saveCheck(check *models.SiteHealthCheck) {
	check.CreatedAt = time.Now()
	_, err := s.db.Exec(`
		INSERT INTO SiteHealthChecks (SiteId, Status, ResponseMs, StatusCode, ErrorMessage, UploaderOk, CreatedAt)
		VALUES (?, ?, ?, ?, ?, ?, ?)
	`, check.SiteID, check.Status, check.ResponseMs, check.StatusCode, check.ErrorMessage, check.UploaderOk, check.CreatedAt.Format("2006-01-02 15:04:05"))
	if err != nil {
		s.log.Error("Failed to save health check", "siteId", check.SiteID, "error", err)
	}
}
