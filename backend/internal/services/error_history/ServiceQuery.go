// Package errorhistory — query and mutation operations.
package errorhistory

import (
	"database/sql"
	"fmt"
	"strings"
	"time"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
)

// GetById returns a single error by ID
func (s *Service) GetById(id int64) apperror.Result[models.ErrorHistory] {
	query := `
		SELECT Id, ErrorId, Code, Level, Message, Details, ContextJson,
			StackTrace, Endpoint, Method, RequestBodyJson, ResponseStatus,
			SessionId, SessionType, PhpStackFramesJson, BackendLogsJson,
			BackendStackTrace, SiteUrl, TriggerComponent, TriggerAction,
			InvocationChainJson, UiClickPath, MarkdownReport, CreatedAt
		FROM ErrorHistory WHERE Id = ?
	`

	scanned := scanNullFields{}

	err := s.db.QueryRow(query, id).Scan(
		&scanned.e.Id,
		&scanned.e.ErrorId,
		&scanned.e.Code,
		&scanned.e.Level,
		&scanned.e.Message,
		&scanned.details,
		&scanned.contextJson,
		&scanned.stackTrace,
		&scanned.endpoint,
		&scanned.method,
		&scanned.requestBodyJson,
		&scanned.responseStatus,
		&scanned.sessionId,
		&scanned.sessionType,
		&scanned.phpStackFramesJson,
		&scanned.backendLogsJson,
		&scanned.backendStackTrace,
		&scanned.siteUrl,
		&scanned.triggerComponent,
		&scanned.triggerAction,
		&scanned.invocationChainJson,
		&scanned.uiClickPath,
		&scanned.markdownReport,
		&scanned.createdAt,
	)
	if err == sql.ErrNoRows {
		appErr := apperror.New(apperror.ErrNotFound, "error not found").
			WithValue("id", fmt.Sprintf("%d", id))

		return apperror.Fail[models.ErrorHistory](appErr)
	}
	if err != nil {
		appErr := apperror.Wrap(err, apperror.ErrDatabaseQuery, "query error history").
			WithValue("id", fmt.Sprintf("%d", id))

		return apperror.Fail[models.ErrorHistory](appErr)
	}

	populateFromNullFields(&scanned)
	scanned.e.ParseJsonFields()

	return apperror.Ok(scanned.e)
}

// GetByErrorId returns a single error by its frontend-generated error ID
func (s *Service) GetByErrorId(errorId string) apperror.Result[models.ErrorHistory] {
	query := `SELECT Id FROM ErrorHistory WHERE ErrorId = ?`
	var id int64

	err := s.db.QueryRow(query, errorId).Scan(&id)
	if err != nil {
		if err == sql.ErrNoRows {
			appErr := apperror.New(apperror.ErrNotFound, "error not found").
				WithValue("errorId", errorId)

			return apperror.Fail[models.ErrorHistory](appErr)
		}

		appErr := apperror.Wrap(err, apperror.ErrDatabaseQuery, "query error by error ID").
			WithValue("errorId", errorId)

		return apperror.Fail[models.ErrorHistory](appErr)
	}

	return s.GetById(id)
}

// Delete removes an error from history
func (s *Service) Delete(id int64) *apperror.AppError {
	result, err := s.db.Exec("DELETE FROM ErrorHistory WHERE Id = ?", id)

	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseDelete, "failed to delete error history")
	}

	rows, _ := result.RowsAffected()
	isFound := rows > 0
	isMissing := !isFound

	if isMissing {
		return apperror.New(apperror.ErrNotFound, "error history entry not found").
			WithValue("id", fmt.Sprintf("%d", id))
	}

	hasLogger := s.log != nil

	if hasLogger {
		s.log.Debug("Error history deleted", "id", id)
	}

	return nil
}

// Clear removes all error history
func (s *Service) Clear() apperror.Result[int64] {
	result, err := s.db.Exec("DELETE FROM ErrorHistory")
	if err != nil {
		return apperror.FailWrap[int64](err, apperror.ErrDatabaseQuery, "clear error history")
	}

	deleted, _ := result.RowsAffected()

	if s.log != nil {
		s.log.Info("Error history cleared", "deleted", deleted)
	}

	return apperror.Ok(deleted)
}

