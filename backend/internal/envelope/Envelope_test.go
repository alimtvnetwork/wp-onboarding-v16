package envelope

import (
	"strings"
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
	results, _ := resp.Results.([]interface{})
	if len(results) != 1 {
		t.Errorf("expected 1 result, got %d", len(results))
	}
	if resp.Errors != nil {
		t.Error("expected Errors=nil")
	}
	if resp.MethodsStack != nil {
		t.Error("expected MethodsStack=nil")
	}
	if resp.Navigation != nil {
		t.Error("expected Navigation=nil for single item")
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
}

func TestDeleted(t *testing.T) {
	resp := Deleted()
	if resp.Status.Code != 200 {
		t.Errorf("expected code 200, got %d", resp.Status.Code)
	}
	results, _ := resp.Results.([]interface{})
	if len(results) != 0 {
		t.Errorf("expected empty results, got %d", len(results))
	}
}

func TestList(t *testing.T) {
	items := []string{"a", "b", "c"}
	pg := NewPagination(50, 3, 10)
	resp := List(items, pg, "/api/v1/plugins")

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
	if resp.Navigation.NextPage == nil || !strings.Contains(*resp.Navigation.NextPage, "page=4") {
		t.Error("expected NextPage URL containing page=4")
	}
	if resp.Navigation.PrevPage == nil || !strings.Contains(*resp.Navigation.PrevPage, "page=2") {
		t.Error("expected PrevPage URL containing page=2")
	}
	if len(resp.Navigation.CloserLinks) != 5 {
		t.Errorf("expected 5 closer links, got %d", len(resp.Navigation.CloserLinks))
	}
	for _, link := range resp.Navigation.CloserLinks {
		if !strings.HasPrefix(link, "/api/v1/plugins?page=") {
			t.Errorf("expected URL string, got %q", link)
		}
	}
}

func TestListUnpaginated(t *testing.T) {
	items := []int{1, 2, 3}
	resp := ListUnpaginated(items, 3)

	if !resp.Attributes.IsMultiple {
		t.Error("expected IsMultiple=true")
	}
	if resp.Navigation != nil {
		t.Error("expected Navigation=nil for unpaginated")
	}
	if resp.Attributes.TotalRecords != 3 {
		t.Errorf("expected TotalRecords=3, got %d", resp.Attributes.TotalRecords)
	}
}

func TestError_WithErrorsEnabled(t *testing.T) {
	SetDebugConfig(DebugConfig{IncludeErrors: true})
	defer SetDebugConfig(DefaultDebugConfig())

	resp := Error(500, "E5001", "Something failed")

	if resp.Status.IsSuccess {
		t.Error("expected IsSuccess=false")
	}
	if !resp.Status.IsFailed {
		t.Error("expected IsFailed=true")
	}
	if !resp.Attributes.HasAnyErrors {
		t.Error("expected HasAnyErrors=true")
	}
	if resp.Errors == nil {
		t.Fatal("expected Errors to be present when IncludeErrors=true")
	}
	if !strings.Contains(resp.Errors.BackendMessage, "E5001") {
		t.Errorf("expected BackendMessage to contain error code, got %q", resp.Errors.BackendMessage)
	}
	results, _ := resp.Results.([]interface{})
	if len(results) != 0 {
		t.Errorf("expected empty results, got %d", len(results))
	}
}

func TestError_WithErrorsDisabled(t *testing.T) {
	SetDebugConfig(DebugConfig{IncludeErrors: false})
	defer SetDebugConfig(DefaultDebugConfig())

	resp := Error(500, "E5001", "Something failed")

	if !resp.Attributes.HasAnyErrors {
		t.Error("expected HasAnyErrors=true even when errors disabled")
	}
	if resp.Errors != nil {
		t.Error("expected Errors=nil when IncludeErrors=false")
	}
}

func TestWithBackendTrace_DebugOn(t *testing.T) {
	SetDebugConfig(DebugConfig{IncludeErrors: true, IncludeStackTrace: true, MaxStackFrames: 10})
	defer SetDebugConfig(DefaultDebugConfig())

	lines := []string{"handler.go:85 HandlePluginList", "service.go:120 FetchPlugins"}
	resp := Error(500, "E5001", "fail").WithBackendTrace(lines)

	if resp.Errors == nil {
		t.Fatal("expected Errors to be present")
	}
	if len(resp.Errors.Backend) != 2 {
		t.Errorf("expected 2 backend trace lines, got %d", len(resp.Errors.Backend))
	}
}

func TestWithBackendTrace_DebugOff(t *testing.T) {
	SetDebugConfig(DebugConfig{IncludeErrors: true, IncludeStackTrace: false})
	defer SetDebugConfig(DefaultDebugConfig())

	lines := []string{"handler.go:85 HandlePluginList"}
	resp := Error(500, "E5001", "fail").WithBackendTrace(lines)

	if resp.Errors != nil && len(resp.Errors.Backend) > 0 {
		t.Error("expected no backend trace when IncludeStackTrace=false")
	}
}

func TestWithBackendTrace_MaxFrames(t *testing.T) {
	SetDebugConfig(DebugConfig{IncludeErrors: true, IncludeStackTrace: true, MaxStackFrames: 1})
	defer SetDebugConfig(DefaultDebugConfig())

	lines := []string{"a.go:1", "b.go:2", "c.go:3"}
	resp := Error(500, "E5001", "fail").WithBackendTrace(lines)

	if len(resp.Errors.Backend) != 1 {
		t.Errorf("expected 1 frame (max), got %d", len(resp.Errors.Backend))
	}
}
