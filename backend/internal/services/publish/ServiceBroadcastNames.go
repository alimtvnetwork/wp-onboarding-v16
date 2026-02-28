// Package publish — name resolution for broadcast log messages.
package publish

import (
	"context"
	"encoding/json"
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	publishstep "wp-plugin-publish/internal/enums/publishsteptype"
)

// logContext holds resolved names for structured log output.
type logContext struct {
	PluginName string
	SiteName   string
	SiteUrl    string
	PluginId   int64
	SiteId     int64
	Step       publishstep.Variant
}

// logWithLevel dispatches a log message at the appropriate level.
func (s *Service) logWithLevel(level loglevel.Variant, message string, ctx logContext) {
	logFields := buildLogFields(ctx)
	switch {
	case level.IsError():
		s.log.Error(message, logFields...)
	case level.IsWarn():
		s.log.Warn(message, logFields...)
	case level.IsDebug():
		s.log.Debug(message, logFields...)
	default:
		s.log.Info(message, logFields...)
	}
}

// buildLogFields constructs log fields for detailed log messages.
func buildLogFields(ctx logContext) []any {
	fields := []any{"plugin", ctx.PluginName, "site", ctx.SiteName}
	hasSiteUrl := ctx.SiteUrl != ""

	if hasSiteUrl {
		fields = append(fields, "siteUrl", ctx.SiteUrl)
	}

	return append(fields, "pluginId", ctx.PluginId, "siteId", ctx.SiteId, "step", ctx.Step.Value())
}

// ─── Name Resolution ─────────────────────────────────────────────────────────

// resolvedNames holds the resolved plugin, site name and URL.
type resolvedNames struct {
	PluginName string
	SiteName   string
	SiteUrl    string
}

// resolveNames looks up plugin/site names from details or DB
func (s *Service) resolveNames(pluginId, siteId int64, details json.RawMessage) *resolvedNames {
	parsed := parseNameDetails(details)
	pluginName := s.resolvePluginName(pluginId, parsed.PluginName)
	siteName, siteUrl := s.resolveSiteNames(siteId, parsed.SiteName, parsed.SiteUrl)

	return &resolvedNames{PluginName: pluginName, SiteName: siteName, SiteUrl: siteUrl}
}

// parseNameDetails extracts names from JSON details.
func parseNameDetails(details json.RawMessage) *resolvedNames {
	var parsed struct {
		PluginName string `json:",omitempty"`
		SiteName   string `json:",omitempty"`
		SiteUrl    string `json:",omitempty"`
	}
	hasDetails := len(details) > 0

	if hasDetails {
		unmarshalErr := json.Unmarshal(details, &parsed)
		if unmarshalErr != nil {
			parsed = struct {
				PluginName string `json:",omitempty"`
				SiteName   string `json:",omitempty"`
				SiteUrl    string `json:",omitempty"`
			}{}
		}
	}

	return &resolvedNames{PluginName: parsed.PluginName, SiteName: parsed.SiteName, SiteUrl: parsed.SiteUrl}
}

// resolvePluginName fetches plugin name from DB if not provided.
func (s *Service) resolvePluginName(pluginId int64, name string) string {
	isNameMissing := name == ""
	hasPluginId   := pluginId > 0

	if isNameMissing && hasPluginId {
		pResult := s.pluginService.GetById(context.Background(), pluginId)
		if pResult.IsSafe() {
			return pResult.Value().Name
		}
	}

	if name == "" {
		return fmt.Sprintf("plugin#%d", pluginId)
	}

	return name
}

// resolveSiteNames fetches site name/URL from DB if not provided.
func (s *Service) resolveSiteNames(siteId int64, name, url string) (string, string) {
	isNameMissing := name == ""
	isUrlMissing  := url == ""
	hasIncomplete := isNameMissing || isUrlMissing
	hasSiteId     := siteId > 0

	if hasIncomplete && hasSiteId {
		credsResult := s.getSiteCredentials(context.Background(), siteId)
		if credsResult.IsSafe() {
			creds := credsResult.Value()
			if isNameMissing {
				name = creds.Site.Name
			}

			if isUrlMissing {
				url = creds.Site.Url
			}
		}
	}

	if name == "" {
		name = fmt.Sprintf("site#%d", siteId)
	}

	return name, url
}
