package handlers_test

import (
	"bytes"
	"database/sql"
	"encoding/json"
	"fmt"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"riseup-licensing/internal/router"
	licensehmac "riseup-licensing/pkg/hmac"

	_ "modernc.org/sqlite"
)

const (
	testAdminToken = "test-admin-token-secret"
	testHMACSecret = "test-hmac-secret"
)

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

func newTestServer(t *testing.T) *httptest.Server {
	t.Helper()

	db := newTestDB(t)
	handler := router.New(router.Config{
		DB:         db,
		HMACSecret: testHMACSecret,
		AdminToken: testAdminToken,
		RateLimit:  1000,
	})

	srv := httptest.NewServer(handler)
	t.Cleanup(srv.Close)

	return srv
}

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

// adminRequest sends a Bearer-token-authenticated request.
func adminRequest(t *testing.T, method, url string, body any) *http.Response {
	t.Helper()

	jsonBody := marshalBody(body)

	req, err := http.NewRequest(method, url, bytes.NewReader(jsonBody))
	if err != nil {
		t.Fatalf("create request: %v", err)
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+testAdminToken)

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		t.Fatalf("execute request: %v", err)
	}

	return resp
}

// hmacRequest sends an HMAC-signed request.
func hmacRequest(t *testing.T, method, url string, body any) *http.Response {
	t.Helper()

	jsonBody := marshalBody(body)
	now := time.Now().Unix()
	sig := licensehmac.Sign(testHMACSecret, now, jsonBody)

	req, err := http.NewRequest(method, url, bytes.NewReader(jsonBody))
	if err != nil {
		t.Fatalf("create request: %v", err)
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Signature", sig)
	req.Header.Set("X-Timestamp", fmt.Sprintf("%d", now))

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		t.Fatalf("execute request: %v", err)
	}

	return resp
}

func marshalBody(body any) []byte {
	if body == nil {
		return []byte{}
	}

	data, _ := json.Marshal(body)

	return data
}

func decodeResponse(t *testing.T, resp *http.Response, target any) {
	t.Helper()
	defer resp.Body.Close()

	err := json.NewDecoder(resp.Body).Decode(target)
	if err != nil {
		t.Fatalf("decode response: %v", err)
	}
}

func assertStatus(t *testing.T, resp *http.Response, expected int) {
	t.Helper()

	if resp.StatusCode != expected {
		t.Errorf("status = %d, want %d", resp.StatusCode, expected)
	}
}

// createTestLicense creates a license via the admin API and returns the key.
func createTestLicense(t *testing.T, baseURL string, maxActivations int) string {
	t.Helper()

	body := map[string]any{
		"email":          "test@example.com",
		"product":        "riseup-uploader",
		"type":           "standard",
		"maxActivations": maxActivations,
	}

	resp := adminRequest(t, "POST", baseURL+"/api/v1/admin/licenses", body)
	assertStatus(t, resp, http.StatusCreated)

	var result map[string]any
	decodeResponse(t, resp, &result)

	key, ok := result["key"].(string)
	if !ok {
		t.Fatal("created license missing key")
	}

	return key
}
