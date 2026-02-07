package envelope

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestSuccess(t *testing.T) {
	data := map[string]string{"name": "Test"}
	resp := Success(data)

	if !resp.Status.IsSuccess {
		t.Error("expected IsSuccess=true")
	}
	if resp.Status.IsFailed {
		t.Error("expected IsFailed=false")
	}
	if resp.Status.Code != 200 {
		t.Errorf("expected code 200, got %d", resp.Status.Code)
	}
	if !resp.Attributes.IsSingle {
		t.Error("expected IsSingle=true")
	}
	if resp.Attributes.IsMultiple {
		t.Error("expected IsMultiple=false")
	}
	results := resp.Results.([]interface{})
	if len(results) != 1 {
		t.Errorf("expected 1 result, got %d", len(results))
	}
	if resp.Error != nil {
		t.Error("expected error=nil")
	}
	if resp.Navigation != nil {
		t.Error("expected navigation=nil for single item")
	}
}

func TestCreated(t *testing.T) {
	resp := Created(map[string]int{"id": 42})
	if resp.Status.Code != 201 {
		t.Errorf("expected code 201, got %d", resp.Status.Code)
	}
	if resp.Status.Message != "Created" {
		t.Errorf("expected message 'Created', got %q", resp.Status.Message)
	}
	if resp.Status.IsFailed {
		t.Error("expected IsFailed=false for Created")
	}
}

func TestDeleted(t *testing.T) {
	resp := Deleted()
	if resp.Status.Code != 200 {
		t.Errorf("expected code 200, got %d", resp.Status.Code)
	}
	results := resp.Results.([]interface{})
	m := results[0].(map[string]interface{})
	if m["deleted"] != true {
		t.Error("expected deleted=true in results")
	}
}

func TestList(t *testing.T) {
	items := []string{"a", "b", "c"}
	pg := NewPagination(50, 3, 10)
	resp := List(items, pg)

	if resp.Attributes.IsSingle {
		t.Error("expected IsSingle=false")
	}
	if !resp.Attributes.IsMultiple {
		t.Error("expected IsMultiple=true")
	}
	if resp.Attributes.TotalRecords != 50 {
		t.Errorf("expected TotalRecords=50, got %d", resp.Attributes.TotalRecords)
	}
	if resp.Attributes.TotalPages != 5 {
		t.Errorf("expected TotalPages=5, got %d", resp.Attributes.TotalPages)
	}
	if resp.Attributes.CurrentPage != 3 {
		t.Errorf("expected CurrentPage=3, got %d", resp.Attributes.CurrentPage)
	}
	if resp.Navigation == nil {
		t.Fatal("expected navigation to be present")
	}
	if *resp.Navigation.NextPage != 4 {
		t.Errorf("expected NextPage=4, got %d", *resp.Navigation.NextPage)
	}
	if *resp.Navigation.PrevPage != 2 {
		t.Errorf("expected PrevPage=2, got %d", *resp.Navigation.PrevPage)
	}
	if len(resp.Navigation.Pages) != 5 {
		t.Errorf("expected 5 pages, got %d", len(resp.Navigation.Pages))
	}
}

func TestListUnpaginated(t *testing.T) {
	items := []int{1, 2, 3}
	resp := ListUnpaginated(items, 3)

	if !resp.Attributes.IsMultiple {
		t.Error("expected IsMultiple=true")
	}
	if resp.Navigation != nil {
		t.Error("expected navigation=nil for unpaginated")
	}
	if resp.Attributes.TotalRecords != 3 {
		t.Errorf("expected TotalRecords=3, got %d", resp.Attributes.TotalRecords)
	}
}

