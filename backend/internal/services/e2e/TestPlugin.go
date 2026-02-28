// Package e2e - Test body types and plugin CRUD tests
package e2e

import (
	"context"
	"fmt"
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
	URL      string
	Username string
	Password string
}

type mappingCreateBody struct {
	SiteID     int64
	RemoteSlug string
}

type publishPreviewBody struct {
	PluginID int64
	SiteID   int64
}

type credentialsTestBody struct {
	URL      string
	Username string
	Password string
}

// --- Plugin CRUD Tests ---

func (s *serviceImpl) testRegisterPlugin(ctx context.Context, result *TestResult) error {
	body := pluginCreateBody{Name: "E2E Test Plugin", Path: s.testPluginPath, ForceCreate: true}
	result.RequestData = toJSON(body)

	resp, err := s.api.post("/plugins", body)
	if err != nil {
		return fmt.Errorf("POST /plugins failed: %w", err)
	}
	result.ResponseData = resp.RawBody

	if err = expectSuccess(resp); err != nil {
		return err
	}
	if resp.isDataMissing("id") {
		return fmt.Errorf("expected 'id' field in response data")
	}

	return s.verifyAndStorePlugin(resp)
}

// verifyAndStorePlugin confirms the plugin list is non-empty and stores the cleanup ID.
func (s *serviceImpl) verifyAndStorePlugin(resp *apiResponse) error {
	if err := s.verifyPluginInList(); err != nil {
		return err
	}

	id, ok := resp.dataFieldFloat("id")
	if ok {
		s.setCleanupID("plugin", int64(id))
	}
	return nil
}

// verifyPluginInList confirms the plugin list is non-empty.
func (s *serviceImpl) verifyPluginInList() error {
	listResp, err := s.api.get("/plugins")
	if err != nil {
		return fmt.Errorf("GET /plugins failed: %w", err)
	}
	isListEmpty := !listResp.isDataArray()

	if isListEmpty {
		return fmt.Errorf("plugin list is empty after creation")
	}
	return nil
}

func (s *serviceImpl) testRegisterInvalidPath(ctx context.Context, result *TestResult) error {
	body := pluginCreateBody{Name: "Invalid Plugin", Path: "/nonexistent/path/e2e-test-invalid"}
	result.RequestData = toJSON(body)

	resp, err := s.api.post("/plugins", body)
	if err != nil {
		return fmt.Errorf("POST /plugins failed: %w", err)
	}
	result.ResponseData = resp.RawBody

	isSuccessStatus := resp.StatusCode < 400

	if isSuccessStatus {
		return fmt.Errorf("expected error response, got HTTP %d", resp.StatusCode)
	}

	isErrorCodeMissing := resp.errorCode() == ""

	if isErrorCodeMissing {
		return fmt.Errorf("expected error code in response")
	}
	return nil
}

func (s *serviceImpl) testUpdatePlugin(ctx context.Context, result *TestResult) error {
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

	err = expectSuccess(resp)
	if err != nil {
		return err
	}

	return s.verifyPluginName(pluginID, "E2E Updated Plugin")
}

// verifyPluginName confirms a plugin has the expected name.
func (s *serviceImpl) verifyPluginName(id int64, expected string) error {
	getResp, err := s.api.get(fmt.Sprintf("/plugins/%d", id))
	if err != nil {
		return fmt.Errorf("GET /plugins/%d failed: %w", id, err)
	}
	name := getResp.dataField("name")
	isNameMismatch := name != expected

	if isNameMismatch {
		return fmt.Errorf("expected name '%s', got '%s'", expected, name)
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

	isErrorStatus := resp.StatusCode >= 400

	if isErrorStatus {
		return fmt.Errorf("expected success, got HTTP %d", resp.StatusCode)
	}

	return s.verifyPluginDeleted(pluginID)
}

// verifyPluginDeleted confirms a plugin returns 404.
func (s *serviceImpl) verifyPluginDeleted(id int64) error {
	getResp, err := s.api.get(fmt.Sprintf("/plugins/%d", id))
	if err != nil {
		return fmt.Errorf("GET /plugins/%d failed: %w", id, err)
	}
	isSuccessStatus := getResp.StatusCode < 400

	if isSuccessStatus {
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

	isErrorStatus := resp.StatusCode >= 400

	if isErrorStatus {
		return fmt.Errorf("expected success, got HTTP %d", resp.StatusCode)
	}

	return nil
}
