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

// ValidateSiteUrl validates a WordPress site URL
func ValidateSiteUrl(siteUrl string) *apperror.AppError {
	siteUrl = strings.TrimSpace(siteUrl)
	isUrlEmpty := siteUrl == ""

	if isUrlEmpty {
		return apperror.New(apperror.ErrValidation, "URL is required")
	}

	// Add https if no scheme
	isSchemeMissing := !urlSchemeRegex.MatchString(siteUrl)

	if isSchemeMissing {
		siteUrl = "https://" + siteUrl
	}

	parsed, err := url.Parse(siteUrl)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrValidation, "invalid URL format")
	}

	isHostEmpty := parsed.Host == ""

	if isHostEmpty {
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
	isUsernameEmpty := username == ""

	if isUsernameEmpty {
		return apperror.New(apperror.ErrValidation, "username is required")
	}

	isUsernameTooLong := len(username) > 60

	if isUsernameTooLong {
		return apperror.New(apperror.ErrValidation, "username must be between 1 and 60 characters")
	}

	return nil
}

// ValidateApplicationPassword validates a WordPress application password
func ValidateApplicationPassword(password string) *apperror.AppError {
	// Remove spaces (WordPress displays app passwords with spaces)
	password = strings.ReplaceAll(password, " ", "")

	isPasswordEmpty := password == ""

	if isPasswordEmpty {
		return apperror.New(apperror.ErrValidation, "application password is required")
	}

	isPasswordTooShort := len(password) < minPasswordLength

	if isPasswordTooShort {
		return apperror.New(apperror.ErrValidation, "application password appears too short")
	}

	return nil
}

// ValidateSiteName validates a site display name
func ValidateSiteName(name string) *apperror.AppError {
	name = strings.TrimSpace(name)
	isNameEmpty := name == ""

	if isNameEmpty {
		return apperror.New(apperror.ErrValidation, "name is required")
	}

	isNameTooLong := len(name) > 100

	if isNameTooLong {
		return apperror.New(apperror.ErrValidation, "name must be 100 characters or less")
	}

	return nil
}

// SanitizeApplicationPassword removes spaces from application passwords
func SanitizeApplicationPassword(password string) string {
	return strings.ReplaceAll(strings.TrimSpace(password), " ", "")
}
