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
		Name: "E2E Test Site", Url: s.testSiteUrl,
		Username: s.testSiteUsername, Password: s.testSitePassword,
	}
	result.RequestData = toJson(redactSiteBody(body))

	resp, err := s.api.post("/sites", body)
	if err != nil {
		return fmt.Errorf("POST /sites failed: %w", err)
	}
	result.ResponseData = resp.RawBody

	err = expectSuccess(resp)
	if err != nil {
		return err
	}
	if resp.isDataMissing("id") {
		return fmt.Errorf("expected data object in response")
	}

	id, ok := resp.dataFieldFloat("id")
	if ok {
		s.setCleanupId("site", int64(id))
	}
	return nil
}

func (s *serviceImpl) testSiteConnection(ctx context.Context, result *TestResult) error {
	siteId, err := s.createTestSite()
	if err != nil {
		return fmt.Errorf("setup: %w", err)
	}
	defer s.cleanupSite(siteId)

	result.RequestData = fmt.Sprintf(`{"siteId": %d}`, siteId)

	resp, err := s.api.post(fmt.Sprintf("/sites/%d/test", siteId), nil)
	if err != nil {
		return fmt.Errorf("POST /sites/%d/test failed: %w", siteId, err)
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 {
		return fmt.Errorf("connection test failed with HTTP %d: %s", resp.StatusCode, resp.RawBody)
	}
	return nil
}

func (s *serviceImpl) testInvalidCredentials(ctx context.Context, result *TestResult) error {
	body := credentialsTestBody{
		Url: s.testSiteUrl, Username: "invalid_user_e2e", Password: "invalid_password_e2e",
	}
	result.RequestData = toJson(body)

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
	ids, err := s.setupPluginAndSite()
	if err != nil {
		return err
	}
	defer s.cleanupPlugin(ids.PluginId)
	defer s.cleanupSite(ids.SiteId)

	body := mappingCreateBody{SiteId: ids.SiteId, RemoteSlug: "e2e-test-plugin"}
	result.RequestData = toJson(body)

	resp, err := s.api.post(fmt.Sprintf("/plugins/%d/mappings", ids.PluginId), body)
	if err != nil {
		return fmt.Errorf("POST /plugins/%d/mappings failed: %w", ids.PluginId, err)
	}
	result.ResponseData = resp.RawBody

	return expectSuccess(resp)
}

// testIds holds IDs created during test setup.
type testIds struct {
	PluginId int64
	SiteId   int64
}

// setupPluginAndSite creates a test plugin and site, returning their IDs.
func (s *serviceImpl) setupPluginAndSite() (*testIds, error) {
	pluginId, err := s.createTestPlugin()
	if err != nil {
		return nil, fmt.Errorf("setup plugin: %w", err)
	}
	siteId, err := s.createTestSite()
	if err != nil {
		s.cleanupPlugin(pluginId)
		return nil, fmt.Errorf("setup site: %w", err)
	}
	return &testIds{PluginId: pluginId, SiteId: siteId}, nil
}

// --- Sync Tests ---

func (s *serviceImpl) testDetectNewFiles(ctx context.Context, result *TestResult) error {
	pluginId, err := s.createTestPlugin()
	if err != nil {
		return fmt.Errorf("setup: %w", err)
	}
	defer s.cleanupPlugin(pluginId)

	resp, err := s.api.post(fmt.Sprintf("/watcher/scan/%d", pluginId), nil)
	if err != nil {
		return fmt.Errorf("POST /watcher/scan failed: %w", err)
	}
	result.RequestData = fmt.Sprintf(`{"pluginId": %d, "action": "scan"}`, pluginId)
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
	ids, err := s.createTestMapping()
	if err != nil {
		return fmt.Errorf("setup: %w", err)
	}
	defer s.cleanupPlugin(ids.PluginId)
	defer s.cleanupSite(ids.SiteId)

	body := publishPreviewBody{PluginId: ids.PluginId, SiteId: ids.SiteId}
	result.RequestData = toJson(body)

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
	pluginId, err := s.createTestPlugin()
	if err != nil {
		return fmt.Errorf("setup: %w", err)
	}
	defer s.cleanupPlugin(pluginId)

	result.RequestData = fmt.Sprintf(`{"pluginId": %d}`, pluginId)

	resp, err := s.api.get(fmt.Sprintf("/backups/%d", pluginId))
	if err != nil {
		return fmt.Errorf("GET /backups/%d failed: %w", pluginId, err)
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 {
		return fmt.Errorf("expected success, got HTTP %d", resp.StatusCode)
	}
	return nil
}
