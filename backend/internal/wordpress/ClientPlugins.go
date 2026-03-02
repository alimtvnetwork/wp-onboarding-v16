package wordpress

import (
	"encoding/json"
	"fmt"
	"strings"

	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	operationtype "wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/pkg/apperror"
)

// GetPlugins returns a list of installed plugins
func (c *Client) GetPlugins() apperror.Result[[]PluginInfo] {
	rawResult := c.doApiCallRaw(apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  WPCorePlugins,
		Operation: operationtype.GetPluginsList,
	})
	if rawResult.HasError() {
		return apperror.Fail[[]PluginInfo](rawResult.AppError())
	}

	var plugins []PluginInfo
	unmarshalErr := json.Unmarshal(rawResult.Value(), &plugins)

	if unmarshalErr != nil {
		return apperror.FailWrap[[]PluginInfo](unmarshalErr, apperror.ErrInternal, "failed to decode plugins response")
	}

	return apperror.Ok(plugins)
}

// GetPlugin returns information about a specific plugin
func (c *Client) GetPlugin(slug string) apperror.Result[PluginInfo] {
	endpoint := fmt.Sprintf(WPCorePluginBySlug, escapePathSegmentPreservingPercent(slug))

	rawResult := c.doApiCallRaw(apiCallInput{
		Method:     httpmethod.Get,
		Endpoint:   endpoint,
		Operation:  operationtype.GetPlugin,
		PluginSlug: slug,
	})
	if rawResult.HasError() {
		return apperror.Fail[PluginInfo](rawResult.AppError())
	}

	var plugin PluginInfo
	unmarshalErr := json.Unmarshal(rawResult.Value(), &plugin)

	if unmarshalErr != nil {
		return apperror.FailWrap[PluginInfo](unmarshalErr, apperror.ErrInternal, "failed to decode plugin response")
	}

	return apperror.Ok(plugin)
}

// ResolvePluginIdentifier attempts to map a short slug (e.g. "akismet") to the full plugin
// identifier used by WP REST API (e.g. "akismet/akismet.php").
// If slug already looks like a full identifier (contains "/"), it is returned as-is.
func (c *Client) ResolvePluginIdentifier(slug string) apperror.Result[string] {
	slug = strings.TrimSpace(slug)
	isSlugEmpty := slug == ""

	if isSlugEmpty {
		return apperror.FailNew[string](apperror.ErrValidation, "empty plugin slug")
	}
	if strings.Contains(slug, "/") {
		isPhpExtensionMissing := !strings.HasSuffix(slug, ".php")

		if isPhpExtensionMissing {
			slug = slug + ".php"
		}
		return apperror.Ok(slug)
	}

	plugsResult := c.GetPlugins()
	if plugsResult.HasError() {
		return apperror.Fail[string](plugsResult.AppError())
	}

	plugs := plugsResult.Value()
	target := strings.ToLower(slug)
	for _, p := range plugs {
		pluginId := strings.ToLower(strings.TrimSpace(p.Plugin))
		textDomain := strings.ToLower(strings.TrimSpace(p.TextDomain))

		if pluginId == target || textDomain == target || strings.HasPrefix(pluginId, target+"/") {
			return apperror.Ok(p.Plugin)
		}
	}

	return apperror.Fail[string](apperror.New(apperror.ErrNotFound, "plugin not found").WithSlug(slug))
}
