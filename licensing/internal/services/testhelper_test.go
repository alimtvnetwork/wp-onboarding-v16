package services

import (
	"database/sql"
	"testing"

	"riseup-licensing/internal/enums/licensetype"
	"riseup-licensing/internal/enums/producttype"

	_ "modernc.org/sqlite"
)

// schema is the DDL used by all service tests (mirrors 001_initial.sql).
const schema = `
CREATE TABLE licenses (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    key             TEXT    NOT NULL UNIQUE,
    email           TEXT    NOT NULL,
    product         TEXT    NOT NULL,
    type            TEXT    NOT NULL DEFAULT 'standard',
    status          TEXT    NOT NULL DEFAULT 'active',
    max_activations INTEGER NOT NULL DEFAULT 1,
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE activations (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    license_id      INTEGER NOT NULL REFERENCES licenses(id) ON DELETE CASCADE,
    domain          TEXT    NOT NULL,
    ip_address      TEXT,
    user_agent      TEXT,
    activated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deactivated_at  DATETIME,
    UNIQUE(license_id, domain)
);
CREATE TABLE audit_log (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    license_id      INTEGER REFERENCES licenses(id) ON DELETE SET NULL,
    action          TEXT    NOT NULL,
    domain          TEXT,
    ip_address      TEXT,
    details         TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
`

// newTestDB returns an in-memory SQLite database with the schema applied.
func newTestDB(t *testing.T) *sql.DB {
	t.Helper()

	db, err := sql.Open("sqlite", ":memory:")
	if err != nil {
		t.Fatalf("open test db: %v", err)
	}

	_, err = db.Exec("PRAGMA foreign_keys=ON")
	if err != nil {
		t.Fatalf("enable foreign keys: %v", err)
	}

	_, err = db.Exec(schema)
	if err != nil {
		t.Fatalf("apply schema: %v", err)
	}

	t.Cleanup(func() { db.Close() })

	return db
}

// seedLicense inserts a license and returns its ID.
func seedLicense(t *testing.T, db *sql.DB) int64 {
	t.Helper()

	svc := NewLicenseService(db)

	lic, err := svc.Create(CreateInput{
		Key:            "RISEUP-TEST-AAAA-BBBB-CCCC",
		Email:          "test@example.com",
		Product:        producttype.RiseupUploader,
		Type:           licensetype.Standard,
		MaxActivations: 3,
		Notes:          "seed license",
	})
	if err != nil {
		t.Fatalf("seed license: %v", err)
	}

	return lic.Id
}
