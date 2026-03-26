// Package urlutil provides URL normalization utilities.
package urlutil

import (
	"net/url"
	"strings"
)

// NormalizeWordPressUrl normalizes a WordPress site URL for consistent storage.
// It ensures HTTPS, strips common WP paths, and removes query/fragment.
func NormalizeWordPressUrl(rawUrl string) string {
	rawUrl = ensureScheme(strings.TrimSpace(rawUrl))

	parsed, err := url.Parse(rawUrl)
	if err != nil {
		return strings.TrimSuffix(rawUrl, "/")
	}

	parsed.Path = stripWordPressPaths(parsed.Path)
	parsed.RawQuery = ""
	parsed.Fragment = ""
	return parsed.String()
}

// ensureScheme prepends https:// if no scheme is present.
func ensureScheme(rawUrl string) string {
	hasHttpPrefix := strings.HasPrefix(rawUrl, "http://")
	hasHttpsPrefix := strings.HasPrefix(rawUrl, "https://")
	hasScheme :=
		hasHttpPrefix ||
			hasHttpsPrefix

	if hasScheme {
		return rawUrl
	}

	return "https://" + rawUrl
}

// stripWordPressPaths removes common WordPress path suffixes from a URL path.
func stripWordPressPaths(path string) string {
	pathsToStrip := []string{"/wp-admin/", "/wp-admin", "/wp-login.php", "/wp-json/", "/wp-json"}
	for _, p := range pathsToStrip {
		if strings.HasPrefix(path, p) {
			return strings.TrimSuffix(strings.TrimPrefix(path, strings.TrimSuffix(p, "/")), "/")
		}
		if strings.HasSuffix(path, p) {
			return strings.TrimSuffix(strings.TrimSuffix(path, p), "/")
		}
	}
	return strings.TrimSuffix(path, "/")
}
