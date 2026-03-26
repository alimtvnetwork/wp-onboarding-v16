// Package pluginutil provides WordPress plugin slug normalization utilities.
package pluginutil

import (
	"path/filepath"
	"strings"

	"wp-plugin-publish/pkg/apperror"
)

// NormalizeSlug trims, validates, and normalizes a plugin slug.
// If the slug contains a "/" (e.g., "plugin/file"), it appends ".php" when missing.
// Returns (normalizedSlug, nil) on success, or ("", *AppError) on validation failure.
func NormalizeSlug(slug string) (string, *apperror.AppError) {
	slug = strings.TrimSpace(slug)

	isSlugEmpty := slug == ""

	if isSlugEmpty {
		return "", apperror.New(apperror.ErrValidation, "empty plugin slug")
	}

	if strings.Contains(slug, "/") {
		hasPhpExtension := strings.HasSuffix(slug, ".php")

		if !hasPhpExtension {
			slug = slug + ".php"
		}
		return slug, nil
	}

	return slug, nil
}

// ExtractFolderSlug extracts the folder-level slug from a full WordPress plugin
// identifier like "broken-link-checker/broken-link-checker.php".
// Returns just the directory portion, or the slug with ".php" stripped.
func ExtractFolderSlug(slug string) string {
	if strings.Contains(slug, "/") {
		dir := filepath.Dir(slug)
		isDirValid := dir != "." && dir != ""

		if isDirValid {
			return dir
		}
	}

	slug = strings.TrimSuffix(slug, ".php")
	return slug
}
