package wordpress

import (
	"fmt"
	"os/exec"
	"strings"
)

// ExtractAPIError returns the *APIError from an error, or nil if not an APIError.
// This is the centralized extraction point — callers MUST use this instead of inline type assertions.
func ExtractAPIError(err error) *APIError {
	if err == nil {
		return nil
	}
	apiErr, ok := err.(*APIError)
	if !ok {
		return nil
	}
	return apiErr
}

// ExtractExitError returns the *exec.ExitError from an error, or nil if not an ExitError.
func ExtractExitError(err error) *exec.ExitError {
	if err == nil {
		return nil
	}
	exitErr, ok := err.(*exec.ExitError)
	if !ok {
		return nil
	}
	return exitErr
}

// APIError contains rich request/response context for failed WordPress REST calls.
// It intentionally keeps Error() short/stable (so user-facing messages remain readable)
// while exposing full diagnostics via fields.
type APIError struct {
	Operation     string
	Method        string
	Endpoint      string
	Url           string
	StatusCode    int
	RequestBody   string // The JSON body sent in the request
	ResponseBody  string
	PluginSlugIn  string
	PluginIdUsed  string
	StackTrace    string // Captured stack trace at error time
}

func (e *APIError) Error() string {
	op := e.Operation
	if op == "" {
		op = "WordPress API request failed"
	}

	req := ""
	if e.Method != "" || e.Endpoint != "" {
		req = fmt.Sprintf(" (%s %s)", strings.ToUpper(e.Method), e.Endpoint)
	} else if e.Url != "" {
		req = fmt.Sprintf(" (%s)", e.Url)
	}

	return fmt.Sprintf("%s%s: status %d", op, req, e.StatusCode)
}

// FullError returns the complete error message with response body for logging
func (e *APIError) FullError() string {
	msg := e.Error()
	if e.ResponseBody != "" {
		msg += fmt.Sprintf("\nResponse Body: %s", e.ResponseBody)
	}
	if e.StackTrace != "" {
		msg += fmt.Sprintf("\n--- Stack Trace ---\n%s--- End Stack Trace ---", e.StackTrace)
	}
	return msg
}

// ConnectionInfo represents WordPress connection details (built internally, not parsed from external)
type ConnectionInfo struct {
	IsConnected      bool
	Connected        bool     // legacy compat
	Username         string
	WPVersion        string   `json:",omitempty"`
	SiteName         string   `json:",omitempty"`
	SiteDescription  string   `json:",omitempty"`
	UserId           int      `json:",omitempty"`
	UserDisplayName  string   `json:",omitempty"`
	UserRoles        []string `json:",omitempty"`
	CanManagePlugins bool
	CanWritePosts    bool
}

// PluginInfo represents a WordPress plugin (parsed from WordPress REST API)
type PluginInfo struct {
	Plugin      string `json:"plugin"`       // external key (WordPress REST API)
	Status      string `json:"status"`       // external key
	Name        string `json:"name"`         // external key
	PluginURI   string `json:"plugin_uri"`   // external key
	Author      string `json:"author"`       // external key
	AuthorURI   string `json:"author_uri"`   // external key
	Description struct {
		Raw      string `json:"raw"`      // external key
		Rendered string `json:"rendered"` // external key
	} `json:"description"` // external key
	Version     string `json:"version"`      // external key
	NetworkOnly bool   `json:"network_only"` // external key
	RequiresWP  string `json:"requires_wp"`  // external key
	RequiresPHP string `json:"requires_php"` // external key
	TextDomain  string `json:"textdomain"`   // external key
}
