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
)

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
func (s *Service) Save(input models.ErrorHistoryInput) (*models.ErrorHistory, error) {
	// Generate error ID if not provided
	if input.ErrorID == "" {
		input.ErrorID = fmt.Sprintf("%d-%s", time.Now().UnixMilli(), randomString(8))
	}

	// Marshal JSON fields
	contextJSON, _ := json.Marshal(input.Context)
	requestBodyJSON, _ := json.Marshal(input.RequestBody)
	phpStackFramesJSON, _ := json.Marshal(input.PHPStackFrames)
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
		input.ErrorID, input.Code, input.Level, input.Message, input.Details, string(contextJSON),
		input.StackTrace, input.Endpoint, input.Method, string(requestBodyJSON), input.ResponseStatus,
		input.SessionID, input.SessionType, string(phpStackFramesJSON), string(backendLogsJSON),
		input.BackendStackTrace, input.SiteURL, input.TriggerComponent, input.TriggerAction,
		string(invocationChainJSON), input.UIClickPath, input.MarkdownReport,
	)
	if err != nil {
		return nil, fmt.Errorf("insert error history: %w", err)
	}

	id, _ := result.LastInsertId()

	if s.log != nil {
		s.log.Debug("Error history saved", "errorId", input.ErrorID, "code", input.Code)
	}

	return &models.ErrorHistory{
		ID:      id,
		ErrorID: input.ErrorID,
		Code:    input.Code,
		Level:   input.Level,
		Message: input.Message,
	}, nil
}

// List returns error history with pagination and filters
func (s *Service) List(limit, offset int, filters models.ErrorHistoryFilters) ([]models.ErrorHistory, int, error) {
	if limit <= 0 {
		limit = 50
	}
	if limit > 500 {
		limit = 500
	}

	// Build WHERE clause
	var conditions []string
	var args []interface{}

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
		return nil, 0, fmt.Errorf("count error history: %w", err)
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
		return nil, 0, fmt.Errorf("query error history: %w", err)
	}
	defer rows.Close()

	var errors []models.ErrorHistory
	for rows.Next() {
		var e models.ErrorHistory
		var createdAt string
		var details, contextJSON, stackTrace, endpoint, method, requestBodyJSON sql.NullString
		var sessionID, sessionType, phpStackFramesJSON, backendLogsJSON, backendStackTrace sql.NullString
		var siteURL, triggerComponent, triggerAction, invocationChainJSON, uiClickPath, markdownReport sql.NullString
		var responseStatus sql.NullInt64

		err := rows.Scan(
			&e.ID, &e.ErrorID, &e.Code, &e.Level, &e.Message, &details, &contextJSON,
			&stackTrace, &endpoint, &method, &requestBodyJSON, &responseStatus,
			&sessionID, &sessionType, &phpStackFramesJSON, &backendLogsJSON,
			&backendStackTrace, &siteURL, &triggerComponent, &triggerAction,
			&invocationChainJSON, &uiClickPath, &markdownReport, &createdAt,
		)
		if err != nil {
			return nil, 0, fmt.Errorf("scan error history: %w", err)
		}

		e.Details = details.String
		e.ContextJSON = contextJSON.String
		e.StackTrace = stackTrace.String
		e.Endpoint = endpoint.String
		e.Method = method.String
		e.RequestBodyJSON = requestBodyJSON.String
		e.ResponseStatus = int(responseStatus.Int64)
		e.SessionID = sessionID.String
		e.SessionType = sessionType.String
		e.PHPStackFramesJSON = phpStackFramesJSON.String
		e.BackendLogsJSON = backendLogsJSON.String
		e.BackendStackTrace = backendStackTrace.String
		e.SiteURL = siteURL.String
		e.TriggerComponent = triggerComponent.String
		e.TriggerAction = triggerAction.String
		e.InvocationChainJSON = invocationChainJSON.String
		e.UIClickPath = uiClickPath.String
		e.MarkdownReport = markdownReport.String
		e.CreatedAt, _ = time.Parse("2006-01-02 15:04:05", createdAt)

		// Parse JSON fields
		e.ParseJSONFields()

		errors = append(errors, e)
	}

	return errors, total, nil
}

