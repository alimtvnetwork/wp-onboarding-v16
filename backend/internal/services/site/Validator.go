// Package site - Input validation
package site

import (
	"net/url"
	"regexp"
	"strings"

	"wp-plugin-publish/pkg/apperror"
)

var (
	// Minimum password length for application passwords (WordPress format is 24 chars with spaces)
	minPasswordLength = 20

	// URL must start with http:// or https://
	urlSchemeRegex = regexp.MustCompile(`^https?://`)
)

// ValidateSiteURL validates a WordPress site URL
func ValidateSiteURL(siteURL string) *apperror.AppError {
	siteURL = strings.TrimSpace(siteURL)

	if siteURL == "" {
		return apperror.New(apperror.ErrValidation, "URL is required")
	}

	// Add https if no scheme
	if !urlSchemeRegex.MatchString(siteURL) {
		siteURL = "https://" + siteURL
	}

	parsed, err := url.Parse(siteURL)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrValidation, "invalid URL format")
	}

	if parsed.Host == "" {
		return apperror.New(apperror.ErrValidation, "URL must include a host")
	}

	// Check for common issues
	if strings.Contains(parsed.Path, "wp-admin") {
		return apperror.New(apperror.ErrValidation, "URL should not include /wp-admin")
	}

	return nil
}

// ValidateUsername validates a WordPress username
func ValidateUsername(username string) *apperror.AppError {
	username = strings.TrimSpace(username)

	if username == "" {
		return apperror.New(apperror.ErrValidation, "username is required")
	}

	if len(username) < 1 || len(username) > 60 {
		return apperror.New(apperror.ErrValidation, "username must be between 1 and 60 characters")
	}

	return nil
}

// ValidateApplicationPassword validates a WordPress application password
func ValidateApplicationPassword(password string) *apperror.AppError {
	// Remove spaces (WordPress displays app passwords with spaces)
	password = strings.ReplaceAll(password, " ", "")

	if password == "" {
		return apperror.New(apperror.ErrValidation, "application password is required")
	}

	if len(password) < minPasswordLength {
		return apperror.New(apperror.ErrValidation, "application password appears too short")
	}

	return nil
}

// ValidateSiteName validates a site display name
func ValidateSiteName(name string) *apperror.AppError {
	name = strings.TrimSpace(name)

	if name == "" {
		return apperror.New(apperror.ErrValidation, "name is required")
	}

	if len(name) > 100 {
		return apperror.New(apperror.ErrValidation, "name must be 100 characters or less")
	}

	return nil
}

// SanitizeApplicationPassword removes spaces from application passwords
func SanitizeApplicationPassword(password string) string {
	return strings.ReplaceAll(strings.TrimSpace(password), " ", "")
}
