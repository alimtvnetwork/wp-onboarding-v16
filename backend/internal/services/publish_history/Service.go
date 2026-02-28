// Package publishhistory provides publish history persistence and querying
package publishhistory

import (
	"database/sql"
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
	Items []models.PublishHistory
	Total int
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
func (s *Service) Record(entry models.PublishHistory) (*models.PublishHistory, error) {
	result, err := s.db.Exec(insertHistorySql,
		entry.PluginId,
		entry.PluginName,
		entry.SiteId,
		entry.SiteName,
		entry.SiteUrl,
		entry.SessionId,
		entry.Status,
		entry.Mode,
		entry.FilesUpdated,
		entry.ActivationStatus,
		entry.RollbackStatus,
		entry.RollbackMessage,
		entry.ErrorMessage,
		entry.DurationMs,
	)
	if err != nil {

		return nil, apperror.Wrap(err, errDBWrite, "failed to record publish history")
	}

	id, _ := result.LastInsertId()
	entry.Id = id

	return &entry, nil
}

// List returns paginated publish history with optional filters
func (s *Service) List(limit, offset int, filters models.PublishHistoryFilters) apperror.Result[PublishHistoryListResult] {
	where, args := buildWhereClause(filters)

	total, countErr := s.countHistory(where, args)
	if countErr != nil {

		return apperror.Fail[PublishHistoryListResult](countErr)
	}

	entries, listErr := s.queryHistoryPage(where, args, limit, offset)
	if listErr != nil {

		return apperror.Fail[PublishHistoryListResult](listErr)
	}

	return apperror.Ok(PublishHistoryListResult{Items: entries, Total: total})
}

// countHistory returns the total count matching the where clause.
func (s *Service) countHistory(where string, args []any) (int, *apperror.AppError) {
	var total int
	countQuery := "SELECT COUNT(*) FROM PublishHistory" + where
	err := s.db.QueryRow(countQuery, args...).Scan(&total)

	if err != nil {

		return 0, apperror.Wrap(err, errDBRead, "failed to count publish history")
	}

	return total, nil
}

// queryHistoryPage fetches a page of publish history entries.
func (s *Service) queryHistoryPage(where string, args []any, limit, offset int) ([]models.PublishHistory, *apperror.AppError) {
	query := selectHistorySql + where + " ORDER BY CreatedAt DESC LIMIT ? OFFSET ?"
	allArgs := append(args, limit, offset)

	rows, err := s.db.Query(query, allArgs...)
	if err != nil {

		return nil, apperror.Wrap(err, errDBRead, "failed to list publish history")
	}
	defer rows.Close()

	return scanHistoryRows(rows, s.log), nil
}

// GetById returns a specific publish history entry
func (s *Service) GetById(id int64) apperror.Result[models.PublishHistory] {
	row := s.db.QueryRow(selectHistorySql+" WHERE Id = ?", id)

	m, err := scanHistoryRow(row)
	if err != nil {

		return apperror.FailWrap[models.PublishHistory](err, errDBRead, "publish history entry not found")
	}

	return apperror.Ok(m)
}

// GetStats returns aggregate publish statistics
func (s *Service) GetStats() apperror.Result[models.PublishHistoryStats] {
	var m models.PublishHistoryStats

	err := s.db.QueryRow(statsSql).Scan(
		&m.TotalPublishes,
		&m.SuccessCount,
		&m.FailureCount,
		&m.PartialCount,
		&m.AvgDurationMs,
		&m.TotalFilesUpdated,
		&m.LastPublishAt,
	)
	if err != nil {

		return apperror.FailWrap[models.PublishHistoryStats](err, errDBRead, "failed to get publish history stats")
	}

	return apperror.Ok(m)
}

// Delete removes a publish history entry
func (s *Service) Delete(id int64) error {
	_, err := s.db.Exec("DELETE FROM PublishHistory WHERE Id = ?", id)
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

	return apperror.Ok(count)
}

func buildWhereClause(f models.PublishHistoryFilters) (string, []any) {
	var conditions []string
	var args []any

	if f.PluginId > 0 {
		conditions = append(conditions, "PluginId = ?")
		args = append(args, f.PluginId)
	}

	if f.SiteId > 0 {
		conditions = append(conditions, "SiteId = ?")
		args = append(args, f.SiteId)
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

	isNoConditions := len(conditions) == 0

	if isNoConditions {

		return "", nil
	}

	return " WHERE " + strings.Join(conditions, " AND "), args
}