// GetByID returns a single error by ID
func (s *Service) GetByID(id int64) (*models.ErrorHistory, error) {
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
	var sessionID, sessionType, phpStackFramesJSON, backendLogsJSON, backendStackTrace sql.NullString
	var siteURL, triggerComponent, triggerAction, invocationChainJSON, uiClickPath, markdownReport sql.NullString
	var responseStatus sql.NullInt64

	err := s.db.QueryRow(query, id).Scan(
		&e.ID, &e.ErrorID, &e.Code, &e.Level, &e.Message, &details, &contextJSON,
		&stackTrace, &endpoint, &method, &requestBodyJSON, &responseStatus,
		&sessionID, &sessionType, &phpStackFramesJSON, &backendLogsJSON,
		&backendStackTrace, &siteURL, &triggerComponent, &triggerAction,
		&invocationChainJSON, &uiClickPath, &markdownReport, &createdAt,
	)
	if err == sql.ErrNoRows {
		return nil, fmt.Errorf("error not found: %d", id)
	}
	if err != nil {
		return nil, fmt.Errorf("query error history: %w", err)
	}

	e.Details = details.String
	e.ContextJSON = contextJSON.String
	e.StackTrace = stackTrace.String
	e.Endpoint = endpoint.String
	e.Method = method.String
	e.RequestBodyJSON = requestBodyJSON.String
	e.ResponseStatus = int(responseStatus.Int64)
	e.SessionID = sessionID.String
	e.SessionType = sessionType.String
	e.PHPStackFramesJSON = phpStackFramesJSON.String
	e.BackendLogsJSON = backendLogsJSON.String
	e.BackendStackTrace = backendStackTrace.String
	e.SiteURL = siteURL.String
	e.TriggerComponent = triggerComponent.String
	e.TriggerAction = triggerAction.String
	e.InvocationChainJSON = invocationChainJSON.String
	e.UIClickPath = uiClickPath.String
	e.MarkdownReport = markdownReport.String
	e.CreatedAt, _ = time.Parse("2006-01-02 15:04:05", createdAt)

	e.ParseJSONFields()

	return &e, nil
}

// GetByErrorID returns a single error by its frontend-generated error ID
func (s *Service) GetByErrorID(errorID string) (*models.ErrorHistory, error) {
	query := `SELECT Id FROM ErrorHistory WHERE ErrorId = ?`
	var id int64
	if err := s.db.QueryRow(query, errorID).Scan(&id); err != nil {
		if err == sql.ErrNoRows {
			return nil, fmt.Errorf("error not found: %s", errorID)
		}
		return nil, err
	}
	return s.GetByID(id)
}

// Delete removes an error from history
func (s *Service) Delete(id int64) error {
	result, err := s.db.Exec("DELETE FROM ErrorHistory WHERE Id = ?", id)
	if err != nil {
		return fmt.Errorf("delete error history: %w", err)
	}

	rows, _ := result.RowsAffected()
	if rows == 0 {
		return fmt.Errorf("error not found: %d", id)
	}

	if s.log != nil {
		s.log.Debug("Error history deleted", "id", id)
	}

	return nil
}

// Clear removes all error history
func (s *Service) Clear() (int64, error) {
	result, err := s.db.Exec("DELETE FROM ErrorHistory")
	if err != nil {
		return 0, fmt.Errorf("clear error history: %w", err)
	}

	deleted, _ := result.RowsAffected()

	if s.log != nil {
		s.log.Info("Error history cleared", "deleted", deleted)
	}

	return deleted, nil
}

// BulkExport generates a combined markdown report for multiple errors
func (s *Service) BulkExport(ids []int64) (string, error) {
	if len(ids) == 0 {
		return "", fmt.Errorf("no error IDs provided")
	}

	var reports []string
	for _, id := range ids {
		e, err := s.GetByID(id)
		if err != nil {
			continue
		}

		if e.MarkdownReport != "" {
			reports = append(reports, e.MarkdownReport)
		} else {
			// Generate basic report
			report := fmt.Sprintf("## Error %s\n\n**Code:** %s\n**Level:** %s\n**Message:** %s\n**Time:** %s\n",
				e.ErrorID, e.Code, e.Level, e.Message, e.CreatedAt.Format(time.RFC3339))
			if e.Details != "" {
				report += fmt.Sprintf("\n**Details:** %s\n", e.Details)
			}
			if e.StackTrace != "" {
				report += fmt.Sprintf("\n### Stack Trace\n```\n%s\n```\n", e.StackTrace)
			}
			reports = append(reports, report)
		}
	}

	return strings.Join(reports, "\n\n---\n\n"), nil
}

// GetStats returns error history statistics
func (s *Service) GetStats() (map[string]interface{}, error) {
	stats := make(map[string]interface{})

	// Total count
	var total int
	if err := s.db.QueryRow("SELECT COUNT(*) FROM ErrorHistory").Scan(&total); err != nil {
		return nil, err
	}
	stats["total"] = total

	// Count by level
	rows, err := s.db.Query("SELECT Level, COUNT(*) FROM ErrorHistory GROUP BY Level")
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	byLevel := make(map[string]int)
	for rows.Next() {
		var level string
		var count int
		rows.Scan(&level, &count)
		byLevel[level] = count
	}
	stats["byLevel"] = byLevel

	// Count by code (top 10)
	codeRows, err := s.db.Query("SELECT Code, COUNT(*) as cnt FROM ErrorHistory GROUP BY Code ORDER BY cnt DESC LIMIT 10")
	if err != nil {
		return nil, err
	}
	defer codeRows.Close()

	byCode := make(map[string]int)
	for codeRows.Next() {
		var code string
		var count int
		codeRows.Scan(&code, &count)
		byCode[code] = count
	}
	stats["byCode"] = byCode

	return stats, nil
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