func TestError(t *testing.T) {
	resp := Error(500, "E5001", "Something failed")

	if resp.Status.IsSuccess {
		t.Error("expected IsSuccess=false")
	}
	if !resp.Status.IsFailed {
		t.Error("expected IsFailed=true")
	}
	if resp.Status.Code != 500 {
		t.Errorf("expected code 500, got %d", resp.Status.Code)
	}
	if resp.Error == nil {
		t.Fatal("expected error to be present")
	}
	if resp.Error.Code != "E5001" {
		t.Errorf("expected error code E5001, got %s", resp.Error.Code)
	}
	results := resp.Results.([]interface{})
	if len(results) != 0 {
		t.Errorf("expected empty results, got %d", len(results))
	}
}

func TestErrorWithTrace_DebugOff(t *testing.T) {
	SetDebugConfig(DefaultDebugConfig()) // stack traces off
	resp := ErrorWithTrace(500, "E5001", "fail", "trace here", []StackFrame{{File: "a.go", Line: 1}})

	if resp.Error.StackTrace != "" {
		t.Error("expected empty stack trace when debug is off")
	}
	if len(resp.Error.StackTraceFrames) != 0 {
		t.Error("expected empty stack trace frames when debug is off")
	}
}

func TestErrorWithTrace_DebugOn(t *testing.T) {
	SetDebugConfig(DebugConfig{IncludeStackTrace: true, MaxStackFrames: 10})
	defer SetDebugConfig(DefaultDebugConfig())

	frames := []StackFrame{{File: "a.go", Line: 1}, {File: "b.go", Line: 2}}
	resp := ErrorWithTrace(500, "E5001", "fail", "trace", frames)

	if resp.Error.StackTrace != "trace" {
		t.Error("expected stack trace when debug is on")
	}
	if len(resp.Error.StackTraceFrames) != 2 {
		t.Errorf("expected 2 frames, got %d", len(resp.Error.StackTraceFrames))
	}
}

func TestErrorWithTrace_MaxFrames(t *testing.T) {
	SetDebugConfig(DebugConfig{IncludeStackTrace: true, MaxStackFrames: 1})
	defer SetDebugConfig(DefaultDebugConfig())

	frames := []StackFrame{{File: "a.go", Line: 1}, {File: "b.go", Line: 2}, {File: "c.go", Line: 3}}
	resp := ErrorWithTrace(500, "E5001", "fail", "trace", frames)

	if len(resp.Error.StackTraceFrames) != 1 {
		t.Errorf("expected 1 frame (max), got %d", len(resp.Error.StackTraceFrames))
	}
}

func TestWithAdditional(t *testing.T) {
	resp := Success("data").WithAdditional(map[string]bool{"retryable": true})

	if resp.Additional == nil {
		t.Fatal("expected additional to be present")
	}
	m := resp.Additional.(map[string]bool)
	if !m["retryable"] {
		t.Error("expected retryable=true")
	}
}

func TestWithEndpoints(t *testing.T) {
	resp := Success("data").WithEndpoints("/api/v1/plugins/1", "/wp-json/riseup-asia-uploader/v1/plugin")
	if resp.Attributes.RequestedEndpoint != "/api/v1/plugins/1" {
		t.Errorf("expected RequestedEndpoint, got %q", resp.Attributes.RequestedEndpoint)
	}
	if resp.Attributes.DelegatedEndpoint != "/wp-json/riseup-asia-uploader/v1/plugin" {
		t.Errorf("expected DelegatedEndpoint, got %q", resp.Attributes.DelegatedEndpoint)
	}
}

func TestWithTraversal(t *testing.T) {
	resp := Success("data").WithTraversal("PublishHandler.Handle", "PublishService.Upload", "WordPressClient.UploadPlugin")
	if len(resp.Attributes.TraversalSteps) != 3 {
		t.Errorf("expected 3 traversal steps, got %d", len(resp.Attributes.TraversalSteps))
	}
	if resp.Attributes.TraversalSteps[0] != "PublishHandler.Handle" {
		t.Errorf("unexpected first step: %q", resp.Attributes.TraversalSteps[0])
	}
}

