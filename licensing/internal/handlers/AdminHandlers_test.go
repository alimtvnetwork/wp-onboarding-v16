package handlers_test

import (
	"fmt"
	"net/http"
	"testing"
)

func TestAdminCreateLicense(t *testing.T) {
	srv := newTestServer(t)

	body := map[string]any{
		"email":          "admin@example.com",
		"product":        "riseup-uploader",
		"type":           "professional",
		"maxActivations": 5,
		"notes":          "integration test",
	}

	resp := adminRequest(t, "POST", srv.URL+"/api/v1/admin/licenses", body)
	assertStatus(t, resp, http.StatusCreated)

	var result map[string]any
	decodeResponse(t, resp, &result)

	if result["email"] != "admin@example.com" {
		t.Errorf("email = %v, want admin@example.com", result["email"])
	}

	if result["key"] == nil || result["key"] == "" {
		t.Error("expected non-empty license key")
	}
}

func TestAdminCreateLicenseMissingEmail(t *testing.T) {
	srv := newTestServer(t)

	resp := adminRequest(t, "POST", srv.URL+"/api/v1/admin/licenses", map[string]any{
		"product": "riseup-uploader",
	})
	assertStatus(t, resp, http.StatusBadRequest)
}

func TestAdminListLicenses(t *testing.T) {
	srv := newTestServer(t)

	createTestLicense(t, srv.URL, 1)
	createTestLicense(t, srv.URL, 1)

	resp := adminRequest(t, "GET", srv.URL+"/api/v1/admin/licenses", nil)
	assertStatus(t, resp, http.StatusOK)

	var result []map[string]any
	decodeResponse(t, resp, &result)

	if len(result) != 2 {
		t.Errorf("len = %d, want 2", len(result))
	}
}

func TestAdminGetLicense(t *testing.T) {
	srv := newTestServer(t)

	createResp := adminRequest(t, "POST", srv.URL+"/api/v1/admin/licenses", map[string]any{
		"email":   "get@example.com",
		"product": "riseup-uploader",
	})

	var created map[string]any
	decodeResponse(t, createResp, &created)

	id := created["id"].(float64)

	resp := adminRequest(t, "GET", srv.URL+"/api/v1/admin/licenses/"+intStr(id), nil)
	assertStatus(t, resp, http.StatusOK)

	var result map[string]any
	decodeResponse(t, resp, &result)

	if result["email"] != "get@example.com" {
		t.Errorf("email = %v, want get@example.com", result["email"])
	}
}

func TestAdminUpdateLicense(t *testing.T) {
	srv := newTestServer(t)

	createResp := adminRequest(t, "POST", srv.URL+"/api/v1/admin/licenses", map[string]any{
		"email":   "upd@example.com",
		"product": "riseup-uploader",
	})

	var created map[string]any
	decodeResponse(t, createResp, &created)

	id := created["id"].(float64)
	notes := "updated via test"

	resp := adminRequest(t, "PATCH", srv.URL+"/api/v1/admin/licenses/"+intStr(id), map[string]any{
		"status":         "suspended",
		"maxActivations": 10,
		"notes":          notes,
	})
	assertStatus(t, resp, http.StatusOK)

	var result map[string]any
	decodeResponse(t, resp, &result)

	if result["status"] != "suspended" {
		t.Errorf("status = %v, want suspended", result["status"])
	}
	if result["notes"] != notes {
		t.Errorf("notes = %v, want %q", result["notes"], notes)
	}
}

func TestAdminDeleteLicense(t *testing.T) {
	srv := newTestServer(t)

	createResp := adminRequest(t, "POST", srv.URL+"/api/v1/admin/licenses", map[string]any{
		"email":   "del@example.com",
		"product": "riseup-uploader",
	})

	var created map[string]any
	decodeResponse(t, createResp, &created)

	id := created["id"].(float64)

	resp := adminRequest(t, "DELETE", srv.URL+"/api/v1/admin/licenses/"+intStr(id), nil)
	assertStatus(t, resp, http.StatusOK)

	getResp := adminRequest(t, "GET", srv.URL+"/api/v1/admin/licenses/"+intStr(id), nil)
	assertStatus(t, getResp, http.StatusNotFound)
	getResp.Body.Close()
}

func TestAdminUnauthorized(t *testing.T) {
	srv := newTestServer(t)

	req, _ := http.NewRequest("GET", srv.URL+"/api/v1/admin/licenses", nil)
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		t.Fatalf("request: %v", err)
	}
	defer resp.Body.Close()

	assertStatus(t, resp, http.StatusUnauthorized)
}

func TestAdminForbiddenBadToken(t *testing.T) {
	srv := newTestServer(t)

	req, _ := http.NewRequest("GET", srv.URL+"/api/v1/admin/licenses", nil)
	req.Header.Set("Authorization", "Bearer wrong-token")

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		t.Fatalf("request: %v", err)
	}
	defer resp.Body.Close()

	assertStatus(t, resp, http.StatusForbidden)
}

func intStr(f float64) string {
	return fmt.Sprintf("%d", int64(f))
}
