// Package e2e - Site, sync, and publish test implementations
package e2e

import (
	"context"
	"encoding/json"
	"fmt"
)

// --- Site Connection Tests ---

func (s *serviceImpl) testRegisterSite(ctx context.Context, result *TestResult) error {
	body := siteCreateBody{
		Name: "E2E Test Site", URL: s.testSiteURL,
		Username: s.testSiteUsername, Password: s.testSitePassword,
	}
	result.RequestData = toJSON(redactSiteBody(body))

	resp, err := s.api.post("/sites", body)
	if err != nil {
		return fmt.Errorf("POST /sites failed: %w", err)
	}
	result.ResponseData = resp.RawBody

	if err := expectSuccess(resp); err != nil {
		return err
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
		URL: s.testSiteURL, Username: "invalid_user_e2e", Password: "invalid_password_e2e",
	}
	result.RequestData = toJSON(body)

	resp, err := s.api.post("/sites/test", body)
	if err != nil {
		return fmt.Errorf("POST /sites/test failed: %w", err)
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 500 {
		return fmt.Errorf("unexpected server error HTTP %d", resp.StatusCode)
	}
	return nil
}

func (s *serviceImpl) testCreatePluginMapping(ctx context.Context, result *TestResult) error {
	pluginID, siteID, err := s.setupPluginAndSite()
	if err != nil {
		return err
	}
	defer s.cleanupPlugin(pluginID)
	defer s.cleanupSite(siteID)

	body := mappingCreateBody{SiteID: siteID, RemoteSlug: "e2e-test-plugin"}
	result.RequestData = toJSON(body)

	resp, err := s.api.post(fmt.Sprintf("/plugins/%d/mappings", pluginID), body)
	if err != nil {
		return fmt.Errorf("POST /plugins/%d/mappings failed: %w", pluginID, err)
	}
	result.ResponseData = resp.RawBody

	return expectSuccess(resp)
}

// setupPluginAndSite creates a test plugin and site, returning their IDs.
func (s *serviceImpl) setupPluginAndSite() (int64, int64, error) {
	pluginID, err := s.createTestPlugin()
	if err != nil {
		return 0, 0, fmt.Errorf("setup plugin: %w", err)
	}
	siteID, err := s.createTestSite()
	if err != nil {
		s.cleanupPlugin(pluginID)
		return 0, 0, fmt.Errorf("setup site: %w", err)
	}
	return pluginID, siteID, nil
}

// --- Sync Tests ---

func (s *serviceImpl) testDetectNewFiles(ctx context.Context, result *TestResult) error {
	pluginID, err := s.createTestPlugin()
	if err != nil {
		return fmt.Errorf("setup: %w", err)
	}
	defer s.cleanupPlugin(pluginID)

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

	body := publishPreviewBody{PluginID: pluginID, SiteID: siteID}
	result.RequestData = toJSON(body)

	resp, err := s.api.post("/publish/preview", body)
	if err != nil {
		return fmt.Errorf("POST /publish/preview failed: %w", err)
	}
	result.ResponseData = resp.RawBody

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
