package envelope

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
)

func TestWithDelegatedErrorStack_DebugOn(t *testing.T) {
	SetDebugConfig(DebugConfig{IncludeErrors: true, IncludeStackTrace: true, MaxStackFrames: 10})
	defer SetDebugConfig(DefaultDebugConfig())

	lines := []string{"PHP Fatal error: Class 'PDO' not found", "#0 upload.php(42): connect()"}
	resp := Error(502, "E6001", "Delegated call failed").WithDelegatedErrorStack(lines)

	if resp.Errors == nil {
		t.Fatal("expected Errors to be present")
	}
	if len(resp.Errors.DelegatedServiceErrorStack) != 2 {
		t.Errorf("expected 2 delegated lines, got %d", len(resp.Errors.DelegatedServiceErrorStack))
	}
}

func TestWithDelegatedErrorStack_DebugOff(t *testing.T) {
	SetDebugConfig(DebugConfig{IncludeErrors: true, IncludeStackTrace: false})
	defer SetDebugConfig(DefaultDebugConfig())

	lines := []string{"PHP Fatal error"}
	resp := Error(502, "E6001", "fail").WithDelegatedErrorStack(lines)

	if resp.Errors != nil && len(resp.Errors.DelegatedServiceErrorStack) > 0 {
		t.Error("expected no delegated stack when IncludeStackTrace=false")
	}
}

func TestWithMethodsStack_Enabled(t *testing.T) {
	SetDebugConfig(DebugConfig{IncludeMethodsStack: true})
	defer SetDebugConfig(DefaultDebugConfig())

	frames := []MethodFrame{
		{Method: "HandlePublishUpload", File: "publish_handlers.go", LineNumber: 55},
		{Method: "UploadToSite", File: "publish_service.go", LineNumber: 112},
	}
	resp := Success("data").WithMethodsStack(frames)

	if resp.MethodsStack == nil {
		t.Fatal("expected MethodsStack to be present")
	}
	if len(resp.MethodsStack.Backend) != 2 {
		t.Errorf("expected 2 backend frames, got %d", len(resp.MethodsStack.Backend))
	}
	if resp.MethodsStack.Backend[0].Method != "HandlePublishUpload" {
		t.Errorf("unexpected method: %q", resp.MethodsStack.Backend[0].Method)
	}
}

func TestWithMethodsStack_Disabled(t *testing.T) {
	SetDebugConfig(DebugConfig{IncludeMethodsStack: false})
	defer SetDebugConfig(DefaultDebugConfig())

	frames := []MethodFrame{{Method: "Handle", File: "a.go", LineNumber: 1}}
	resp := Success("data").WithMethodsStack(frames)

	if resp.MethodsStack != nil {
		t.Error("expected MethodsStack=nil when disabled")
	}
}

func TestWithEndpoints(t *testing.T) {
	resp := Success("data").WithEndpoints("/api/v1/plugins/1", "/wp-json/riseup-asia-uploader/v1/plugin")
	if resp.Attributes.RequestedAt != "/api/v1/plugins/1" {
		t.Errorf("expected RequestedAt, got %q", resp.Attributes.RequestedAt)
	}
	if resp.Attributes.RequestDelegatedAt != "/wp-json/riseup-asia-uploader/v1/plugin" {
		t.Errorf("expected RequestDelegatedAt, got %q", resp.Attributes.RequestDelegatedAt)
	}
}

func TestPagination_TotalPages(t *testing.T) {
	tests := []struct {
		total, perPage, expected int
	}{
		{0, 20, 0},
		{1, 20, 1},
		{20, 20, 1},
		{21, 20, 2},
		{100, 10, 10},
		{101, 10, 11},
	}
	for _, tt := range tests {
		pg := NewPagination(tt.total, 1, tt.perPage)
		if got := pg.TotalPages(); got != tt.expected {
			t.Errorf("TotalPages(%d, %d) = %d, want %d", tt.total, tt.perPage, got, tt.expected)
		}
	}
}

