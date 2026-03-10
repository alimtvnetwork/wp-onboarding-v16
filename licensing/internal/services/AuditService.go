package services

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"strings"

	"riseup-licensing/internal/enums/auditaction"
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
	Action    auditaction.Variant
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
