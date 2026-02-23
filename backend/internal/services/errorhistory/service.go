// Package errorhistory provides persistent error/notification storage
package errorhistory

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"strings"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
)

// ErrorHistoryListResult wraps paginated results with total count
type ErrorHistoryListResult struct {
	Items []models.ErrorHistory `json:"items"`
	Total int                   `json:"total"`
}

// Config holds error history service configuration
type Config struct {
	DB     *database.DB
	Logger *logger.Logger
}

// Service manages error history persistence
type Service struct {
	db  *database.DB
	log *logger.Logger
}

// New creates a new error history service
func New(cfg Config) *Service {
	return &Service{
		db:  cfg.DB,
		log: cfg.Logger,
	}
}

// Save persists an error to the database
func (s *Service) Save(input models.ErrorHistoryInput) apperror.Result[models.ErrorHistory] {
	// Generate error ID if not provided
	if input.ErrorId == "" {
		input.ErrorId = fmt.Sprintf("%d-%s", time.Now().UnixMilli(), randomString(8))
	}

	// Marshal JSON fields
	contextJSON, _ := json.Marshal(input.Context)
	requestBodyJSON, _ := json.Marshal(input.RequestBody)
	phpStackFramesJSON, _ := json.Marshal(input.PhpStackFrames)
	backendLogsJSON, _ := json.Marshal(input.BackendLogs)
	invocationChainJSON, _ := json.Marshal(input.InvocationChain)

	query := `
		INSERT INTO ErrorHistory (
			ErrorId, Code, Level, Message, Details, ContextJson,
			StackTrace, Endpoint, Method, RequestBodyJson, ResponseStatus,
			SessionId, SessionType, PhpStackFramesJson, BackendLogsJson,
			BackendStackTrace, SiteUrl, TriggerComponent, TriggerAction,
			InvocationChainJson, UiClickPath, MarkdownReport
		) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
	`

	result, err := s.db.Exec(query,
		input.ErrorId, input.Code, input.Level, input.Message, input.Details, string(contextJSON),
		input.StackTrace, input.Endpoint, input.Method, string(requestBodyJSON), input.ResponseStatus,
		input.SessionId, input.SessionType, string(phpStackFramesJSON), string(backendLogsJSON),
		input.BackendStackTrace, input.SiteUrl, input.TriggerComponent, input.TriggerAction,
		string(invocationChainJSON), input.UIClickPath, input.MarkdownReport,
	)
	if err != nil {
		return apperror.FailWrap[models.ErrorHistory](err, apperror.ErrDatabaseQuery, "insert error history").
			WithValue("errorId", input.ErrorId)
	}

	id, _ := result.LastInsertId()

	if s.log != nil {
		s.log.Debug("Error history saved", "errorId", input.ErrorId, "code", input.Code)
	}

	return apperror.Ok(models.ErrorHistory{
		Id:      id,
		ErrorId: input.ErrorId,
		Code:    input.Code,
		Level:   input.Level,
		Message: input.Message,
	})
}

