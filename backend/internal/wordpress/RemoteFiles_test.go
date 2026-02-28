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

	"wp-plugin-publish/internal/enums/uploadsourcetype"
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
	result := c.CheckOnboardPluginAvailable()
	if result.HasError() {
		t.Fatalf("expected no error, got: %v", result.AppError())
	}
	if result.Value().IsUnavailable() {
		t.Fatalf("expected available=true")
	}
}

func TestCheckUploaderHelperAvailable_UsesUploaderNamespace(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/wp-json/plugin-uploader/v1/status" {
			t.Fatalf("unexpected path: %s, expected /wp-json/plugin-uploader/v1/status", r.URL.Path)
		}
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"status":"ok","version":"1.1.0"}`))
	}))
	defer server.Close()

	c := NewClient(ClientConfig{BaseURL: server.URL, Username: "u", Password: "p", Timeout: 2 * time.Second})
	result := c.CheckUploaderHelperAvailable()
	if result.HasError() {
		t.Fatalf("expected no error, got: %v", result.AppError())
	}
	if result.Value().IsUnavailable() {
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
	tokResult := c.RequestMutationToken("upload")
	if tokResult.HasError() {
		t.Fatalf("expected no error, got: %v", tokResult.AppError())
	}
	if tokResult.Value() != "abc123" {
		t.Fatalf("expected token abc123, got: %s", tokResult.Value())
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
			if r.FormValue("pluginSlug") != "category-generator" {
				t.Fatalf("unexpected pluginSlug: %s", r.FormValue("pluginSlug"))
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
	res := c.UploadPluginZip(zipPath, "category-generator")
	if res.HasError() {
		t.Fatalf("expected no error, got: %v", res.AppError())
	}
	if res.Value().IsFailed() {
		t.Fatalf("expected success result")
	}
}

func TestUploadPluginViaUploader_PostsToUploaderEndpoint(t *testing.T) {
	tmpDir := t.TempDir()
	zipPath := filepath.Join(tmpDir, "test-plugin.zip")
	if err := os.WriteFile(zipPath, []byte("fake-zip-data"), 0644); err != nil {
		t.Fatalf("write temp zip: %v", err)
	}

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/wp-json/plugin-uploader/v1/upload" {
			t.Fatalf("unexpected path: %s, expected /wp-json/plugin-uploader/v1/upload", r.URL.Path)
		}
		if r.Method != http.MethodPost {
			t.Fatalf("unexpected method: %s", r.Method)
		}
		if r.Header.Get("Content-Type") != "application/json" {
			t.Fatalf("unexpected content-type: %s", r.Header.Get("Content-Type"))
		}

		var body map[string]interface{}
		if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
			t.Fatalf("decode body: %v", err)
		}

		if body["plugin_name"] != "test-plugin.zip" {
			t.Fatalf("unexpected plugin_name: %v", body["plugin_name"])
		}
		if body["plugin_data"] == nil || body["plugin_data"] == "" {
			t.Fatalf("expected plugin_data to be set")
		}
		if body["activate"] != true {
			t.Fatalf("expected activate=true")
		}

		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)
		_ = json.NewEncoder(w).Encode(UploaderUploadResult{
			Success:   true,
			Message:   "Plugin installed and activated successfully.",
			Plugin:    "test-plugin/test-plugin.php",
			Activated: true,
		})
	}))
	defer server.Close()

	c := NewClient(ClientConfig{BaseURL: server.URL, Username: "u", Password: "p", Timeout: 2 * time.Second})
	res := c.UploadPluginViaUploader(UploadInput{ZipPath: zipPath, Slug: "test-plugin", IsActivate: true, UploadSource: uploadsourcetype.RestAPI})
	if res.HasError() {
		t.Fatalf("expected no error, got: %v", res.AppError())
	}
	if res.Value().IsFailed() {
		t.Fatalf("expected success result")
	}
	if res.Value().IsDeactivated() {
		t.Fatalf("expected activated=true")
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

func TestEnablePluginViaUploader_UsesUploaderNamespace(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// Fixed URL endpoint - slug is in JSON body, not in URL path
		if r.URL.Path != "/wp-json/plugin-uploader/v1/plugins/enable" {
			t.Fatalf("unexpected path: %s (expected /wp-json/plugin-uploader/v1/plugins/enable)", r.URL.Path)
		}
		if r.Method != http.MethodPost {
			t.Fatalf("unexpected method: %s", r.Method)
		}
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"success":true,"message":"Plugin activated successfully.","slug":"my-plugin"}`))
	}))
	defer server.Close()

	c := NewClient(ClientConfig{BaseURL: server.URL, Username: "u", Password: "p", Timeout: 2 * time.Second})
	if err := c.EnablePluginViaUploader("my-plugin"); err != nil {
		t.Fatalf("expected nil error, got: %v", err)
	}
}

func TestDisablePluginViaUploader_UsesUploaderNamespace(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// Fixed URL endpoint - slug is in JSON body, not in URL path
		if r.URL.Path != "/wp-json/plugin-uploader/v1/plugins/disable" {
			t.Fatalf("unexpected path: %s (expected /wp-json/plugin-uploader/v1/plugins/disable)", r.URL.Path)
		}
		if r.Method != http.MethodPost {
			t.Fatalf("unexpected method: %s", r.Method)
		}
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"success":true}`))
	}))
	defer server.Close()

	c := NewClient(ClientConfig{BaseURL: server.URL, Username: "u", Password: "p", Timeout: 2 * time.Second})
	if err := c.DisablePluginViaUploader("my-plugin"); err != nil {
		t.Fatalf("expected nil error, got: %v", err)
	}
}
