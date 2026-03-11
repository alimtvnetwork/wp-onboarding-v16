package services

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"strings"

	"riseup-licensing/internal/enums/auditactiontype"
	"riseup-licensing/internal/models"
	"riseup-licensing/pkg/apperror"
)

// AuditService manages audit log entries.
type AuditService struct {
	db *sql.DB
}

// NewAuditService creates a new AuditService.
func NewAuditService(db *sql.DB) *AuditService {
	return &AuditService{db: db}
}

// LogInput holds parameters for recording an audit entry.
type LogInput struct {
	LicenseId *int64
	Action    auditactiontype.Variant
	Domain    string
	IpAddress string
	Details   any
}

// Log records an audit trail entry.
func (s *AuditService) Log(input LogInput) *apperror.AppError {
	detailsJson, marshalErr := marshalDetails(input.Details)
	if marshalErr != nil {

		return apperror.Wrap(marshalErr, apperror.ErrMarshal, "marshal audit details")
	}

	_, execErr := s.db.Exec(auditInsertSql, input.LicenseId, input.Action.String(), input.Domain, input.IpAddress, detailsJson)
	if execErr != nil {

		return apperror.Wrap(execErr, apperror.ErrDatabaseInsert, "insert audit log")
	}

	return nil
}

// ListFilter holds optional filters for listing audit logs.
type ListFilter struct {
	Action    *auditaction.Variant
	LicenseId *int64
}

// List returns audit log entries matching the given filters.
func (s *AuditService) List(filter ListFilter) apperror.Result[[]models.AuditLog] {
	query, args := buildAuditListQuery(filter)

	rows, queryErr := s.db.Query(query, args...)
	if queryErr != nil {

		return apperror.FailWrap[[]models.AuditLog](queryErr, apperror.ErrDatabaseQuery, "query audit logs")
	}
	defer rows.Close()

	return scanAuditLogRows(rows)
}

// buildAuditListQuery constructs the audit list query with optional filters.
func buildAuditListQuery(filter ListFilter) (string, []any) {
	var clauses []string
	var args []any

	if filter.LicenseId != nil {
		clauses = append(clauses, "license_id = ?")
		args = append(args, *filter.LicenseId)
	}

	if filter.Action != nil {
		clauses = append(clauses, "action = ?")
		args = append(args, filter.Action.String())
	}

	hasClauses := len(clauses) > 0

	if hasClauses {

		return auditSelectSql + " WHERE " + strings.Join(clauses, " AND ") + " ORDER BY created_at DESC", args
	}

	return auditListSql, args
}

// marshalDetails converts audit details to JSON, or nil if no details.
func marshalDetails(details any) ([]byte, error) {
	isNilDetails := details == nil

	if isNilDetails {

		return nil, nil
	}

	data, marshalErr := json.Marshal(details)
	if marshalErr != nil {

		return nil, fmt.Errorf("marshal audit details: %w", marshalErr)
	}

	return data, nil
}
