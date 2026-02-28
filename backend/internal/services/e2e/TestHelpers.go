package e2e

import (
	"encoding/json"
	"fmt"

	"wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) createTestPlugin() (int64, *apperror.AppError) {
	resp, appErr := s.api.post("/plugins", pluginCreateBody{
		Name: "E2E Test Plugin", Path: s.testPluginPath, ForceCreate: true,
	})

	if appErr != nil {
		return 0, appErr
	}

	return extractId(resp, "create plugin")
}

func (s *serviceImpl) createTestSite() (int64, *apperror.AppError) {
	resp, appErr := s.api.post("/sites", siteCreateBody{
		Name: "E2E Temp Site", Url: s.testSiteUrl,
		Username: s.testSiteUsername, Password: s.testSitePassword,
	})

	if appErr != nil {
		return 0, appErr
	}

	return extractId(resp, "create site")
}

// extractId pulls an int64 id from an API response.
func extractId(resp *apiResponse, action string) (int64, *apperror.AppError) {
	isErrorStatus := resp.StatusCode >= 400

	if isErrorStatus {
		return 0, apperror.New(apperror.ErrE2EAssertion,
			fmt.Sprintf("%s failed: HTTP %d - %s", action, resp.StatusCode, resp.RawBody))
	}

	id, ok := resp.dataFieldFloat("id")
	isIdMissing := !ok

	if isIdMissing {
		return 0, apperror.New(apperror.ErrE2EAssertion,
			fmt.Sprintf("no id in %s response", action))
	}

	return int64(id), nil
}

func (s *serviceImpl) createTestMapping() (*testIds, *apperror.AppError) {
	ids, appErr := s.setupPluginAndSite()

	if appErr != nil {
		return nil, appErr
	}

	_, appErr = s.api.post(fmt.Sprintf("/plugins/%d/mappings", ids.PluginId), mappingCreateBody{
		SiteId: ids.SiteId, RemoteSlug: "e2e-test-plugin",
	})

	if appErr != nil {
		s.cleanupPlugin(ids.PluginId)
		s.cleanupSite(ids.SiteId)

		return nil, apperror.Wrap(appErr, apperror.ErrE2ESetup, "create mapping")
	}

	return ids, nil
}

func (s *serviceImpl) cleanupPlugin(id int64) {
	s.api.del(fmt.Sprintf("/plugins/%d", id))
}

func (s *serviceImpl) cleanupSite(id int64) {
	s.api.del(fmt.Sprintf("/sites/%d", id))
}

// setCleanupId stores the resource id for later cleanup.
func (s *serviceImpl) setCleanupId(resourceType string, id int64) {
	s.mu.Lock()
	defer s.mu.Unlock()

	if s.cleanupIds == nil {
		s.cleanupIds = make(map[string]int64)
	}
	s.cleanupIds[resourceType] = id
}

// getCleanupId retrieves a stored resource id.
func (s *serviceImpl) getCleanupId(resourceType string) (int64, bool) {
	s.mu.RLock()
	defer s.mu.RUnlock()

	id, ok := s.cleanupIds[resourceType]

	return id, ok
}

// cleanupAll removes all stored test resources.
func (s *serviceImpl) cleanupAll() {
	s.mu.RLock()
	ids := make(map[string]int64)
	for k, v := range s.cleanupIds {
		ids[k] = v
	}
	s.mu.RUnlock()

	for resourceType, id := range ids {
		switch resourceType {
		case "plugin":
			s.cleanupPlugin(id)
		case "site":
			s.cleanupSite(id)
		}
	}
}

// runCleanup runs post-test cleanup.
func (s *serviceImpl) runCleanup() {
	s.cleanupAll()
}

// expectSuccess returns an error if the response is not successful.
func expectSuccess(resp *apiResponse) *apperror.AppError {
	isSuccess := resp.Success

	if isSuccess {
		return nil
	}

	return apperror.New(apperror.ErrE2EAssertion,
		fmt.Sprintf("expected success, got HTTP %d: %s", resp.StatusCode, resp.RawBody))
}

func toJson(v any) string {
	b, _ := json.Marshal(v)

	return string(b)
}

func redactSiteBody(body siteCreateBody) siteCreateBody {
	return siteCreateBody{
		Name: body.Name, Url: body.Url,
		Username: body.Username, Password: "***REDACTED***",
	}
}
