package services

import (
	"database/sql"
	"encoding/json"
	"testing"

	"riseup-licensing/internal/enums/auditactiontype"
)

func TestLogBasicEntry(t *testing.T) {
	db := newTestDB(t)
	licId := seedLicense(t, db)
	svc := NewAuditService(db)

	err := svc.Log(LogInput{
		LicenseId: &licId,
		Action:    auditactiontype.Activated,
		Domain:    "audit.com",
		IpAddress: "1.2.3.4",
		Details:   map[string]string{"reason": "test"},
	})
	if err != nil {
		t.Fatalf("log: %v", err)
	}

	row := db.QueryRow("SELECT action, domain, ip_address, details FROM audit_log WHERE license_id = ?", licId)

	var action, domain, ip string
	var details sql.NullString

	if err := row.Scan(&action, &domain, &ip, &details); err != nil {
		t.Fatalf("scan: %v", err)
	}

	if action != "activated" {
		t.Errorf("action = %q, want activated", action)
	}
	if domain != "audit.com" {
		t.Errorf("domain = %q, want audit.com", domain)
	}

	if !details.Valid {
		t.Fatal("expected details to be non-null")
	}

	var parsed map[string]string
	if err := json.Unmarshal([]byte(details.String), &parsed); err != nil {
		t.Fatalf("unmarshal details: %v", err)
	}
	if parsed["reason"] != "test" {
		t.Errorf("details.reason = %q, want test", parsed["reason"])
	}
}

func TestLogNilDetails(t *testing.T) {
	db := newTestDB(t)
	svc := NewAuditService(db)

	err := svc.Log(LogInput{
		LicenseId: nil,
		Action:    auditactiontype.Validated,
		Domain:    "nil.com",
		IpAddress: "5.6.7.8",
		Details:   nil,
	})
	if err != nil {
		t.Fatalf("log nil details: %v", err)
	}

	var count int
	db.QueryRow("SELECT COUNT(*) FROM audit_log WHERE domain = 'nil.com'").Scan(&count)

	if count != 1 {
		t.Errorf("count = %d, want 1", count)
	}
}

func TestLogAllActions(t *testing.T) {
	db := newTestDB(t)
	svc := NewAuditService(db)

	for _, action := range auditactiontype.All() {
		err := svc.Log(LogInput{
			Action:    action,
			Domain:    "all-actions.com",
			IpAddress: "0.0.0.0",
		})
		if err != nil {
			t.Fatalf("log action %q: %v", action, err)
		}
	}

	var count int
	db.QueryRow("SELECT COUNT(*) FROM audit_log WHERE domain = 'all-actions.com'").Scan(&count)

	expected := len(auditaction.All())
	if count != expected {
		t.Errorf("count = %d, want %d", count, expected)
	}
}
