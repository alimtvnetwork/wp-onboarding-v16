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

// Service manages publish history records
type Service struct {
	db  *database.DB
	log *logger.Logger
}

// New creates a new publish history service
func New(db *database.DB, log *logger.Logger) *Service {
	return &Service{db: db, log: log}
}

// Record saves a new publish history entry
func (s *Service) Record(entry models.PublishHistory) (*models.PublishHistory, error) {
	result, err := s.db.Exec(`
		INSERT INTO PublishHistory (PluginID, PluginName, SiteID, SiteName, SiteURL, SessionID, Status, Mode, FilesUpdated, ActivationStatus, RollbackStatus, RollbackMessage, ErrorMessage, DurationMs)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		entry.PluginID, entry.PluginName, entry.SiteID, entry.SiteName, entry.SiteURL,
		entry.SessionID, entry.Status, entry.Mode, entry.FilesUpdated,
		entry.ActivationStatus, entry.RollbackStatus, entry.RollbackMessage,
		entry.ErrorMessage, entry.DurationMs,
	)
	if err != nil {
		return nil, apperror.Wrap(err, errDBWrite, "failed to record publish history")
	}
	id, _ := result.LastInsertId()
	entry.ID = id
	return &entry, nil
}

// List returns paginated publish history with optional filters
func (s *Service) List(limit, offset int, filters models.PublishHistoryFilters) ([]models.PublishHistory, int, error) {
	where, args := buildWhereClause(filters)

	// Count total
	var total int
	countQuery := "SELECT COUNT(*) FROM PublishHistory" + where
	if err := s.db.QueryRow(countQuery, args...).Scan(&total); err != nil {
		return nil, 0, apperror.Wrap(err, errDBRead, "failed to count publish history")
	}

	// Fetch page
	query := "SELECT ID, PluginID, PluginName, SiteID, SiteName, SiteURL, SessionID, Status, Mode, FilesUpdated, ActivationStatus, RollbackStatus, RollbackMessage, ErrorMessage, DurationMs, CreatedAt FROM PublishHistory" + where + " ORDER BY CreatedAt DESC LIMIT ? OFFSET ?"
	allArgs := append(args, limit, offset)

	rows, err := s.db.Query(query, allArgs...)
	if err != nil {
		return nil, 0, apperror.Wrap(err, errDBRead, "failed to list publish history")
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
	return entries, total, nil
}

// GetByID returns a specific publish history entry
func (s *Service) GetByID(id int64) (*models.PublishHistory, error) {
	var e models.PublishHistory
	err := s.db.QueryRow("SELECT ID, PluginID, PluginName, SiteID, SiteName, SiteURL, SessionID, Status, Mode, FilesUpdated, ActivationStatus, RollbackStatus, RollbackMessage, ErrorMessage, DurationMs, CreatedAt FROM PublishHistory WHERE ID = ?", id).
		Scan(&e.ID, &e.PluginID, &e.PluginName, &e.SiteID, &e.SiteName, &e.SiteURL, &e.SessionID, &e.Status, &e.Mode, &e.FilesUpdated, &e.ActivationStatus, &e.RollbackStatus, &e.RollbackMessage, &e.ErrorMessage, &e.DurationMs, &e.CreatedAt)
	if err != nil {
		return nil, apperror.Wrap(err, errDBRead, "publish history entry not found")
	}
	return &e, nil
}

// GetStats returns aggregate publish statistics
func (s *Service) GetStats() (*models.PublishHistoryStats, error) {
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
		return nil, apperror.Wrap(err, errDBRead, "failed to get publish history stats")
	}
	return &stats, nil
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
func (s *Service) Clear() (int64, error) {
	result, err := s.db.Exec("DELETE FROM PublishHistory")
	if err != nil {
		return 0, apperror.Wrap(err, errDBDel, "failed to clear publish history")
	}
	count, _ := result.RowsAffected()
	return count, nil
}

func buildWhereClause(f models.PublishHistoryFilters) (string, []interface{}) {
	var conditions []string
	var args []interface{}

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