// List returns error history with pagination and filters
func (s *Service) List(limit, offset int, filters models.ErrorHistoryFilters) apperror.Result[ErrorHistoryListResult] {
	if limit <= 0 {
		limit = 50
	}
	if limit > 500 {
		limit = 500
	}

	// Build WHERE clause
	var conditions []string
	var args []any

	if filters.Code != "" {
		conditions = append(conditions, "Code = ?")
		args = append(args, filters.Code)
	}
	if filters.Level != "" {
		conditions = append(conditions, "Level = ?")
		args = append(args, filters.Level)
	}
	if filters.StartDate != "" {
		conditions = append(conditions, "CreatedAt >= ?")
		args = append(args, filters.StartDate)
	}
	if filters.EndDate != "" {
		conditions = append(conditions, "CreatedAt <= ?")
		args = append(args, filters.EndDate)
	}
	if filters.Search != "" {
		conditions = append(conditions, "(Message LIKE ? OR Details LIKE ? OR Code LIKE ?)")
		searchPattern := "%" + filters.Search + "%"
		args = append(args, searchPattern, searchPattern, searchPattern)
	}

	whereClause := ""
	if len(conditions) > 0 {
		whereClause = "WHERE " + strings.Join(conditions, " AND ")
	}

	// Get total count
	var total int
	countQuery := fmt.Sprintf("SELECT COUNT(*) FROM ErrorHistory %s", whereClause)
	if err := s.db.QueryRow(countQuery, args...).Scan(&total); err != nil {
		return apperror.FailWrap[ErrorHistoryListResult](err, apperror.ErrDatabaseQuery, "count error history")
	}

	// Get paginated results
	query := fmt.Sprintf(`
		SELECT Id, ErrorId, Code, Level, Message, Details, ContextJson,
			StackTrace, Endpoint, Method, RequestBodyJson, ResponseStatus,
			SessionId, SessionType, PhpStackFramesJson, BackendLogsJson,
			BackendStackTrace, SiteUrl, TriggerComponent, TriggerAction,
			InvocationChainJson, UiClickPath, MarkdownReport, CreatedAt
		FROM ErrorHistory %s
		ORDER BY CreatedAt DESC
		LIMIT ? OFFSET ?
	`, whereClause)

	args = append(args, limit, offset)
	rows, err := s.db.Query(query, args...)
	if err != nil {
		return apperror.FailWrap[ErrorHistoryListResult](err, apperror.ErrDatabaseQuery, "query error history")
	}
	defer rows.Close()

	var errors []models.ErrorHistory
	for rows.Next() {
		e, err := scanErrorHistoryRow(rows)
		if err != nil {
			return apperror.FailWrap[ErrorHistoryListResult](err, apperror.ErrDatabaseQuery, "scan error history")
		}
		errors = append(errors, e)
	}

	return apperror.Ok(ErrorHistoryListResult{
		Items: errors,
		Total: total,
	})
}

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

	var e models.ErrorHistory
	var createdAt string
	var details, contextJSON, stackTrace, endpoint, method, requestBodyJSON sql.NullString
	var sessionId, sessionType, phpStackFramesJson, backendLogsJSON, backendStackTrace sql.NullString
	var siteUrl, triggerComponent, triggerAction, invocationChainJSON, uiClickPath, markdownReport sql.NullString
	var responseStatus sql.NullInt64

	err := s.db.QueryRow(query, id).Scan(
		&e.Id, &e.ErrorId, &e.Code, &e.Level, &e.Message, &details, &contextJSON,
		&stackTrace, &endpoint, &method, &requestBodyJSON, &responseStatus,
		&sessionId, &sessionType, &phpStackFramesJson, &backendLogsJSON,
		&backendStackTrace, &siteUrl, &triggerComponent, &triggerAction,
		&invocationChainJSON, &uiClickPath, &markdownReport, &createdAt,
	)
	if err == sql.ErrNoRows {
		return apperror.FailNew[models.ErrorHistory](apperror.ErrNotFound, "error not found").
			WithValue("id", fmt.Sprintf("%d", id))
	}
	if err != nil {
		return apperror.FailWrap[models.ErrorHistory](err, apperror.ErrDatabaseQuery, "query error history").
			WithValue("id", fmt.Sprintf("%d", id))
	}

	populateErrorHistoryFields(&e, details, contextJSON, stackTrace, endpoint, method, requestBodyJSON,
		responseStatus, sessionId, sessionType, phpStackFramesJson, backendLogsJSON, backendStackTrace,
		siteUrl, triggerComponent, triggerAction, invocationChainJSON, uiClickPath, markdownReport, createdAt)

	e.ParseJsonFields()

	return apperror.Ok(e)
}

// GetByErrorId returns a single error by its frontend-generated error ID
func (s *Service) GetByErrorId(errorId string) apperror.Result[models.ErrorHistory] {
	query := `SELECT Id FROM ErrorHistory WHERE ErrorId = ?`
	var id int64
	if err := s.db.QueryRow(query, errorId).Scan(&id); err != nil {
		if err == sql.ErrNoRows {
			return apperror.FailNew[models.ErrorHistory](apperror.ErrNotFound, "error not found").
				WithValue("errorId", errorId)
		}
		return apperror.FailWrap[models.ErrorHistory](err, apperror.ErrDatabaseQuery, "query error by error ID").
			WithValue("errorId", errorId)
	}
	return s.GetById(id)
}

