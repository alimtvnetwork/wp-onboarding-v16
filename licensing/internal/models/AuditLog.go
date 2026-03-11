package models

import (
	"encoding/json"
	"time"

	"riseup-licensing/internal/enums/auditactiontype"
)

// AuditLog represents an audit trail entry.
type AuditLog struct {
	Id        int64                `json:"id"`
	LicenseId *int64               `json:"license_id,omitempty"`
	Action    auditaction.Variant  `json:"action"`
	Domain    string               `json:"domain,omitempty"`
	IpAddress string               `json:"ip_address,omitempty"`
	Details   json.RawMessage      `json:"details,omitempty"`
	CreatedAt time.Time            `json:"created_at"`
}
