package e2e

import (
	"encoding/json"
	"fmt"
)

func (s *serviceImpl) createTestPlugin() (int64, error) {
	resp, err := s.api.post("/plugins", pluginCreateBody{
		Name: "E2E Test Plugin", Path: s.testPluginPath, ForceCreate: true,
	})
	if err != nil {
		return 0, err
	}
	return extractID(resp, "create plugin")
}

func (s *serviceImpl) createTestSite() (int64, error) {
	resp, err := s.api.post("/sites", siteCreateBody{
		Name: "E2E Temp Site", Url: s.testSiteURL,
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

	_, err = s.api.post(fmt.Sprintf("/plugins/%d/mappings", ids.PluginId), mappingCreateBody{
		SiteId: ids.SiteId, RemoteSlug: "e2e-test-plugin",
	})
	if err != nil {
		s.cleanupPlugin(ids.PluginId)
		s.cleanupSite(ids.SiteId)
		return nil, fmt.Errorf("create mapping: %w", err)
	}
	return ids, nil
}

func (s *serviceImpl) cleanupPlugin(id int64) {
	s.api.delete(fmt.Sprintf("/plugins/%d", id))
}

func (s *serviceImpl) cleanupSite(id int64) {
	s.api.delete(fmt.Sprintf("/sites/%d", id))
}

// setCleanupID stores the resource id for later cleanup.
func (s *serviceImpl) setCleanupID(resourceType string, id int64) {
	s.mu.Lock()
	defer s.mu.Unlock()

	if s.cleanupIDs == nil {
		s.cleanupIDs = make(map[string]int64)
	}
	s.cleanupIDs[resourceType] = id
}

// getCleanupID retrieves a stored resource id.
func (s *serviceImpl) getCleanupID(resourceType string) (int64, bool) {
	s.mu.RLock()
	defer s.mu.RUnlock()

	id, ok := s.cleanupIDs[resourceType]
	return id, ok
}

// cleanupAll removes all stored test resources.
func (s *serviceImpl) cleanupAll() {
	s.mu.RLock()
	ids := make(map[string]int64)
	for k, v := range s.cleanupIDs {
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

	return
}

func toJSON(v any) string {
	b, _ := json.Marshal(v)
	return string(b)
}

func redactSiteBody(body siteCreateBody) siteCreateBody {
	return siteCreateBody{
		Name: body.Name, Url: body.Url,
		Username: body.Username, Password: "***REDACTED***",
	}
}
