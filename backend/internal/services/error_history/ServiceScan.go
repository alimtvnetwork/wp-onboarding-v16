// Package errorhistory — row scanning and field population helpers.
package errorhistory

import (
	"database/sql"
	"time"

	"wp-plugin-publish/internal/models"
)

// scanNullFields bundles nullable SQL fields for scanning a full ErrorHistory row.
type scanNullFields struct {
	e                   models.ErrorHistory
	details             sql.NullString
	contextJson         sql.NullString
	stackTrace          sql.NullString
	endpoint            sql.NullString
	method              sql.NullString
	requestBodyJson     sql.NullString
	responseStatus      sql.NullInt64
	sessionId           sql.NullString
	sessionType         sql.NullString
	phpStackFramesJson  sql.NullString
	backendLogsJson     sql.NullString
	backendStackTrace   sql.NullString
	siteUrl             sql.NullString
	triggerComponent    sql.NullString
	triggerAction       sql.NullString
	invocationChainJson sql.NullString
	uiClickPath         sql.NullString
	markdownReport      sql.NullString
	createdAt           string
}

// scanErrorHistoryRow scans a single row from a rows iterator into a models.ErrorHistory
func scanErrorHistoryRow(rows *sql.Rows) (models.ErrorHistory, error) {
	scanned := scanNullFields{}

	err := rows.Scan(
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
	if err != nil {
		return scanned.e, err
	}

	populateFromNullFields(&scanned)
	scanned.e.ParseJsonFields()

	return scanned.e, nil
}

// populateFromNullFields assigns nullable SQL fields to the ErrorHistory struct.
func populateFromNullFields(s *scanNullFields) {
	s.e.Details = s.details.String
	s.e.ContextJson = s.contextJson.String
	s.e.StackTrace = s.stackTrace.String
	s.e.Endpoint = s.endpoint.String
	s.e.Method = s.method.String
	s.e.RequestBodyJson = s.requestBodyJson.String
	s.e.ResponseStatus = int(s.responseStatus.Int64)
	s.e.SessionId = s.sessionId.String
	s.e.SessionType = s.sessionType.String
	s.e.PhpStackFramesJson = s.phpStackFramesJson.String
	s.e.BackendLogsJson = s.backendLogsJson.String
	s.e.BackendStackTrace = s.backendStackTrace.String
	s.e.SiteUrl = s.siteUrl.String
	s.e.TriggerComponent = s.triggerComponent.String
	s.e.TriggerAction = s.triggerAction.String
	s.e.InvocationChainJson = s.invocationChainJson.String
	s.e.UIClickPath = s.uiClickPath.String
	s.e.MarkdownReport = s.markdownReport.String
	s.e.CreatedAt, _ = time.Parse("2006-01-02 15:04:05", s.createdAt)
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
