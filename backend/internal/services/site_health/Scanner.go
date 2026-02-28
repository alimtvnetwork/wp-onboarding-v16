// Package sitehealth — row scanning for health check records.
package sitehealth

import (
	"database/sql"
	"time"

	"wp-plugin-publish/internal/models"
)

// siteCheckInfo holds site data needed for a health check probe.
type siteCheckInfo struct {
	ID                int64
	Name              string
	URL               string
	Username          string
	PasswordEncrypted []byte
}

// scanHealthCheckRows scans health check rows into a slice.
func scanHealthCheckRows(rows *sql.Rows) []models.SiteHealthCheck {
	var checks []models.SiteHealthCheck

	for rows.Next() {
		m, err := scanHealthCheckRow(rows)
	if err != nil {
			continue
		}

		checks = append(checks, m)
	}

	return checks
}

// scanHealthCheckRow scans a single health check from *sql.Rows.
func scanHealthCheckRow(rows *sql.Rows) (models.SiteHealthCheck, error) {
	var m models.SiteHealthCheck
	var createdAt string
	var errMsg *string

	err := rows.Scan(
		&m.ID,
		&m.SiteID,
		&m.SiteName,
		&m.SiteURL,
		&m.Status,
		&m.ResponseMs,
		&m.StatusCode,
		&errMsg,
		&m.UploaderOk,
		&createdAt,
	)

	if err != nil {
		return m, err
	}

	if errMsg != nil {
		m.ErrorMessage = *errMsg
	}

	m.CreatedAt, _ = time.Parse("2006-01-02 15:04:05", createdAt)

	return m, nil
}

// scanSummaryRows scans health summary rows into a slice.
func scanSummaryRows(rows *sql.Rows) []models.SiteHealthSummary {
	var summaries []models.SiteHealthSummary

	for rows.Next() {
		m, err := scanSummaryRow(rows)
	if err != nil {
			continue
		}

		if m.TotalChecks > 0 {
			m.UptimePercent = float64(m.HealthyChecks) / float64(m.TotalChecks) * 100
		}

		summaries = append(summaries, m)
	}

	return summaries
}

// scanSummaryRow scans a single health summary from *sql.Rows.
func scanSummaryRow(rows *sql.Rows) (models.SiteHealthSummary, error) {
	var m models.SiteHealthSummary

	err := rows.Scan(
		&m.SiteID,
		&m.SiteName,
		&m.SiteURL,
		&m.CurrentStatus,
		&m.LastCheckedAt,
		&m.AvgResponseMs,
		&m.TotalChecks,
		&m.HealthyChecks,
		&m.DownChecks,
		&m.LastErrorAt,
		&m.LastError,
	)

	return m, err
}
