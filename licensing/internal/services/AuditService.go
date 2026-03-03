package services

import (
	"database/sql"
	"encoding/json"
	"fmt"

	"riseup-licensing/internal/enums/auditaction"
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
func (s *AuditService) Log(input LogInput) error {
	detailsJson, marshalErr := marshalDetails(input.Details)
	if marshalErr != nil {

		return marshalErr
	}

	query := `INSERT INTO audit_log (license_id, action, domain, ip_address, details) VALUES (?, ?, ?, ?, ?)`

	_, execErr := s.db.Exec(query, input.LicenseId, input.Action.String(), input.Domain, input.IpAddress, detailsJson)
	if execErr != nil {

		return fmt.Errorf("insert audit log: %w", execErr)
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
