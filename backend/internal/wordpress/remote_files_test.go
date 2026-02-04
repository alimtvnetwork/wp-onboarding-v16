package wordpress

import (
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"
	"time"
)

func TestCheckOnboardPluginAvailable_UsesOnboardNamespace(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/wp-json/onboard-plugin/v1/plugins/list" {
			t.Fatalf("unexpected path: %s", r.URL.Path)
		}
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"success":true}`))
	}))
	defer server.Close()

	c := NewClient(ClientConfig{BaseURL: server.URL, Username: "u", Password: "p", Timeout: 2 * time.Second})
	ok, err := c.CheckOnboardPluginAvailable()
	if err != nil {
		t.Fatalf("expected nil error, got: %v", err)
	}
	if !ok {
		t.Fatalf("expected available=true")
	}
}

func TestRequestMutationToken_UsesOnboardNamespace(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/wp-json/onboard-plugin/v1/request-mutation" {
			t.Fatalf("unexpected path: %s", r.URL.Path)
		}
		if r.URL.Query().Get("action") != "upload" {
			t.Fatalf("unexpected action: %s", r.URL.Query().Get("action"))
		}
		_ = json.NewEncoder(w).Encode(map[string]any{
			"mutation_token": "abc123",
			"expires_in":     1200,
		})
	}))
	defer server.Close()

	c := NewClient(ClientConfig{BaseURL: server.URL, Username: "u", Password: "p", Timeout: 2 * time.Second})
	tok, err := c.RequestMutationToken("upload")
	if err != nil {
		t.Fatalf("expected nil error, got: %v", err)
	}
	if tok != "abc123" {
		t.Fatalf("expected token abc123, got: %s", tok)
	}
}

func TestUploadPluginZip_PostsToOnboardUploadEndpoint(t *testing.T) {
	tmpDir := t.TempDir()
	zipPath := filepath.Join(tmpDir, "plug.zip")
	if err := os.WriteFile(zipPath, []byte("not-a-real-zip"), 0644); err != nil {
		t.Fatalf("write temp zip: %v", err)
	}

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/wp-json/onboard-plugin/v1/request-mutation":
			if r.URL.Query().Get("action") != "upload" {
				t.Fatalf("unexpected action: %s", r.URL.Query().Get("action"))
			}
			_ = json.NewEncoder(w).Encode(map[string]any{
				"mutation_token": "abc123",
				"expires_in":     1200,
			})
			return
		case "/wp-json/onboard-plugin/v1/mutations/abc123/plugins/upload":
			if r.Method != http.MethodPost {
				t.Fatalf("unexpected method: %s", r.Method)
			}
			if err := r.ParseMultipartForm(10 << 20); err != nil {
				t.Fatalf("parse multipart: %v", err)
			}
			if r.FormValue("plugin_slug") != "category-generator" {
				t.Fatalf("unexpected plugin_slug: %s", r.FormValue("plugin_slug"))
			}
			if r.FormValue("overwrite") != "true" {
				t.Fatalf("unexpected overwrite: %s", r.FormValue("overwrite"))
			}
			f, _, err := r.FormFile("plugin_zip")
			if err != nil {
				t.Fatalf("missing plugin_zip: %v", err)
			}
			defer f.Close()
			b, _ := io.ReadAll(f)
			if len(b) == 0 {
				t.Fatalf("expected plugin_zip content")
			}
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusOK)
			_ = json.NewEncoder(w).Encode(OnboardUploadResult{Success: true, Message: "ok"})
			return
		default:
			t.Fatalf("unexpected path: %s", r.URL.Path)
		}
	}))
	defer server.Close()

	c := NewClient(ClientConfig{BaseURL: server.URL, Username: "u", Password: "p", Timeout: 2 * time.Second})
	res, err := c.UploadPluginZip(zipPath, "category-generator")
	if err != nil {
		t.Fatalf("expected nil error, got: %v", err)
	}
	if res == nil || !res.Success {
		t.Fatalf("expected success result")
	}
}

func TestEnablePlugin_UsesOnboardNamespaceAndEnableRoute(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/wp-json/onboard-plugin/v1/request-mutation":
			if r.URL.Query().Get("action") != "enable" {
				t.Fatalf("unexpected action: %s", r.URL.Query().Get("action"))
			}
			_ = json.NewEncoder(w).Encode(map[string]any{
				"mutation_token": "abc123",
				"expires_in":     1200,
			})
			return
		case "/wp-json/onboard-plugin/v1/mutations/abc123/plugins/category-generator/enable":
			if r.Method != http.MethodPost {
				t.Fatalf("unexpected method: %s", r.Method)
			}
			w.WriteHeader(http.StatusOK)
			_, _ = w.Write([]byte(`{"success":true}`))
			return
		default:
			t.Fatalf("unexpected path: %s", r.URL.Path)
		}
	}))
	defer server.Close()

	c := NewClient(ClientConfig{BaseURL: server.URL, Username: "u", Password: "p", Timeout: 2 * time.Second})
	if err := c.EnablePlugin("category-generator"); err != nil {
		t.Fatalf("expected nil error, got: %v", err)
	}
}
