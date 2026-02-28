// Package publishhistory — row scanning for publish history records.
package publishhistory

import (
	"database/sql"

	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
)

// scanHistoryRows scans all rows into PublishHistory entries.
func scanHistoryRows(rows *sql.Rows, log *logger.Logger) []models.PublishHistory {
	var entries []models.PublishHistory

	for rows.Next() {
		m, err := scanHistoryRowFromRows(rows)
		if err != nil {
			log.Warn("Failed to scan publish history row", "error", err)

			continue
		}

		entries = append(entries, m)
	}

	return entries
}

// scanHistoryRow scans a single *sql.Row into a PublishHistory.
func scanHistoryRow(row *sql.Row) (models.PublishHistory, error) {
	var m models.PublishHistory

	err := row.Scan(
		&m.Id,
		&m.PluginId,
		&m.PluginName,
		&m.SiteId,
		&m.SiteName,
		&m.SiteUrl,
		&m.SessionId,
		&m.Status,
		&m.Mode,
		&m.FilesUpdated,
		&m.ActivationStatus,
		&m.RollbackStatus,
		&m.RollbackMessage,
		&m.ErrorMessage,
		&m.DurationMs,
		&m.CreatedAt,
	)

	return m, err
}

// scanHistoryRowFromRows scans a single row from *sql.Rows into a PublishHistory.
func scanHistoryRowFromRows(rows *sql.Rows) (models.PublishHistory, error) {
	var m models.PublishHistory

	err := rows.Scan(
		&m.Id,
		&m.PluginId,
		&m.PluginName,
		&m.SiteId,
		&m.SiteName,
		&m.SiteUrl,
		&m.SessionId,
		&m.Status,
		&m.Mode,
		&m.FilesUpdated,
		&m.ActivationStatus,
		&m.RollbackStatus,
		&m.RollbackMessage,
		&m.ErrorMessage,
		&m.DurationMs,
		&m.CreatedAt,
	)

	return m, err
}
