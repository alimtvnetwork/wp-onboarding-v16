package apperror

import "strings"

// NormalizePluginSlug trims, validates, and normalizes a plugin slug.
// If the slug contains a "/" (e.g., "plugin/file"), it appends ".php" when missing.
// Returns (normalizedSlug, nil) on success, or ("", *AppError) on validation failure.
func NormalizePluginSlug(slug string) (string, *AppError) {
	slug = strings.TrimSpace(slug)

	if slug == "" {
		return "", New(ErrValidation, "empty plugin slug")
	}

	if strings.Contains(slug, "/") {
		if !strings.HasSuffix(slug, ".php") {
			slug = slug + ".php"
		}
		return slug, nil
	}

	return slug, nil
}
