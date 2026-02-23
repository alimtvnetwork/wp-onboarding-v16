// Package publishhistory provides publish history persistence and querying
package publishhistory

import (
	"fmt"
	"strings"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
)

// Database error aliases for readability
const (
	errDBWrite = apperror.ErrDatabaseInsert
	errDBRead  = apperror.ErrDatabaseQuery
	errDBDel   = apperror.ErrDatabaseDelete
)

// PublishHistoryListResult wraps paginated list results
type PublishHistoryListResult struct {
	Items []models.PublishHistory `json:"items"`
	Total int                    `json:"total"`
}

// Config holds configuration for the publish history service
type Config struct {
	DB     *database.DB
	Logger *logger.Logger
}

// Service manages publish history records
type Service struct {
	db  *database.DB
	log *logger.Logger
}

// New creates a new publish history service
func New(cfg Config) *Service {
	return &Service{db: cfg.DB, log: cfg.Logger}
}

// Record saves a new publish history entry
func (s *Service) Record(entry models.PublishHistory) apperror.Result[models.PublishHistory] {
	result, err := s.db.Exec(`
		INSERT INTO PublishHistory (PluginID, PluginName, SiteID, SiteName, SiteURL, SessionID, Status, Mode, FilesUpdated, ActivationStatus, RollbackStatus, RollbackMessage, ErrorMessage, DurationMs)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		entry.PluginID, entry.PluginName, entry.SiteID, entry.SiteName, entry.SiteURL,
		entry.SessionID, entry.Status, entry.Mode, entry.FilesUpdated,
		entry.ActivationStatus, entry.RollbackStatus, entry.RollbackMessage,
		entry.ErrorMessage, entry.DurationMs,
	)
	if err != nil {
		return apperror.FailWrap[models.PublishHistory](err, errDBWrite, "failed to record publish history")
	}
	id, _ := result.LastInsertId()
	entry.ID = id
	return apperror.OK(entry)
}

// List returns paginated publish history with optional filters
func (s *Service) List(limit, offset int, filters models.PublishHistoryFilters) apperror.Result[PublishHistoryListResult] {
	where, args := buildWhereClause(filters)

	// Count total
	var total int
	countQuery := "SELECT COUNT(*) FROM PublishHistory" + where
	if err := s.db.QueryRow(countQuery, args...).Scan(&total); err != nil {
		return apperror.FailWrap[PublishHistoryListResult](err, errDBRead, "failed to count publish history")
	}

	// Fetch page
	query := "SELECT ID, PluginID, PluginName, SiteID, SiteName, SiteURL, SessionID, Status, Mode, FilesUpdated, ActivationStatus, RollbackStatus, RollbackMessage, ErrorMessage, DurationMs, CreatedAt FROM PublishHistory" + where + " ORDER BY CreatedAt DESC LIMIT ? OFFSET ?"
	allArgs := append(args, limit, offset)

	rows, err := s.db.Query(query, allArgs...)
	if err != nil {
		return apperror.FailWrap[PublishHistoryListResult](err, errDBRead, "failed to list publish history")
	}
	defer rows.Close()

	var entries []models.PublishHistory
	for rows.Next() {
		var e models.PublishHistory
		if err := rows.Scan(&e.ID, &e.PluginID, &e.PluginName, &e.SiteID, &e.SiteName, &e.SiteURL, &e.SessionID, &e.Status, &e.Mode, &e.FilesUpdated, &e.ActivationStatus, &e.RollbackStatus, &e.RollbackMessage, &e.ErrorMessage, &e.DurationMs, &e.CreatedAt); err != nil {
			s.log.Warn("Failed to scan publish history row", "error", err)
			continue
		}
		entries = append(entries, e)
	}
	return apperror.OK(PublishHistoryListResult{Items: entries, Total: total})
}

// GetById returns a specific publish history entry
func (s *Service) GetById(id int64) apperror.Result[models.PublishHistory] {
	var e models.PublishHistory
	err := s.db.QueryRow("SELECT ID, PluginID, PluginName, SiteID, SiteName, SiteURL, SessionID, Status, Mode, FilesUpdated, ActivationStatus, RollbackStatus, RollbackMessage, ErrorMessage, DurationMs, CreatedAt FROM PublishHistory WHERE ID = ?", id).
		Scan(&e.ID, &e.PluginID, &e.PluginName, &e.SiteID, &e.SiteName, &e.SiteURL, &e.SessionID, &e.Status, &e.Mode, &e.FilesUpdated, &e.ActivationStatus, &e.RollbackStatus, &e.RollbackMessage, &e.ErrorMessage, &e.DurationMs, &e.CreatedAt)
	if err != nil {
		return apperror.FailWrap[models.PublishHistory](err, errDBRead, "publish history entry not found")
	}
	return apperror.OK(e)
}

// GetStats returns aggregate publish statistics
func (s *Service) GetStats() apperror.Result[models.PublishHistoryStats] {
	var stats models.PublishHistoryStats
	err := s.db.QueryRow(`
		SELECT 
			COUNT(*),
			COALESCE(SUM(CASE WHEN Status = 'success' THEN 1 ELSE 0 END), 0),
			COALESCE(SUM(CASE WHEN Status = 'failed' THEN 1 ELSE 0 END), 0),
			COALESCE(SUM(CASE WHEN Status = 'partial' THEN 1 ELSE 0 END), 0),
			COALESCE(AVG(DurationMs), 0),
			COALESCE(SUM(FilesUpdated), 0),
			MAX(CreatedAt)
		FROM PublishHistory
	`).Scan(&stats.TotalPublishes, &stats.SuccessCount, &stats.FailureCount, &stats.PartialCount, &stats.AvgDurationMs, &stats.TotalFilesUpdated, &stats.LastPublishAt)
	if err != nil {
		return apperror.FailWrap[models.PublishHistoryStats](err, errDBRead, "failed to get publish history stats")
	}
	return apperror.OK(stats)
}

// Delete removes a publish history entry
func (s *Service) Delete(id int64) error {
	_, err := s.db.Exec("DELETE FROM PublishHistory WHERE ID = ?", id)
	if err != nil {
		return apperror.Wrap(err, errDBDel, "failed to delete publish history")
	}
	return nil
}

// Clear removes all publish history
func (s *Service) Clear() apperror.Result[int64] {
	result, err := s.db.Exec("DELETE FROM PublishHistory")
	if err != nil {
		return apperror.FailWrap[int64](err, errDBDel, "failed to clear publish history")
	}
	count, _ := result.RowsAffected()
	return apperror.OK(count)
}

func buildWhereClause(f models.PublishHistoryFilters) (string, []any) {
	var conditions []string
	var args []any

	if f.PluginID > 0 {
		conditions = append(conditions, "PluginID = ?")
		args = append(args, f.PluginID)
	}
	if f.SiteID > 0 {
		conditions = append(conditions, "SiteID = ?")
		args = append(args, f.SiteID)
	}
	if f.Status != "" {
		conditions = append(conditions, "Status = ?")
		args = append(args, f.Status)
	}
	if f.Search != "" {
		conditions = append(conditions, "(PluginName LIKE ? OR SiteName LIKE ? OR ErrorMessage LIKE ?)")
		search := fmt.Sprintf("%%%s%%", f.Search)
		args = append(args, search, search, search)
	}

	if len(conditions) == 0 {
		return "", nil
	}
	return " WHERE " + strings.Join(conditions, " AND "), args
}
