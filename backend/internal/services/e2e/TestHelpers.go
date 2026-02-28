// Package e2e - Test helper methods and utilities
package e2e

import (
	"encoding/json"
	"fmt"
)

// --- Helper Methods ---

func (s *serviceImpl) createTestPlugin() (int64, error) {
	resp, err := s.api.post("/plugins", pluginCreateBody{
		Name: "E2E Temp Plugin", Path: s.testPluginPath, ForceCreate: true,
	})
	if err != nil {
		return 0, err
	}
	return extractID(resp, "create plugin")
}

func (s *serviceImpl) createTestSite() (int64, error) {
	resp, err := s.api.post("/sites", siteCreateBody{
		Name: "E2E Temp Site", URL: s.testSiteURL,
		Username: s.testSiteUsername, Password: s.testSitePassword,
	})
	if err != nil {
		return 0, err
	}
	return extractID(resp, "create site")
}

// extractID pulls an int64 id from an API response.
func extractID(resp *apiResponse, action string) (int64, error) {
	isErrorStatus := resp.StatusCode >= 400

	if isErrorStatus {
		return 0, fmt.Errorf("%s failed: HTTP %d - %s", action, resp.StatusCode, resp.RawBody)
	}
	id, ok := resp.dataFieldFloat("id")
	if !ok {
		return 0, fmt.Errorf("no id in %s response", action)
	}
	return int64(id), nil
}

func (s *serviceImpl) createTestMapping() (*testIds, error) {
	ids, err := s.setupPluginAndSite()
	if err != nil {
		return nil, err
	}

	_, err = s.api.post(fmt.Sprintf("/plugins/%d/mappings", ids.PluginID), mappingCreateBody{
		SiteID: ids.SiteID, RemoteSlug: "e2e-test-plugin",
	})
	if err != nil {
		s.cleanupPlugin(ids.PluginID)
		s.cleanupSite(ids.SiteID)
		return nil, fmt.Errorf("create mapping: %w", err)
	}
	return ids, nil
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

	isCleanupEmpty := ids == nil

	if isCleanupEmpty {
		return
	}
	for _, id := range ids["plugin"] {
		s.cleanupPlugin(id)
	}
	for _, id := range ids["site"] {
		s.cleanupSite(id)
	}
}

// expectSuccess returns an error if the response is not successful.
func expectSuccess(resp *apiResponse) error {
	isFailure := resp.StatusCode >= 400 || !resp.Success
	if isFailure {
		return fmt.Errorf("expected success, got HTTP %d: %s", resp.StatusCode, resp.errorCode())
	}
	return nil
}

func toJSON(v any) string {
	b, _ := json.Marshal(v)
	return string(b)
}

func redactSiteBody(body siteCreateBody) siteCreateBody {
	return siteCreateBody{
		Name: body.Name, URL: body.URL,
		Username: body.Username, Password: "***REDACTED***",
	}
}
