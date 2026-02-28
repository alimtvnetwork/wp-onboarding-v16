package wordpress

import (
	"encoding/json"
	"fmt"
	"strings"

	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/pkg/apperror"
)

// GetPlugins returns a list of installed plugins
func (c *Client) GetPlugins() ([]PluginInfo, *apperror.AppError) {
	data, err := c.doAPICallRaw(apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  WPCorePlugins,
		Operation: "get plugins list",
	})
	if err != nil {
		return nil, err
	}

	var plugins []PluginInfo
	if unmarshalErr := json.Unmarshal(data, &plugins); unmarshalErr != nil {
		return nil, apperror.Wrap(unmarshalErr, apperror.ErrInternal, "failed to decode plugins response").
			WithEndpoint(WPCorePlugins)
	}
	return plugins, nil
}

// GetPlugin returns information about a specific plugin
func (c *Client) GetPlugin(slug string) (*PluginInfo, *apperror.AppError) {
	endpoint := fmt.Sprintf(WPCorePluginBySlug, escapePathSegmentPreservingPercent(slug))

	data, err := c.doAPICallRaw(apiCallInput{
		Method:     httpmethod.Get,
		Endpoint:   endpoint,
		Operation:  "get plugin",
		PluginSlug: slug,
	})
	if err != nil {
		return nil, err
	}

	var plugin PluginInfo
	if unmarshalErr := json.Unmarshal(data, &plugin); unmarshalErr != nil {
		return nil, apperror.Wrap(unmarshalErr, apperror.ErrInternal, "failed to decode plugin response").
			WithSlug(slug)
	}
	return &plugin, nil
}

// ResolvePluginIdentifier attempts to map a short slug (e.g. "akismet") to the full plugin
// identifier used by WP REST API (e.g. "akismet/akismet.php").
// If slug already looks like a full identifier (contains "/"), it is returned as-is.
func (c *Client) ResolvePluginIdentifier(slug string) (string, *apperror.AppError) {
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
