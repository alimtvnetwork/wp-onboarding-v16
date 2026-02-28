// Package errorhistory provides persistent error/notification storage
package errorhistory

import (
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
	Items []models.ErrorHistory
	Total int
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
	if input.ErrorId == "" {
		input.ErrorId = fmt.Sprintf("%d-%s", time.Now().UnixMilli(), randomString(8))
	}

	contextJson, _ := json.Marshal(input.Context)
	requestBodyJson, _ := json.Marshal(input.RequestBody)
	phpStackFramesJson, _ := json.Marshal(input.PhpStackFrames)
	backendLogsJson, _ := json.Marshal(input.BackendLogs)
	invocationChainJson, _ := json.Marshal(input.InvocationChain)

	query := `
		INSERT INTO ErrorHistory (
			ErrorId, Code, Level, Message, Details, ContextJson,
			StackTrace, Endpoint, Method, RequestBodyJson, ResponseStatus,
			SessionId, SessionType, PhpStackFramesJson, BackendLogsJson,
			BackendStackTrace, SiteUrl, TriggerComponent, TriggerAction,
			InvocationChainJson, UiClickPath, MarkdownReport
		) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
	`

	result, err := s.db.Exec(
		query,
		input.ErrorId,
		input.Code,
		input.Level,
		input.Message,
		input.Details,
		string(contextJson),
		input.StackTrace,
		input.Endpoint,
		input.Method,
		string(requestBodyJson),
		input.ResponseStatus,
		input.SessionId,
		input.SessionType,
		string(phpStackFramesJson),
		string(backendLogsJson),
		input.BackendStackTrace,
		input.SiteUrl,
		input.TriggerComponent,
		input.TriggerAction,
		string(invocationChainJson),
		input.UIClickPath,
		input.MarkdownReport,
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
func (s *Service) List(
	limit int,
	offset int,
	filters models.ErrorHistoryFilters,
) apperror.Result[ErrorHistoryListResult] {
	isLimitUnset := limit <= 0

	if isLimitUnset {
		limit = 50
	}

	isLimitExcessive := limit > 500

	if isLimitExcessive {
		limit = 500
	}

	whereClause, args := buildFilterClause(filters)

	// Get total count
	var total int
	countQuery := fmt.Sprintf("SELECT COUNT(*) FROM ErrorHistory %s", whereClause)

	err := s.db.QueryRow(countQuery, args...).Scan(&total)
	if err != nil {
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
		e, scanErr := scanErrorHistoryRow(rows)
		if scanErr != nil {
			return apperror.FailWrap[ErrorHistoryListResult](scanErr, apperror.ErrDatabaseQuery, "scan error history")
		}

		errors = append(errors, e)
	}

	return apperror.Ok(ErrorHistoryListResult{
		Items: errors,
		Total: total,
	})
}

// buildFilterClause constructs a WHERE clause from filters.
func buildFilterClause(filters models.ErrorHistoryFilters) (string, []any) {
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

	return whereClause, args
}
