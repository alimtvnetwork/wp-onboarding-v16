// Package urlutil provides URL normalization utilities.
package urlutil

import "strings"

// NormalizeWordPressUrl strips common WordPress paths and enforces HTTPS.
func NormalizeWordPressUrl(rawUrl string) string {
	u := strings.TrimSpace(rawUrl)
	u = strings.TrimRight(u, "/")

	for _, suffix := range []string{"/wp-admin", "/wp-login.php", "/wp-json"} {
		u = strings.TrimSuffix(u, suffix)
	}

	isHttp := strings.HasPrefix(u, "http://")

	if isHttp {
		u = "https://" + strings.TrimPrefix(u, "http://")
	}

	isHttpsMissing := !strings.HasPrefix(u, "https://")

	if isHttpsMissing {
		u = "https://" + u
	}

	return u
}