func TestPagination_Offset(t *testing.T) {
	pg := NewPagination(100, 3, 10)
	if pg.Offset() != 20 {
		t.Errorf("expected offset 20, got %d", pg.Offset())
	}
}

func TestPagination_Defaults(t *testing.T) {
	pg := NewPagination(100, 0, 0)
	if pg.Page != 1 {
		t.Errorf("expected page 1, got %d", pg.Page)
	}
	if pg.PerPage != 20 {
		t.Errorf("expected perPage 20, got %d", pg.PerPage)
	}
}

func TestNavigation_FirstPage(t *testing.T) {
	pg := NewPagination(100, 1, 10)
	nav := pg.NavigationURLs("/api/v1/items")

	if nav.PrevPage != nil {
		t.Error("expected no prev page on first page")
	}
	if nav.NextPage == nil || !strings.Contains(*nav.NextPage, "page=2") {
		t.Error("expected NextPage URL containing page=2")
	}
	if !strings.HasPrefix(nav.CloserLinks[0], "/api/v1/items?page=1") {
		t.Errorf("expected first closer link to start with path, got %q", nav.CloserLinks[0])
	}
}

func TestNavigation_LastPage(t *testing.T) {
	pg := NewPagination(100, 10, 10)
	nav := pg.NavigationURLs("/api/v1/items")

	if nav.NextPage != nil {
		t.Error("expected no next page on last page")
	}
	if nav.PrevPage == nil || !strings.Contains(*nav.PrevPage, "page=9") {
		t.Error("expected PrevPage URL containing page=9")
	}
}

func TestNavigation_SmallDataset(t *testing.T) {
	pg := NewPagination(3, 1, 10)
	nav := pg.NavigationURLs("/api/v1/items")

	if nav.NextPage != nil {
		t.Error("expected no next page for single-page dataset")
	}
	if len(nav.CloserLinks) != 1 {
		t.Errorf("expected 1 closer link, got %d", len(nav.CloserLinks))
	}
}

func TestWrite(t *testing.T) {
	w := httptest.NewRecorder()
	resp := Success(map[string]string{"hello": "world"})
	Write(w, resp)

	if w.Code != http.StatusOK {
		t.Errorf("expected 200, got %d", w.Code)
	}
	if ct := w.Header().Get("Content-Type"); ct != "application/json" {
		t.Errorf("expected application/json, got %s", ct)
	}

	var decoded Response
	if err := json.NewDecoder(w.Body).Decode(&decoded); err != nil {
		t.Fatalf("failed to decode response: %v", err)
	}
	if !decoded.Status.IsSuccess {
		t.Error("expected decoded IsSuccess=true")
	}
}

func TestJSON_Serialization_PascalCase(t *testing.T) {
	SetDebugConfig(DebugConfig{IncludeErrors: true})
	defer SetDebugConfig(DefaultDebugConfig())

	resp := Error(400, "E1001", "Bad request")
	b, err := json.Marshal(resp)
	if err != nil {
		t.Fatalf("marshal failed: %v", err)
	}

	var decoded map[string]interface{}
	json.Unmarshal(b, &decoded)

	for _, key := range []string{"Status", "Attributes", "Results", "Errors"} {
		if _, ok := decoded[key]; !ok {
			t.Errorf("missing PascalCase top-level key %q", key)
		}
	}
	if _, ok := decoded["Navigation"]; ok {
		t.Error("Navigation should be omitted for error responses")
	}
	if _, ok := decoded["MethodsStack"]; ok {
		t.Error("MethodsStack should be omitted when disabled")
	}
	if _, ok := decoded["Error"]; ok {
		t.Error("old 'Error' key should not exist")
	}
	if _, ok := decoded["Additional"]; ok {
		t.Error("old 'Additional' key should not exist")
	}
}
