// Package sitehealth — SQL query constants and builders.
package sitehealth

import (
	healthstatus "wp-plugin-publish/internal/enums/healthstatustype"
	"wp-plugin-publish/internal/models"
)

const healthHistorySql = `
	SELECT h.Id, h.SiteId, s.Name, s.Url, h.Status, h.ResponseMs, h.StatusCode, h.ErrorMessage, h.UploaderOk, h.CreatedAt
	FROM SiteHealthChecks h
	JOIN Sites s ON s.Id = h.SiteId
	WHERE (? = 0 OR h.SiteId = ?)
	ORDER BY h.CreatedAt DESC
	LIMIT ?
`

const insertCheckSql = `
	INSERT INTO SiteHealthChecks (SiteId, Status, ResponseMs, StatusCode, ErrorMessage, UploaderOk, CreatedAt)
	VALUES (?, ?, ?, ?, ?, ?, ?)
`

// buildSummarySql constructs the health summary query using enum values.
func buildSummarySql() string {
	healthy := healthstatus.Healthy.DBValue()
	down := healthstatus.Down.DBValue()
	unknown := healthstatus.Unknown.DBValue()

	return `SELECT
		s.Id, s.Name, s.Url,
		COALESCE((SELECT Status FROM SiteHealthChecks WHERE SiteId = s.Id ORDER BY CreatedAt DESC LIMIT 1), '` + unknown + `') as CurrentStatus,
		(SELECT MAX(CreatedAt) FROM SiteHealthChecks WHERE SiteId = s.Id) as LastCheckedAt,
		COALESCE((SELECT AVG(ResponseMs) FROM SiteHealthChecks WHERE SiteId = s.Id), 0) as AvgResponseMs,
		COALESCE((SELECT COUNT(*) FROM SiteHealthChecks WHERE SiteId = s.Id), 0) as TotalChecks,
		COALESCE((SELECT COUNT(*) FROM SiteHealthChecks WHERE SiteId = s.Id AND Status = '` + healthy + `'), 0) as HealthyChecks,
		COALESCE((SELECT COUNT(*) FROM SiteHealthChecks WHERE SiteId = s.Id AND Status = '` + down + `'), 0) as DownChecks,
		(SELECT MAX(CreatedAt) FROM SiteHealthChecks WHERE SiteId = s.Id AND Status = '` + down + `') as LastErrorAt,
		COALESCE((SELECT ErrorMessage FROM SiteHealthChecks WHERE SiteId = s.Id AND Status = '` + down + `' ORDER BY CreatedAt DESC LIMIT 1), '') as LastError
	FROM Sites s
	ORDER BY s.Name`
}

// computeStats aggregates stats from summaries.
func computeStats(summaries []models.SiteHealthSummary) models.SiteHealthStats {
	stats := models.SiteHealthStats{TotalSites: len(summaries)}
	var totalResponse float64
	var totalUptime float64

	for _, m := range summaries {
		switch m.CurrentStatus {
		case healthstatus.Healthy.DBValue():
			stats.HealthySites++
		case healthstatus.Degraded.DBValue():
			stats.DegradedSites++
		case healthstatus.Down.DBValue():
			stats.DownSites++
		default:
			stats.UnknownSites++
		}

		totalResponse += m.AvgResponseMs
		totalUptime += m.UptimePercent
	}

	hasSites := stats.TotalSites > 0

	if hasSites {
		stats.AvgResponseMs = totalResponse / float64(stats.TotalSites)
		stats.AvgUptime = totalUptime / float64(stats.TotalSites)
	}

	return stats
}
