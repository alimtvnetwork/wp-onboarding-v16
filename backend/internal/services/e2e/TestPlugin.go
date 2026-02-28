// Package e2e - Test body types and plugin CRUD tests
package e2e

import (
	"context"
	"fmt"

	"wp-plugin-publish/pkg/apperror"
)

// --- Typed request body structs ---

type pluginCreateBody struct {
	Name        string
	Path        string
	ForceCreate bool `json:",omitempty"`
}

type pluginUpdateBody struct {
	Name string
}

type siteCreateBody struct {
	Name     string
	Url      string
	Username string
	Password string
}

type mappingCreateBody struct {
	SiteId     int64
	RemoteSlug string
}

type publishPreviewBody struct {
	PluginId int64
	SiteId   int64
}

type credentialsTestBody struct {
	Url      string
	Username string
	Password string
}

// --- Plugin CRUD Tests ---

func (s *serviceImpl) testRegisterPlugin(ctx context.Context, result *TestResult) *apperror.AppError {
	body := pluginCreateBody{Name: "E2E Test Plugin", Path: s.testPluginPath, ForceCreate: true}
	result.RequestData = toJson(body)

	resp, appErr := s.api.post("/plugins", body)

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest, "POST /plugins failed")
	}
	result.ResponseData = resp.RawBody

	appErr = expectSuccess(resp)

	if appErr != nil {
		return appErr
	}

	if resp.isDataMissing("id") {
		return apperror.New(apperror.ErrE2EAssertion, "expected 'id' field in response data")
	}

	return s.verifyAndStorePlugin(resp)
}

// verifyAndStorePlugin confirms the plugin list is non-empty and stores the cleanup ID.
func (s *serviceImpl) verifyAndStorePlugin(resp *apiResponse) *apperror.AppError {
	appErr := s.verifyPluginInList()

	if appErr != nil {
		return appErr
	}

	id, ok := resp.dataFieldFloat("id")
	if ok {
		s.setCleanupId("plugin", int64(id))
	}

	return nil
}

// verifyPluginInList confirms the plugin list is non-empty.
func (s *serviceImpl) verifyPluginInList() *apperror.AppError {
	listResp, appErr := s.api.get("/plugins")

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest, "GET /plugins failed")
	}

	isListEmpty := !listResp.isDataArray()

	if isListEmpty {
		return apperror.New(apperror.ErrE2EAssertion, "plugin list is empty after creation")
	}

	return nil
}

func (s *serviceImpl) testRegisterInvalidPath(ctx context.Context, result *TestResult) *apperror.AppError {
	body := pluginCreateBody{Name: "Invalid Plugin", Path: "/nonexistent/path/e2e-test-invalid"}
	result.RequestData = toJson(body)

	resp, appErr := s.api.post("/plugins", body)

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest, "POST /plugins failed")
	}
	result.ResponseData = resp.RawBody

	isSuccessStatus := resp.StatusCode < 400

	if isSuccessStatus {
		return apperror.New(apperror.ErrE2EAssertion,
			fmt.Sprintf("expected error response, got HTTP %d", resp.StatusCode))
	}

	isErrorCodeMissing := resp.errorCode() == ""

	if isErrorCodeMissing {
		return apperror.New(apperror.ErrE2EAssertion, "expected error code in response")
	}

	return nil
}

func (s *serviceImpl) testUpdatePlugin(ctx context.Context, result *TestResult) *apperror.AppError {
	pluginId, appErr := s.createTestPlugin()

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ESetup, "test setup failed")
	}
	defer s.cleanupPlugin(pluginId)

	body := pluginUpdateBody{Name: "E2E Updated Plugin"}
	result.RequestData = toJson(body)

	resp, appErr := s.api.put(fmt.Sprintf("/plugins/%d", pluginId), body)

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest,
			fmt.Sprintf("PUT /plugins/%d failed", pluginId))
	}
	result.ResponseData = resp.RawBody

	appErr = expectSuccess(resp)

	if appErr != nil {
		return appErr
	}

	return s.verifyPluginName(pluginId, "E2E Updated Plugin")
}

// verifyPluginName confirms a plugin has the expected name.
func (s *serviceImpl) verifyPluginName(id int64, expected string) *apperror.AppError {
	getResp, appErr := s.api.get(fmt.Sprintf("/plugins/%d", id))

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest,
			fmt.Sprintf("GET /plugins/%d failed", id))
	}

	name := getResp.dataField("name")
	isNameMismatch := name != expected

	if isNameMismatch {
		return apperror.New(apperror.ErrE2EAssertion,
			fmt.Sprintf("expected name '%s', got '%s'", expected, name))
	}

	return nil
}

func (s *serviceImpl) testDeletePlugin(ctx context.Context, result *TestResult) *apperror.AppError {
	pluginId, appErr := s.createTestPlugin()

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ESetup, "test setup failed")
	}

	result.RequestData = fmt.Sprintf(`{"id": %d}`, pluginId)

	resp, appErr := s.api.del(fmt.Sprintf("/plugins/%d", pluginId))

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest,
			fmt.Sprintf("DELETE /plugins/%d failed", pluginId))
	}
	result.ResponseData = resp.RawBody

	isErrorStatus := resp.StatusCode >= 400

	if isErrorStatus {
		return apperror.New(apperror.ErrE2EAssertion,
			fmt.Sprintf("expected success, got HTTP %d", resp.StatusCode))
	}

	return s.verifyPluginDeleted(pluginId)
}

// verifyPluginDeleted confirms a plugin returns 404.
func (s *serviceImpl) verifyPluginDeleted(id int64) *apperror.AppError {
	getResp, appErr := s.api.get(fmt.Sprintf("/plugins/%d", id))

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest,
			fmt.Sprintf("GET /plugins/%d failed", id))
	}

	isSuccessStatus := getResp.StatusCode < 400

	if isSuccessStatus {
		return apperror.New(apperror.ErrE2EAssertion,
			fmt.Sprintf("expected 404 after delete, got HTTP %d", getResp.StatusCode))
	}

	return nil
}

func (s *serviceImpl) testScanPluginFiles(ctx context.Context, result *TestResult) *apperror.AppError {
	pluginId, appErr := s.createTestPlugin()

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ESetup, "test setup failed")
	}
	defer s.cleanupPlugin(pluginId)

	result.RequestData = fmt.Sprintf(`{"pluginId": %d}`, pluginId)

	resp, appErr := s.api.post(fmt.Sprintf("/watcher/scan/%d", pluginId), nil)

	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrE2ERequest,
			fmt.Sprintf("POST /watcher/scan/%d failed", pluginId))
	}
	result.ResponseData = resp.RawBody

	isErrorStatus := resp.StatusCode >= 400

	if isErrorStatus {
		return apperror.New(apperror.ErrE2EAssertion,
			fmt.Sprintf("expected success, got HTTP %d", resp.StatusCode))
	}

	return nil
}