// Delete removes an error from history
func (s *Service) Delete(id int64) error {
	result, err := s.db.Exec("DELETE FROM ErrorHistory WHERE Id = ?", id)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseDelete, "failed to delete error history")
	}

	rows, _ := result.RowsAffected()
	if rows == 0 {
		return apperror.New(apperror.ErrNotFound, "error history entry not found").
			WithValue("id", fmt.Sprintf("%d", id))
	}

	if s.log != nil {
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
			// Generate basic report
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

	// Total count
	if err := s.db.QueryRow("SELECT COUNT(*) FROM ErrorHistory").Scan(&stats.Total); err != nil {
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

// scanErrorHistoryRow scans a single row from a rows iterator into a models.ErrorHistory
func scanErrorHistoryRow(rows *sql.Rows) (models.ErrorHistory, error) {
	var e models.ErrorHistory
	var createdAt string
	var details, contextJSON, stackTrace, endpoint, method, requestBodyJSON sql.NullString
	var sessionId, sessionType, phpStackFramesJson, backendLogsJSON, backendStackTrace sql.NullString
	var siteUrl, triggerComponent, triggerAction, invocationChainJSON, uiClickPath, markdownReport sql.NullString
	var responseStatus sql.NullInt64

	err := rows.Scan(
		&e.Id, &e.ErrorId, &e.Code, &e.Level, &e.Message, &details, &contextJSON,
		&stackTrace, &endpoint, &method, &requestBodyJSON, &responseStatus,
		&sessionId, &sessionType, &phpStackFramesJson, &backendLogsJSON,
		&backendStackTrace, &siteUrl, &triggerComponent, &triggerAction,
		&invocationChainJSON, &uiClickPath, &markdownReport, &createdAt,
	)
	if err != nil {
		return e, err
	}

	populateErrorHistoryFields(&e, details, contextJSON, stackTrace, endpoint, method, requestBodyJSON,
		responseStatus, sessionId, sessionType, phpStackFramesJson, backendLogsJSON, backendStackTrace,
		siteUrl, triggerComponent, triggerAction, invocationChainJSON, uiClickPath, markdownReport, createdAt)

	e.ParseJsonFields()

	return e, nil
}

// populateErrorHistoryFields assigns nullable SQL fields to the ErrorHistory struct
func populateErrorHistoryFields(e *models.ErrorHistory,
	details, contextJSON, stackTrace, endpoint, method, requestBodyJSON sql.NullString,
	responseStatus sql.NullInt64,
	sessionId, sessionType, phpStackFramesJson, backendLogsJSON, backendStackTrace sql.NullString,
	siteUrl, triggerComponent, triggerAction, invocationChainJSON, uiClickPath, markdownReport sql.NullString,
	createdAt string,
) {
	e.Details = details.String
	e.ContextJSON = contextJSON.String
	e.StackTrace = stackTrace.String
	e.Endpoint = endpoint.String
	e.Method = method.String
	e.RequestBodyJSON = requestBodyJSON.String
	e.ResponseStatus = int(responseStatus.Int64)
	e.SessionId = sessionId.String
	e.SessionType = sessionType.String
	e.PhpStackFramesJson = phpStackFramesJson.String
	e.BackendLogsJSON = backendLogsJSON.String
	e.BackendStackTrace = backendStackTrace.String
	e.SiteUrl = siteUrl.String
	e.TriggerComponent = triggerComponent.String
	e.TriggerAction = triggerAction.String
	e.InvocationChainJSON = invocationChainJSON.String
	e.UIClickPath = uiClickPath.String
	e.MarkdownReport = markdownReport.String
	e.CreatedAt, _ = time.Parse("2006-01-02 15:04:05", createdAt)
}

// randomString generates a random string for error IDs
func randomString(n int) string {
	const letters = "abcdefghijklmnopqrstuvwxyz0123456789"
	b := make([]byte, n)
	for i := range b {
		b[i] = letters[time.Now().UnixNano()%int64(len(letters))]
	}
	return string(b)
}