// ClearOlderThan removes error history entries older than the given duration string.
// Accepted values: "1h", "6h", "24h", "7d", "30d".
func (s *Service) ClearOlderThan(threshold string) apperror.Result[int64] {
	duration, parseErr := parseDurationThreshold(threshold)
	if parseErr != nil {
		return apperror.FailWrap[int64](parseErr, apperror.ErrValidation, "invalid threshold")
	}

	cutoff := time.Now().Add(-duration)

	result, err := s.db.Exec("DELETE FROM ErrorHistory WHERE CreatedAt < ?", cutoff.UTC().Format("2006-01-02 15:04:05"))
	if err != nil {
		return apperror.FailWrap[int64](err, apperror.ErrDatabaseQuery, "clear old error history")
	}

	deleted, _ := result.RowsAffected()

	if s.log != nil {
		s.log.Info("Old error history cleared", "threshold", threshold, "cutoff", cutoff.Format(time.RFC3339), "deleted", deleted)
	}

	return apperror.Ok(deleted)
}

// parseDurationThreshold converts a human-readable threshold like "7d" or "24h" into a time.Duration.
func parseDurationThreshold(s string) (time.Duration, error) {
	if len(s) < 2 {
		return 0, fmt.Errorf("threshold too short: %q", s)
	}

	unit := s[len(s)-1]
	value := s[:len(s)-1]

	var num int
	_, err := fmt.Sscanf(value, "%d", &num)
	if err != nil || num <= 0 {
		return 0, fmt.Errorf("invalid threshold number: %q", value)
	}

	switch unit {
	case 'h':
		return time.Duration(num) * time.Hour, nil
	case 'd':
		return time.Duration(num) * 24 * time.Hour, nil
	default:
		return 0, fmt.Errorf("unsupported threshold unit %q, use 'h' or 'd'", string(unit))
	}
}

// BulkExport generates a combined markdown report for multiple errors
func (s *Service) BulkExport(ids []int64) apperror.Result[string] {
	if len(ids) == 0 {
		return apperror.FailNew[string](apperror.ErrValidation, "no error IDs provided")
	}

	var reports []string

	for _, id := range ids {
		result := s.GetById(id)
		if result.HasError() {
			continue
		}

		e := result.Value()

		if e.MarkdownReport != "" {
			reports = append(reports, e.MarkdownReport)
		} else {
			report := fmt.Sprintf("## Error %s\n\n**Code:** %s\n**Level:** %s\n**Message:** %s\n**Time:** %s\n",
				e.ErrorId, e.Code, e.Level, e.Message, e.CreatedAt.Format(time.RFC3339))
			if e.Details != "" {
				report += fmt.Sprintf("\n**Details:** %s\n", e.Details)
			}
			if e.StackTrace != "" {
				report += fmt.Sprintf("\n### Stack Trace\n```\n%s\n```\n", e.StackTrace)
			}

			reports = append(reports, report)
		}
	}

	return apperror.Ok(strings.Join(reports, "\n\n---\n\n"))
}

// GetStats returns error history statistics
func (s *Service) GetStats() apperror.Result[models.ErrorHistoryStats] {
	stats := models.ErrorHistoryStats{
		ByLevel: make(map[string]int),
		ByCode:  make(map[string]int),
	}

	err := s.db.QueryRow("SELECT COUNT(*) FROM ErrorHistory").Scan(&stats.Total)
	if err != nil {
		return apperror.FailWrap[models.ErrorHistoryStats](err, apperror.ErrDatabaseQuery, "count error history total")
	}

	// Count by level
	rows, err := s.db.Query("SELECT Level, COUNT(*) FROM ErrorHistory GROUP BY Level")
	if err != nil {
		return apperror.FailWrap[models.ErrorHistoryStats](err, apperror.ErrDatabaseQuery, "count error history by level")
	}
	defer rows.Close()

	for rows.Next() {
		var level string
		var count int
		rows.Scan(&level, &count)
		stats.ByLevel[level] = count
	}

	// Count by code (top 10)
	codeRows, err := s.db.Query("SELECT Code, COUNT(*) as cnt FROM ErrorHistory GROUP BY Code ORDER BY cnt DESC LIMIT 10")
	if err != nil {
		return apperror.FailWrap[models.ErrorHistoryStats](err, apperror.ErrDatabaseQuery, "count error history by code")
	}
	defer codeRows.Close()

	for codeRows.Next() {
		var code string
		var count int
		codeRows.Scan(&code, &count)
		stats.ByCode[code] = count
	}

	return apperror.Ok(stats)
}
