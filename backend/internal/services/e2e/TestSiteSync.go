// Package e2e - Site, sync, and publish test implementations
package e2e

import (
	"context"
	"fmt"

	"wp-plugin-publish/pkg/apperror"
)

// --- Site Connection Tests ---

func (s *serviceImpl) testRegisterSite(ctx context.Context, result *TestResult) *apperror.AppError {
	body := siteCreateBody{
		Name: "E2E Test Site", Url: s.testSiteUrl,
		Username: s.testSiteUsername, Password: s.testSitePassword,
	}
	result.RequestData = toJson(redactSiteBody(body))

	resp, appErr := s.api.post("/sites", body)

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest, "POST /sites failed")
	}
	result.ResponseData = resp.RawBody

	appErr = expectSuccess(resp)

	if appErr != nil {
		return appErr
	}

	hasIdField := resp.hasDataField("id")
	if !hasIdField {
		return apperror.New(apperror.ErrE2EAssertion, "expected data object in response")
	}

	id, ok := resp.dataFieldFloat("id")
	if ok {
		s.setCleanupId("site", int64(id))
	}

	return nil
}

func (s *serviceImpl) testSiteConnection(ctx context.Context, result *TestResult) *apperror.AppError {
	siteId, appErr := s.createTestSite()

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ESetup, "test setup failed")
	}
	defer s.cleanupSite(siteId)

	result.RequestData = fmt.Sprintf(`{"siteId": %d}`, siteId)

	resp, appErr := s.api.post(fmt.Sprintf("/sites/%d/test", siteId), nil)

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest,
			fmt.Sprintf("POST /sites/%d/test failed", siteId))
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 {
		return apperror.New(apperror.ErrE2EAssertion,
			fmt.Sprintf("connection test failed with HTTP %d: %s", resp.StatusCode, resp.RawBody))
	}

	return nil
}

func (s *serviceImpl) testInvalidCredentials(ctx context.Context, result *TestResult) *apperror.AppError {
	body := credentialsTestBody{
		Url: s.testSiteUrl, Username: "invalid_user_e2e", Password: "invalid_password_e2e",
	}
	result.RequestData = toJson(body)

	resp, appErr := s.api.post("/sites/test", body)

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest, "POST /sites/test failed")
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 500 {
		return apperror.New(apperror.ErrE2EAssertion,
			fmt.Sprintf("unexpected server error HTTP %d", resp.StatusCode))
	}

	return nil
}

func (s *serviceImpl) testCreatePluginMapping(ctx context.Context, result *TestResult) *apperror.AppError {
	ids, appErr := s.setupPluginAndSite()

	if appErr != nil {
		return appErr
	}
	defer s.cleanupPlugin(ids.PluginId)
	defer s.cleanupSite(ids.SiteId)

	body := mappingCreateBody{SiteId: ids.SiteId, RemoteSlug: "e2e-test-plugin"}
	result.RequestData = toJson(body)

	resp, appErr := s.api.post(fmt.Sprintf("/plugins/%d/mappings", ids.PluginId), body)

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest,
			fmt.Sprintf("POST /plugins/%d/mappings failed", ids.PluginId))
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
func (s *serviceImpl) setupPluginAndSite() (*testIds, *apperror.AppError) {
	pluginId, appErr := s.createTestPlugin()

	if appErr != nil {
		return nil, apperror.Wrap(appErr, apperror.ErrE2ESetup, "setup plugin")
	}

	siteId, appErr := s.createTestSite()

	if appErr != nil {
		s.cleanupPlugin(pluginId)

		return nil, apperror.Wrap(appErr, apperror.ErrE2ESetup, "setup site")
	}

	return &testIds{PluginId: pluginId, SiteId: siteId}, nil
}

// --- Sync Tests ---

func (s *serviceImpl) testDetectNewFiles(ctx context.Context, result *TestResult) *apperror.AppError {
	pluginId, appErr := s.createTestPlugin()

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ESetup, "test setup failed")
	}
	defer s.cleanupPlugin(pluginId)

	resp, appErr := s.api.post(fmt.Sprintf("/watcher/scan/%d", pluginId), nil)

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest, "POST /watcher/scan failed")
	}
	result.RequestData = fmt.Sprintf(`{"pluginId": %d, "action": "scan"}`, pluginId)
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 {
		return apperror.New(apperror.ErrE2EAssertion,
			fmt.Sprintf("scan failed with HTTP %d", resp.StatusCode))
	}

	return nil
}

func (s *serviceImpl) testBatchScanAll(ctx context.Context, result *TestResult) *apperror.AppError {
	result.RequestData = `{"action": "scan-all"}`

	resp, appErr := s.api.post("/watcher/scan-all", nil)

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest, "POST /watcher/scan-all failed")
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 {
		return apperror.New(apperror.ErrE2EAssertion,
			fmt.Sprintf("scan-all failed with HTTP %d", resp.StatusCode))
	}

	return nil
}

// --- Publish Tests ---

func (s *serviceImpl) testPreviewPublish(ctx context.Context, result *TestResult) *apperror.AppError {
	ids, appErr := s.createTestMapping()

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ESetup, "test setup failed")
	}
	defer s.cleanupPlugin(ids.PluginId)
	defer s.cleanupSite(ids.SiteId)

	body := publishPreviewBody{PluginId: ids.PluginId, SiteId: ids.SiteId}
	result.RequestData = toJson(body)

	resp, appErr := s.api.post("/publish/preview", body)

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest, "POST /publish/preview failed")
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 500 {
		return apperror.New(apperror.ErrE2EAssertion,
			fmt.Sprintf("unexpected server error HTTP %d", resp.StatusCode))
	}

	return nil
}

func (s *serviceImpl) testBackupList(ctx context.Context, result *TestResult) *apperror.AppError {
	pluginId, appErr := s.createTestPlugin()

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ESetup, "test setup failed")
	}
	defer s.cleanupPlugin(pluginId)

	result.RequestData = fmt.Sprintf(`{"pluginId": %d}`, pluginId)

	resp, appErr := s.api.get(fmt.Sprintf("/backups/%d", pluginId))

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest,
			fmt.Sprintf("GET /backups/%d failed", pluginId))
	}
	result.ResponseData = resp.RawBody

	if resp.StatusCode >= 400 {
		return apperror.New(apperror.ErrE2EAssertion,
			fmt.Sprintf("expected success, got HTTP %d", resp.StatusCode))
	}

	return nil
}
