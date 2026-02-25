package wordpress

import (
	"encoding/json"
	"fmt"
	"io"
	"strings"

	"wp-plugin-publish/pkg/apperror"
)

// GetPlugins returns a list of installed plugins
func (c *Client) GetPlugins() ([]PluginInfo, error) {
	endpoint := WPCorePlugins
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != HttpStatusOk.Int() {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get plugins list",
			Method:       "GET",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var plugins []PluginInfo
	if err := json.NewDecoder(resp.Body).Decode(&plugins); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode plugins response").
			WithEndpoint(endpoint)
	}

	return plugins, nil
}

// GetPlugin returns information about a specific plugin
func (c *Client) GetPlugin(slug string) (*PluginInfo, error) {
	endpoint := fmt.Sprintf(WPCorePluginBySlug, escapePathSegmentPreservingPercent(slug))
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode == HttpStatusNotFound.Int() {
		return nil, &APIError{
			Operation:    "get plugin (not found)",
			Method:       "GET",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: "",
			PluginSlugIn: slug,
		}
	}

	if resp.StatusCode != HttpStatusOk.Int() {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get plugin",
			Method:       "GET",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
			PluginSlugIn: slug,
		}
	}

	var plugin PluginInfo
	if err := json.NewDecoder(resp.Body).Decode(&plugin); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode plugin response").
			WithSlug(slug)
	}

	return &plugin, nil
}

// ResolvePluginIdentifier attempts to map a short slug (e.g. "akismet") to the full plugin
// identifier used by WP REST API (e.g. "akismet/akismet.php").
// If slug already looks like a full identifier (contains "/"), it is returned as-is.
func (c *Client) ResolvePluginIdentifier(slug string) (string, error) {
	slug = strings.TrimSpace(slug)
	if slug == "" {
		return "", apperror.New(apperror.ErrValidation, "empty plugin slug")
	}
	if strings.Contains(slug, "/") {
		if !strings.HasSuffix(slug, ".php") {
			slug = slug + ".php"
		}
		return slug, nil
	}

	plugs, err := c.GetPlugins()
	if err != nil {
		return slug, err
	}

	target := strings.ToLower(slug)
	for _, p := range plugs {
		pluginID := strings.ToLower(strings.TrimSpace(p.Plugin))
		textDomain := strings.ToLower(strings.TrimSpace(p.TextDomain))

		if pluginID == target || textDomain == target || strings.HasPrefix(pluginID, target+"/") {
			return p.Plugin, nil
		}
	}

	return slug, apperror.New(apperror.ErrNotFound, "plugin not found").
		WithSlug(slug)
}