func TestWithDelegatedError_DebugOn(t *testing.T) {
	SetDebugConfig(DebugConfig{IncludeStackTrace: true, MaxStackFrames: 10})
	defer SetDebugConfig(DefaultDebugConfig())

	de := DelegatedError{
		Endpoint:   "/wp-json/riseup-asia-uploader/v1/upload",
		StatusCode: 500,
		Message:    "PHP fatal error",
		StackTrace: "php trace",
		StackTraceFrames: []StackFrame{
			{File: "upload.php", Line: 42, Function: "handle", Class: "UploadController"},
		},
	}
	resp := Error(502, "E6001", "Delegated call failed").WithDelegatedError(de)

	additional := resp.Additional.(map[string]interface{})
	got := additional["DelegatedError"].(DelegatedError)
	if got.StackTrace != "php trace" {
		t.Error("expected delegated stack trace when debug is on")
	}
}

func TestWithDelegatedError_DebugOff(t *testing.T) {
	SetDebugConfig(DefaultDebugConfig())

	de := DelegatedError{
		Endpoint:         "/wp-json/riseup-asia-uploader/v1/upload",
		StatusCode:       500,
		Message:          "PHP fatal error",
		StackTrace:       "php trace",
		StackTraceFrames: []StackFrame{{File: "a.php", Line: 1}},
	}
	resp := Error(502, "E6001", "Delegated call failed").WithDelegatedError(de)

	additional := resp.Additional.(map[string]interface{})
	got := additional["DelegatedError"].(DelegatedError)
	if got.StackTrace != "" {
		t.Error("expected empty delegated stack trace when debug is off")
	}
	if got.StackTraceFrames != nil {
		t.Error("expected nil delegated frames when debug is off")
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
	nav := pg.Navigation()

	if nav.PrevPage != nil {
		t.Error("expected no prev page on first page")
	}
	if nav.NextPage == nil || *nav.NextPage != 2 {
		t.Error("expected NextPage=2")
	}
	if nav.Pages[0] != 1 {
		t.Errorf("expected pages starting at 1, got %d", nav.Pages[0])
	}
}

func TestNavigation_LastPage(t *testing.T) {
	pg := NewPagination(100, 10, 10)
	nav := pg.Navigation()

	if nav.NextPage != nil {
		t.Error("expected no next page on last page")
	}
	if nav.PrevPage == nil || *nav.PrevPage != 9 {
		t.Error("expected PrevPage=9")
	}
}

func TestNavigation_SmallDataset(t *testing.T) {
	pg := NewPagination(3, 1, 10)
	nav := pg.Navigation()

	if nav.NextPage != nil {
		t.Error("expected no next page for single-page dataset")
	}
	if len(nav.Pages) != 1 {
		t.Errorf("expected 1 page, got %d", len(nav.Pages))
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
	resp := Error(400, "E1001", "Bad request")
	b, err := json.Marshal(resp)
	if err != nil {
		t.Fatalf("marshal failed: %v", err)
	}

	var decoded map[string]interface{}
	json.Unmarshal(b, &decoded)

	// Verify PascalCase top-level keys
	for _, key := range []string{"Status", "Attributes", "Results", "Error"} {
		if _, ok := decoded[key]; !ok {
			t.Errorf("missing PascalCase top-level key %q", key)
		}
	}
	// Navigation should be omitted (omitempty) for error responses
	if _, ok := decoded["Navigation"]; ok {
		t.Error("Navigation should be omitted for error responses")
	}

	// Verify Status uses IsSuccess/IsFailed
	status := decoded["Status"].(map[string]interface{})
	if _, ok := status["IsSuccess"]; !ok {
		t.Error("missing IsSuccess in Status")
	}
	if _, ok := status["IsFailed"]; !ok {
		t.Error("missing IsFailed in Status")
	}
	// Old "success" key must not exist
	if _, ok := status["success"]; ok {
		t.Error("old 'success' key should not exist")
	}
}
