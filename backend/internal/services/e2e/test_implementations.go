// Package e2e - Real HTTP-based test implementations
package e2e

import (
	"context"
	"encoding/json"
	"fmt"
)

// --- Typed request body structs (replaces map[string]any literals per GE-1) ---

// pluginCreateBody is the request body for plugin creation.
type pluginCreateBody struct {
	Name        string `json:"name"`
	Path        string `json:"path"`
	ForceCreate bool   `json:"forceCreate,omitempty"`
}

// pluginUpdateBody is the request body for plugin update.
type pluginUpdateBody struct {
	Name string `json:"name"`
}

// siteCreateBody is the request body for site registration.
type siteCreateBody struct {
	Name     string `json:"name"`
	URL      string `json:"url"`
	Username string `json:"username"`
	Password string `json:"password"`
}

// mappingCreateBody is the request body for plugin-site mapping.
type mappingCreateBody struct {
	SiteID     int64  `json:"siteId"`
	RemoteSlug string `json:"remoteSlug"`
}

// publishPreviewBody is the request body for publish preview.
type publishPreviewBody struct {
	PluginID int64 `json:"pluginId"`
	SiteID   int64 `json:"siteId"`
}

// credentialsTestBody is the request body for credentials testing.
type credentialsTestBody struct {
	URL      string `json:"url"`
	Username string `json:"username"`
	Password string `json:"password"`
}

// --- Plugin CRUD Tests ---

