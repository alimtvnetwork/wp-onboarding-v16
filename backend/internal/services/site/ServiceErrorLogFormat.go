// Package site — error log formatting helpers
package site

import (
	"fmt"
	"strings"

	ep "wp-plugin-publish/internal/enums/endpoint"
	"wp-plugin-publish/internal/wordpress"
)

// guardRailInput bundles parameters for formatGuardRailSection.
type guardRailInput struct {
	Action  string
	SiteUrl string
	Details *ExtractedErrorDetails
	Method  string
}

// formatGuardRailSection formats the WP Core mutation guard rail section.
func formatGuardRailSection(input guardRailInput) string {
	isNonPluginEndpoint := !strings.Contains(input.Details.Endpoint, "/wp/v2/plugins")
	isReadOnly := input.Method == "GET"
	if isNonPluginEndpoint || isReadOnly {
		return "    This request was correctly delegated through the Riseup Uploader endpoint.\n"
	}

	requiredEndpoint := resolveRequiredEndpoint(input.Action, input.SiteUrl)
	entry := "    WARNING: This request was sent to a WordPress Core endpoint instead of the Riseup Uploader.\n"
	entry += fmt.Sprintf("  Guard Rail:\n    Blocked Direct WP Core Mutation: true\n    Blocked Endpoint: %s\n    Required Delegation Endpoint: %s\n", input.Details.Endpoint, requiredEndpoint)
	return entry
}

// resolveRequiredEndpoint maps an action to its required Riseup delegation endpoint.
func resolveRequiredEndpoint(action, siteUrl string) string {
	switch action {
	case "disable":
		return fmt.Sprintf("%s/wp-json/%s%s", siteUrl, wordpress.RiseupAsiaNamespace, ep.Disable.String())
	case "enable":
		return fmt.Sprintf("%s/wp-json/%s%s", siteUrl, wordpress.RiseupAsiaNamespace, ep.Enable.String())
	case "delete":
		return fmt.Sprintf("%s/wp-json/%s%s", siteUrl, wordpress.RiseupAsiaNamespace, ep.Delete.String())
	default:
		return fmt.Sprintf("%s/wp-json/%s/plugins/%s", siteUrl, wordpress.RiseupAsiaNamespace, action)
	}
}

// formatStackTraceSection formats PHP stack trace frames for the log entry.
func formatStackTraceSection(details *ExtractedErrorDetails) string {
	if len(details.StackTraceFrames) == 0 {
		return ""
	}

	entry := "  PHP Stack Trace Frames:\n"
	for i, frame := range details.StackTraceFrames {
		if frame.Class != "" {
			entry += fmt.Sprintf("    #%d %s::%s() at %s:%d\n", i, frame.Class, frame.Function, frame.File, frame.Line)
		} else {
			entry += fmt.Sprintf("    #%d %s() at %s:%d\n", i, frame.Function, frame.File, frame.Line)
		}
	}
	return entry
}

// formatPhpErrorsSection formats remote PHP error sessions for the log entry.
func formatPhpErrorsSection(details *ExtractedErrorDetails) string {
	if len(details.RemotePhpErrors) == 0 {
		return ""
	}

	entry := fmt.Sprintf("  Remote PHP Error Sessions (%d entries):\n", len(details.RemotePhpErrors))
	for i, phpErr := range details.RemotePhpErrors {
		entry += fmt.Sprintf("    [%d] [%s] %s\n        File: %s  Line: %d  At: %s\n", i+1, strings.ToUpper(phpErr.Level), phpErr.Message, phpErr.File, phpErr.Line, phpErr.CreatedAt)
	}
	return entry
}
