package handlers_test

import (
	"fmt"
	"net/http"
	"testing"
)

func TestPublicValidate(t *testing.T) {
	srv := newTestServer(t)
	key := createTestLicense(t, srv.URL, 3)

	resp := hmacRequest(t, "GET", srv.URL+"/api/v1/licenses/"+key+"/validate", nil)
	assertStatus(t, resp, http.StatusOK)

	var result map[string]any
	decodeResponse(t, resp, &result)

	if result["valid"] != true {
		t.Errorf("valid = %v, want true", result["valid"])
	}
	if result["status"] != "active" {
		t.Errorf("status = %v, want active", result["status"])
	}
}

func TestPublicValidateNotFound(t *testing.T) {
	srv := newTestServer(t)

	resp := hmacRequest(t, "GET", srv.URL+"/api/v1/licenses/RISEUP-FAKE-FAKE-FAKE-FAKE/validate", nil)
	assertStatus(t, resp, http.StatusOK)

	var result map[string]any
	decodeResponse(t, resp, &result)

	if result["valid"] != false {
		t.Errorf("valid = %v, want false", result["valid"])
	}
}

func TestPublicActivate(t *testing.T) {
	srv := newTestServer(t)
	key := createTestLicense(t, srv.URL, 3)

	resp := hmacRequest(t, "POST", srv.URL+"/api/v1/licenses/"+key+"/activate", map[string]string{
		"domain": "mysite.com",
	})
	assertStatus(t, resp, http.StatusOK)

	var result map[string]any
	decodeResponse(t, resp, &result)

	if result["domain"] != "mysite.com" {
		t.Errorf("domain = %v, want mysite.com", result["domain"])
	}
}

func TestPublicActivateMissingDomain(t *testing.T) {
	srv := newTestServer(t)
	key := createTestLicense(t, srv.URL, 1)

	resp := hmacRequest(t, "POST", srv.URL+"/api/v1/licenses/"+key+"/activate", map[string]string{})
	assertStatus(t, resp, http.StatusBadRequest)
	resp.Body.Close()
}

func TestPublicActivateLimitReached(t *testing.T) {
	srv := newTestServer(t)
	key := createTestLicense(t, srv.URL, 1)

	resp1 := hmacRequest(t, "POST", srv.URL+"/api/v1/licenses/"+key+"/activate", map[string]string{
		"domain": "first.com",
	})
	assertStatus(t, resp1, http.StatusOK)
	resp1.Body.Close()

	resp2 := hmacRequest(t, "POST", srv.URL+"/api/v1/licenses/"+key+"/activate", map[string]string{
		"domain": "second.com",
	})
	assertStatus(t, resp2, http.StatusConflict)
	resp2.Body.Close()
}

func TestPublicDeactivate(t *testing.T) {
	srv := newTestServer(t)
	key := createTestLicense(t, srv.URL, 3)

	activateResp := hmacRequest(t, "POST", srv.URL+"/api/v1/licenses/"+key+"/activate", map[string]string{
		"domain": "deact.com",
	})
	assertStatus(t, activateResp, http.StatusOK)
	activateResp.Body.Close()

	resp := hmacRequest(t, "POST", srv.URL+"/api/v1/licenses/"+key+"/deactivate", map[string]string{
		"domain": "deact.com",
	})
	assertStatus(t, resp, http.StatusOK)

	var result map[string]any
	decodeResponse(t, resp, &result)

	if result["status"] != "deactivated" {
		t.Errorf("status = %v, want deactivated", result["status"])
	}
}

func TestPublicStatus(t *testing.T) {
	srv := newTestServer(t)
	key := createTestLicense(t, srv.URL, 3)

	hmacRequest(t, "POST", srv.URL+"/api/v1/licenses/"+key+"/activate", map[string]string{
		"domain": "status-a.com",
	}).Body.Close()

	hmacRequest(t, "POST", srv.URL+"/api/v1/licenses/"+key+"/activate", map[string]string{
		"domain": "status-b.com",
	}).Body.Close()

	resp := hmacRequest(t, "GET", srv.URL+"/api/v1/licenses/"+key+"/status", nil)
	assertStatus(t, resp, http.StatusOK)

	var result map[string]any
	decodeResponse(t, resp, &result)

	activations, ok := result["activations"].([]any)
	if !ok {
		t.Fatal("expected activations array")
	}
	if len(activations) != 2 {
		t.Errorf("activations len = %d, want 2", len(activations))
	}
}

func TestPublicNoHMACUnauthorized(t *testing.T) {
	srv := newTestServer(t)
	key := createTestLicense(t, srv.URL, 1)

	req, _ := http.NewRequest("GET", srv.URL+"/api/v1/licenses/"+key+"/validate", nil)
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		t.Fatalf("request: %v", err)
	}
	defer resp.Body.Close()

	assertStatus(t, resp, http.StatusUnauthorized)
}

func TestPublicBadHMACForbidden(t *testing.T) {
	srv := newTestServer(t)
	key := createTestLicense(t, srv.URL, 1)

	req, _ := http.NewRequest("GET", srv.URL+"/api/v1/licenses/"+key+"/validate", nil)
	req.Header.Set("X-Signature", "bad-signature")
	req.Header.Set("X-Timestamp", fmt.Sprintf("%d", 1000000000))

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		t.Fatalf("request: %v", err)
	}
	defer resp.Body.Close()

	assertStatus(t, resp, http.StatusForbidden)
}

func TestPublicActivateSuspendedLicense(t *testing.T) {
	srv := newTestServer(t)

	createResp := adminRequest(t, "POST", srv.URL+"/api/v1/admin/licenses", map[string]any{
		"email":          "suspended@example.com",
		"product":        "riseup-uploader",
		"maxActivations": 3,
	})

	var created map[string]any
	decodeResponse(t, createResp, &created)

	id := created["id"].(float64)
	key := created["key"].(string)

	adminRequest(t, "PATCH", srv.URL+"/api/v1/admin/licenses/"+intStr(id), map[string]any{
		"status": "suspended",
	}).Body.Close()

	resp := hmacRequest(t, "POST", srv.URL+"/api/v1/licenses/"+key+"/activate", map[string]string{
		"domain": "blocked.com",
	})
	assertStatus(t, resp, http.StatusForbidden)
	resp.Body.Close()
}

func TestHealthEndpoint(t *testing.T) {
	srv := newTestServer(t)

	resp, err := http.Get(srv.URL + "/api/v1/health")
	if err != nil {
		t.Fatalf("health request: %v", err)
	}
	defer resp.Body.Close()

	assertStatus(t, resp, http.StatusOK)
}