func (s *serviceImpl) testRegisterPlugin(ctx context.Context, result *TestResult) error {
	body := pluginCreateBody{
		Name:        "E2E Test Plugin",
		Path:        s.testPluginPath,
		ForceCreate: true,
	}
	result.RequestData = toJSON(body)

	resp, err := s.api.post("/plugins", body)
	if err != nil {
		return fmt.Errorf("POST /plugins failed: %w", err)
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 || !resp.Success {
		return fmt.Errorf("expected success, got HTTP %d: %s", resp.StatusCode, resp.errorCode())
	}

	if !resp.hasDataField("id") {
		return fmt.Errorf("expected 'id' field in response data")
	}

	// Verify it appears in list
	listResp, err := s.api.get("/plugins")
	if err != nil {
		return fmt.Errorf("GET /plugins failed: %w", err)
	}
	if !listResp.isDataArray() {
		return fmt.Errorf("plugin list is empty after creation")
	}

	// Store created ID for cleanup
	if id, ok := resp.dataFieldFloat("id"); ok {
		s.setCleanupID("plugin", int64(id))
	}

	return nil
}

func (s *serviceImpl) testRegisterInvalidPath(ctx context.Context, result *TestResult) error {
	body := pluginCreateBody{
		Name: "Invalid Plugin",
		Path: "/nonexistent/path/e2e-test-invalid",
	}
	result.RequestData = toJSON(body)

	resp, err := s.api.post("/plugins", body)
	if err != nil {
		return fmt.Errorf("POST /plugins failed: %w", err)
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode < 400 {
		return fmt.Errorf("expected error response, got HTTP %d", resp.StatusCode)
	}

	code := resp.errorCode()
	if code == "" {
		return fmt.Errorf("expected error code in response")
	}

	return nil
}

func (s *serviceImpl) testUpdatePlugin(ctx context.Context, result *TestResult) error {
	// Create a plugin first
	pluginID, err := s.createTestPlugin()
	if err != nil {
		return fmt.Errorf("setup: %w", err)
	}
	defer s.cleanupPlugin(pluginID)

	body := pluginUpdateBody{Name: "E2E Updated Plugin"}
	result.RequestData = toJSON(body)

	resp, err := s.api.put(fmt.Sprintf("/plugins/%d", pluginID), body)
	if err != nil {
		return fmt.Errorf("PUT /plugins/%d failed: %w", pluginID, err)
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 || !resp.Success {
		return fmt.Errorf("expected success, got HTTP %d", resp.StatusCode)
	}

	// Verify update
	getResp, err := s.api.get(fmt.Sprintf("/plugins/%d", pluginID))
	if err != nil {
		return fmt.Errorf("GET /plugins/%d failed: %w", pluginID, err)
	}
	name := getResp.dataField("name")
	if name != "E2E Updated Plugin" {
		return fmt.Errorf("expected name 'E2E Updated Plugin', got '%s'", name)
	}

	return nil
}

func (s *serviceImpl) testDeletePlugin(ctx context.Context, result *TestResult) error {
	pluginID, err := s.createTestPlugin()
	if err != nil {
		return fmt.Errorf("setup: %w", err)
	}

	result.RequestData = fmt.Sprintf(`{"id": %d}`, pluginID)

	resp, err := s.api.del(fmt.Sprintf("/plugins/%d", pluginID))
	if err != nil {
		return fmt.Errorf("DELETE /plugins/%d failed: %w", pluginID, err)
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 {
		return fmt.Errorf("expected success, got HTTP %d", resp.StatusCode)
	}

	// Verify deletion
	getResp, err := s.api.get(fmt.Sprintf("/plugins/%d", pluginID))
	if err != nil {
		return fmt.Errorf("GET /plugins/%d failed: %w", pluginID, err)
	}
	if getResp.StatusCode < 400 {
		return fmt.Errorf("expected 404 after delete, got HTTP %d", getResp.StatusCode)
	}

	return nil
}

func (s *serviceImpl) testScanPluginFiles(ctx context.Context, result *TestResult) error {
	pluginID, err := s.createTestPlugin()
	if err != nil {
		return fmt.Errorf("setup: %w", err)
	}
	defer s.cleanupPlugin(pluginID)

	result.RequestData = fmt.Sprintf(`{"pluginId": %d}`, pluginID)

	resp, err := s.api.post(fmt.Sprintf("/watcher/scan/%d", pluginID), nil)
	if err != nil {
		return fmt.Errorf("POST /watcher/scan/%d failed: %w", pluginID, err)
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 {
		return fmt.Errorf("expected success, got HTTP %d", resp.StatusCode)
	}

	return nil
}

// --- Site Connection Tests ---

func (s *serviceImpl) testRegisterSite(ctx context.Context, result *TestResult) error {
	body := siteCreateBody{
		Name:     "E2E Test Site",
		URL:      s.testSiteURL,
		Username: s.testSiteUsername,
		Password: s.testSitePassword,
	}
	result.RequestData = toJSON(redactSiteBody(body))

	resp, err := s.api.post("/sites", body)
	if err != nil {
		return fmt.Errorf("POST /sites failed: %w", err)
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 || !resp.Success {
		return fmt.Errorf("expected success, got HTTP %d: %s", resp.StatusCode, resp.errorCode())
	}

	if !resp.hasDataField("id") {
		return fmt.Errorf("expected data object in response")
	}

	if id, ok := resp.dataFieldFloat("id"); ok {
		s.setCleanupID("site", int64(id))
	}

	return nil
}

func (s *serviceImpl) testSiteConnection(ctx context.Context, result *TestResult) error {
	siteID, err := s.createTestSite()
	if err != nil {
		return fmt.Errorf("setup: %w", err)
	}
	defer s.cleanupSite(siteID)

	result.RequestData = fmt.Sprintf(`{"siteId": %d}`, siteID)

	resp, err := s.api.post(fmt.Sprintf("/sites/%d/test", siteID), nil)
	if err != nil {
		return fmt.Errorf("POST /sites/%d/test failed: %w", siteID, err)
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 {
		return fmt.Errorf("connection test failed with HTTP %d: %s", resp.StatusCode, resp.RawBody)
	}

	return nil
}

func (s *serviceImpl) testInvalidCredentials(ctx context.Context, result *TestResult) error {
	body := credentialsTestBody{
		URL:      s.testSiteURL,
		Username: "invalid_user_e2e",
		Password: "invalid_password_e2e",
	}
	result.RequestData = toJSON(body)

	resp, err := s.api.post("/sites/test", body)
	if err != nil {
		return fmt.Errorf("POST /sites/test failed: %w", err)
	}
	result.ResponseData = resp.RawBody

	// We expect either an error response or a response indicating auth failure
	// (some sites might return 200 with connected=false, others might 401)
	if resp.StatusCode >= 500 {
		return fmt.Errorf("unexpected server error HTTP %d", resp.StatusCode)
	}

	return nil
}

func (s *serviceImpl) testCreatePluginMapping(ctx context.Context, result *TestResult) error {
	pluginID, err := s.createTestPlugin()
	if err != nil {
		return fmt.Errorf("setup plugin: %w", err)
	}
	defer s.cleanupPlugin(pluginID)

	siteID, err := s.createTestSite()
	if err != nil {
		return fmt.Errorf("setup site: %w", err)
	}
	defer s.cleanupSite(siteID)

	body := mappingCreateBody{
		SiteID:     siteID,
		RemoteSlug: "e2e-test-plugin",
	}
	result.RequestData = toJSON(body)

	resp, err := s.api.post(fmt.Sprintf("/plugins/%d/mappings", pluginID), body)
	if err != nil {
		return fmt.Errorf("POST /plugins/%d/mappings failed: %w", pluginID, err)
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 || !resp.Success {
		return fmt.Errorf("expected success, got HTTP %d", resp.StatusCode)
	}

	return nil
}

// --- Sync Tests ---

func (s *serviceImpl) testDetectNewFiles(ctx context.Context, result *TestResult) error {
	pluginID, err := s.createTestPlugin()
	if err != nil {
		return fmt.Errorf("setup: %w", err)
	}
	defer s.cleanupPlugin(pluginID)

	// Scan to detect files
	resp, err := s.api.post(fmt.Sprintf("/watcher/scan/%d", pluginID), nil)
	if err != nil {
		return fmt.Errorf("POST /watcher/scan failed: %w", err)
	}
	result.RequestData = fmt.Sprintf(`{"pluginId": %d, "action": "scan"}`, pluginID)
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 {
		return fmt.Errorf("scan failed with HTTP %d", resp.StatusCode)
	}

	return nil
}

func (s *serviceImpl) testBatchScanAll(ctx context.Context, result *TestResult) error {
	result.RequestData = `{"action": "scan-all"}`

	resp, err := s.api.post("/watcher/scan-all", nil)
	if err != nil {
		return fmt.Errorf("POST /watcher/scan-all failed: %w", err)
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 {
		return fmt.Errorf("scan-all failed with HTTP %d", resp.StatusCode)
	}

	return nil
}

// --- Publish Tests ---

func (s *serviceImpl) testPreviewPublish(ctx context.Context, result *TestResult) error {
	pluginID, siteID, err := s.createTestMapping()
	if err != nil {
		return fmt.Errorf("setup: %w", err)
	}
	defer s.cleanupPlugin(pluginID)
	defer s.cleanupSite(siteID)

	body := publishPreviewBody{
		PluginID: pluginID,
		SiteID:   siteID,
	}
	result.RequestData = toJSON(body)

	resp, err := s.api.post("/publish/preview", body)
	if err != nil {
		return fmt.Errorf("POST /publish/preview failed: %w", err)
	}
	result.ResponseData = resp.RawBody

	// Preview may fail if no remote connection, but should not 500
	if resp.StatusCode >= 500 {
		return fmt.Errorf("unexpected server error HTTP %d", resp.StatusCode)
	}

	return nil
}

func (s *serviceImpl) testBackupList(ctx context.Context, result *TestResult) error {
	pluginID, err := s.createTestPlugin()
	if err != nil {
		return fmt.Errorf("setup: %w", err)
	}
	defer s.cleanupPlugin(pluginID)

	result.RequestData = fmt.Sprintf(`{"pluginId": %d}`, pluginID)

	resp, err := s.api.get(fmt.Sprintf("/backups/%d", pluginID))
	if err != nil {
		return fmt.Errorf("GET /backups/%d failed: %w", pluginID, err)
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 {
		return fmt.Errorf("expected success, got HTTP %d", resp.StatusCode)
	}

	return nil
}

// --- Helper Methods ---

// createTestPlugin creates a plugin for testing and returns its ID
func (s *serviceImpl) createTestPlugin() (int64, error) {
	resp, err := s.api.post("/plugins", pluginCreateBody{
		Name:        "E2E Temp Plugin",
		Path:        s.testPluginPath,
		ForceCreate: true,
	})
	if err != nil {
		return 0, err
	}
	if resp.StatusCode >= 400 {
		return 0, fmt.Errorf("create plugin failed: HTTP %d - %s", resp.StatusCode, resp.RawBody)
	}
	id, ok := resp.dataFieldFloat("id")
	if !ok {
		return 0, fmt.Errorf("no id in create plugin response")
	}
	return int64(id), nil
}

// createTestSite creates a site for testing and returns its ID
func (s *serviceImpl) createTestSite() (int64, error) {
	resp, err := s.api.post("/sites", siteCreateBody{
		Name:     "E2E Temp Site",
		URL:      s.testSiteURL,
		Username: s.testSiteUsername,
		Password: s.testSitePassword,
	})
	if err != nil {
		return 0, err
	}
	if resp.StatusCode >= 400 {
		return 0, fmt.Errorf("create site failed: HTTP %d - %s", resp.StatusCode, resp.RawBody)
	}
	id, ok := resp.dataFieldFloat("id")
	if !ok {
		return 0, fmt.Errorf("no id in create site response")
	}
	return int64(id), nil
}

// createTestMapping creates a plugin + site + mapping for testing
func (s *serviceImpl) createTestMapping() (int64, int64, error) {
	pluginID, err := s.createTestPlugin()
	if err != nil {
		return 0, 0, fmt.Errorf("create plugin: %w", err)
	}
	siteID, err := s.createTestSite()
	if err != nil {
		s.cleanupPlugin(pluginID)
		return 0, 0, fmt.Errorf("create site: %w", err)
	}
	_, err = s.api.post(fmt.Sprintf("/plugins/%d/mappings", pluginID), mappingCreateBody{
		SiteID:     siteID,
		RemoteSlug: "e2e-test-plugin",
	})
	if err != nil {
		s.cleanupPlugin(pluginID)
		s.cleanupSite(siteID)
		return 0, 0, fmt.Errorf("create mapping: %w", err)
	}
	return pluginID, siteID, nil
}

func (s *serviceImpl) cleanupPlugin(id int64) {
	s.api.del(fmt.Sprintf("/plugins/%d", id))
}

func (s *serviceImpl) cleanupSite(id int64) {
	s.api.del(fmt.Sprintf("/sites/%d", id))
}

func (s *serviceImpl) setCleanupID(kind string, id int64) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.cleanupIDs == nil {
		s.cleanupIDs = make(map[string][]int64)
	}
	s.cleanupIDs[kind] = append(s.cleanupIDs[kind], id)
}

func (s *serviceImpl) runCleanup() {
	s.mu.Lock()
	ids := s.cleanupIDs
	s.cleanupIDs = nil
	s.mu.Unlock()

	if ids == nil {
		return
	}
	for _, id := range ids["plugin"] {
		s.cleanupPlugin(id)
	}
	for _, id := range ids["site"] {
		s.cleanupSite(id)
	}
}

// toJSON marshals to JSON string for logging
func toJSON(v any) string {
	b, _ := json.Marshal(v)
	return string(b)
}

// redactSiteBody creates a copy of the site body with password redacted
func redactSiteBody(body siteCreateBody) siteCreateBody {
	return siteCreateBody{
		Name:     body.Name,
		URL:      body.URL,
		Username: body.Username,
		Password: "***REDACTED***",
	}
}
