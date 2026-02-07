// Package site provides WordPress site management services
package site

import (
	"archive/zip"
	"context"
	"crypto/md5"
	"database/sql"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/url"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// Config holds service configuration
type Config struct {
	DB                   *database.DB
	Logger               *logger.Logger
	EncryptionKey        string
	WPClientFactory      WPClientFactory
	WSHub                WSHub           // Optional WebSocket hub for live logging
	SessionService       SessionService  // Optional session service for logging
	CacheEnabled         bool            // Enable remote plugins caching
	CacheTTLMinutes      int             // Cache TTL in minutes (default: 60)
}

// WPClientFactory creates WordPress clients with optional progress callback
type WPClientFactory func(url, user, pass string, onProgress func(step, status, message string, details map[string]interface{})) *wordpress.Client

// WSHub interface for broadcasting messages
type WSHub interface {
	BroadcastConnectionTestProgress(siteID int64, step string, status string, message string, details map[string]interface{})
	BroadcastLog(level string, message string, context map[string]interface{})
	BroadcastRemotePluginLogWithSession(siteID int64, action, sessionID, level, step, message string, details map[string]interface{})
	BroadcastWithSession(eventType string, data interface{}, sessionID string)
}

// SessionService interface for session-based logging
type SessionService interface {
	StartSession(sessionType session.SessionType, pluginID, siteID int64, pluginName, siteName string) (string, error)
	Log(sessionID, level, step, message string, details map[string]interface{})
	LogStageStart(sessionID, stageName string)
	LogStageEnd(sessionID, stageName, status string, durationMs int64)
	EndSession(sessionID, status, errorMsg string)
}

// Service provides site management operations
type Service struct {
	db              *database.DB
	log             *logger.Logger
	encryptionKey   []byte
	wpClientFactory WPClientFactory
	wsHub           WSHub
	sessionService  SessionService
	cacheEnabled    bool
	cacheTTLMinutes int
	// errorLogHashes tracks MD5 hashes of error log entries to prevent duplicate writes.
	// Key: MD5 hex string of (action+siteID+plugin+endpoint+statusCode+responseBody).
	// Identical errors are written only once; subsequent occurrences are silently skipped.
	errorLogHashes   map[string]struct{}
	errorLogHashesMu sync.Mutex
}

// New creates a new site service instance
func New(cfg Config) *Service {
	cacheTTL := cfg.CacheTTLMinutes
	if cacheTTL <= 0 {
		cacheTTL = 60 // default 1 hour
	}
	return &Service{
		db:              cfg.DB,
		log:             cfg.Logger,
		encryptionKey:   []byte(cfg.EncryptionKey),
		wpClientFactory: cfg.WPClientFactory,
		wsHub:           cfg.WSHub,
		sessionService:  cfg.SessionService,
		cacheEnabled:    cfg.CacheEnabled,
		cacheTTLMinutes: cacheTTL,
		errorLogHashes:  make(map[string]struct{}),
	}
}

// List returns all registered sites
func (s *Service) List(ctx context.Context) ([]models.Site, error) {
	query := `
		SELECT Id, Name, Url, Username, PasswordEncrypted, Category, ConnectionStatus, 
		       LastTestedAt, LastSyncAt, CreatedAt, UpdatedAt
		FROM Sites
		ORDER BY Name ASC
	`

	rows, err := s.db.QueryContext(ctx, query)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list sites")
	}
	defer rows.Close()

	var sites []models.Site
	for rows.Next() {
		site, err := s.scanSite(rows)
		if err != nil {
			return nil, err
		}
		sites = append(sites, *site)
	}

	if err := rows.Err(); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "error iterating sites")
	}

	if sites == nil {
		sites = []models.Site{} // Return empty slice instead of nil
	}

	return sites, nil
}

// GetByID returns a site by its ID
func (s *Service) GetByID(ctx context.Context, id int64) (*models.Site, error) {
	query := `
		SELECT Id, Name, Url, Username, PasswordEncrypted, Category, ConnectionStatus, 
		       LastTestedAt, LastSyncAt, CreatedAt, UpdatedAt
		FROM Sites
		WHERE Id = ?
	`

	row := s.db.QueryRowContext(ctx, query, id)
	site, err := s.scanSiteRow(row)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, apperror.New(apperror.ErrNotFound, "site not found").
				WithContext("siteId", id)
		}
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get site")
	}

	return site, nil
}

// GetByURL returns a site by its URL
func (s *Service) GetByURL(ctx context.Context, siteURL string) (*models.Site, error) {
	normalizedURL := normalizeURL(siteURL)
	
	query := `
		SELECT Id, Name, Url, Username, PasswordEncrypted, Category, ConnectionStatus, 
		       LastTestedAt, LastSyncAt, CreatedAt, UpdatedAt
		FROM Sites
		WHERE Url = ?
	`

	row := s.db.QueryRowContext(ctx, query, normalizedURL)
	site, err := s.scanSiteRow(row)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, nil // Not found is not an error for this method
		}
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get site by URL")
	}

	return site, nil
}

// CreateInput holds the data needed to create a site
type CreateInput struct {
	Name     string
	URL      string
	Username string
	Password string
}

// Create adds a new WordPress site
func (s *Service) Create(ctx context.Context, input CreateInput) (*models.Site, error) {
	// Validate input
	if err := s.validateInput(input); err != nil {
		return nil, err
	}

	// Normalize URL
	normalizedURL := normalizeURL(input.URL)

	// Check if URL already exists
	existing, err := s.GetByURL(ctx, normalizedURL)
	if err != nil {
		return nil, err
	}
	if existing != nil {
		return nil, apperror.New(apperror.ErrValidation, "site with this URL already exists").
			WithContext("url", normalizedURL)
	}

	// Encrypt password
	encryptedPassword, err := encrypt([]byte(input.Password), s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to encrypt password")
	}

	// Insert into database
	query := `
		INSERT INTO Sites (Name, Url, Username, PasswordEncrypted, ConnectionStatus, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, ?, 'unknown', datetime('now'), datetime('now'))
	`

	result, err := s.db.ExecContext(ctx, query, input.Name, normalizedURL, input.Username, encryptedPassword)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to create site")
	}

	id, err := result.LastInsertId()
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get inserted site ID")
	}

	s.log.Info("Site created", "id", id, "name", input.Name, "url", normalizedURL)

	// Return the created site
	return s.GetByID(ctx, id)
}

// UpdateInput holds the data for updating a site
type UpdateInput struct {
	Name     *string
	URL      *string
	Username *string
	Password *string // Only updated if non-nil
}

// Update modifies an existing site
func (s *Service) Update(ctx context.Context, id int64, input UpdateInput) (*models.Site, error) {
	// Get existing site
	existing, err := s.GetByID(ctx, id)
	if err != nil {
		return nil, err
	}

	// Build update query dynamically
	var updates []string
	var args []interface{}

	if input.Name != nil && *input.Name != "" {
		updates = append(updates, "Name = ?")
		args = append(args, *input.Name)
	}

	if input.URL != nil && *input.URL != "" {
		normalizedURL := normalizeURL(*input.URL)
		
		// Check if new URL conflicts with another site
		if normalizedURL != existing.URL {
			other, err := s.GetByURL(ctx, normalizedURL)
			if err != nil {
				return nil, err
			}
			if other != nil && other.ID != id {
				return nil, apperror.New(apperror.ErrValidation, "another site with this URL already exists")
			}
		}
		
		updates = append(updates, "Url = ?")
		args = append(args, normalizedURL)
	}

	if input.Username != nil && *input.Username != "" {
		updates = append(updates, "Username = ?")
		args = append(args, *input.Username)
	}

	if input.Password != nil && *input.Password != "" {
		encryptedPassword, err := encrypt([]byte(*input.Password), s.encryptionKey)
		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to encrypt password")
		}
		updates = append(updates, "PasswordEncrypted = ?")
		args = append(args, encryptedPassword)
		
		// Reset connection status when password changes
		updates = append(updates, "ConnectionStatus = 'unknown'")
	}

	if len(updates) == 0 {
		return existing, nil // Nothing to update
	}

	updates = append(updates, "UpdatedAt = datetime('now')")
	args = append(args, id)

	query := fmt.Sprintf("UPDATE Sites SET %s WHERE Id = ?", strings.Join(updates, ", "))

	_, err = s.db.ExecContext(ctx, query, args...)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseUpdate, "failed to update site")
	}

	s.log.Info("Site updated", "id", id)

	return s.GetByID(ctx, id)
}

// Delete removes a site and its mappings (cascaded by FK)
func (s *Service) Delete(ctx context.Context, id int64) error {
	// Verify site exists
	_, err := s.GetByID(ctx, id)
	if err != nil {
		return err
	}

	query := "DELETE FROM Sites WHERE Id = ?"
	result, err := s.db.ExecContext(ctx, query, id)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseDelete, "failed to delete site")
	}

	rowsAffected, _ := result.RowsAffected()
	if rowsAffected == 0 {
		return apperror.New(apperror.ErrNotFound, "site not found")
	}

	s.log.Info("Site deleted", "id", id)

	return nil
}

// TestConnection verifies the WordPress REST API is accessible
func (s *Service) TestConnection(ctx context.Context, id int64) (*ConnectionResult, error) {
	// Broadcast start
	s.broadcastProgress(id, "start", "running", "Starting connection test...", nil)

	site, err := s.GetByID(ctx, id)
	if err != nil {
		s.broadcastProgress(id, "fetch_site", "error", "Failed to retrieve site info", map[string]interface{}{"error": err.Error()})
		return nil, err
	}
	s.broadcastProgress(id, "fetch_site", "success", fmt.Sprintf("Retrieved site: %s", site.Name), nil)

	// Decrypt password
	s.broadcastProgress(id, "decrypt", "running", "Decrypting credentials...", nil)
	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		s.broadcastProgress(id, "decrypt", "error", "Failed to decrypt credentials", map[string]interface{}{"error": err.Error()})
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}
	s.broadcastProgress(id, "decrypt", "success", "Credentials decrypted", nil)

	// Create WordPress client with progress callback
	s.broadcastProgress(id, "connect", "running", fmt.Sprintf("Connecting to %s...", site.URL), nil)
	progressCallback := func(step, status, message string, details map[string]interface{}) {
		s.broadcastProgress(id, step, status, message, details)
	}
	client := s.wpClientFactory(site.URL, site.Username, string(password), progressCallback)

	// Test connection
	result := &ConnectionResult{}
	connInfo, err := client.TestConnection()
	if err != nil {
		result.Success = false
		result.Message = err.Error()
		s.broadcastProgress(id, "api_test", "error", fmt.Sprintf("Connection failed: %s", err.Error()), map[string]interface{}{
			"url":      site.URL,
			"username": site.Username,
		})
		
		// Update connection status
		s.updateConnectionStatus(ctx, id, "disconnected")
		s.broadcastProgress(id, "complete", "error", "Connection test failed", nil)
		
		return result, nil
	}

	result.Success = true
	result.WPVersion = connInfo.WPVersion
	result.PluginsEndpoint = true
	result.Message = "Connection successful"

	s.broadcastProgress(id, "api_test", "success", fmt.Sprintf("WordPress %s detected, REST API accessible", connInfo.WPVersion), map[string]interface{}{
		"wpVersion": connInfo.WPVersion,
	})

	// Update connection status and last tested time
	s.updateConnectionStatus(ctx, id, "connected")
	s.broadcastProgress(id, "complete", "success", "Connection test completed successfully", nil)

	s.log.Info("Site connection tested", "id", id, "success", result.Success)

	return result, nil
}

// TestConnectionWithCredentials tests a connection without saving (for pre-create validation)
func (s *Service) TestConnectionWithCredentials(ctx context.Context, siteURL, username, password string) (*ConnectionResult, error) {
	normalizedURL := normalizeURL(siteURL)
	
	// Broadcast progress (use 0 as siteId for pre-create tests)
	s.broadcastProgress(0, "start", "running", "Testing connection with provided credentials...", nil)
	s.broadcastProgress(0, "normalize", "success", fmt.Sprintf("Normalized URL: %s", normalizedURL), map[string]interface{}{
		"originalUrl":   siteURL,
		"normalizedUrl": normalizedURL,
	})
	
	// Create WordPress client with progress callback
	progressCallback := func(step, status, message string, details map[string]interface{}) {
		s.broadcastProgress(0, step, status, message, details)
	}
	client := s.wpClientFactory(normalizedURL, username, password, progressCallback)

	result := &ConnectionResult{}
	connInfo, err := client.TestConnection()
	if err != nil {
		result.Success = false
		result.Message = err.Error()
		s.broadcastProgress(0, "api_test", "error", fmt.Sprintf("Connection failed: %s", err.Error()), map[string]interface{}{
			"url":      normalizedURL,
			"username": username,
		})
		s.broadcastProgress(0, "complete", "error", "Connection test failed", nil)
		return result, nil
	}

	result.Success = true
	result.WPVersion = connInfo.WPVersion
	result.PluginsEndpoint = true
	result.Message = "Connection successful"

	s.broadcastProgress(0, "api_test", "success", fmt.Sprintf("WordPress %s detected", connInfo.WPVersion), map[string]interface{}{
		"wpVersion": connInfo.WPVersion,
	})
	s.broadcastProgress(0, "complete", "success", "Connection test completed successfully", nil)

	return result, nil
}

// broadcastProgress sends connection test progress via WebSocket
func (s *Service) broadcastProgress(siteID int64, step, status, message string, details map[string]interface{}) {
	if s.wsHub != nil {
		s.wsHub.BroadcastConnectionTestProgress(siteID, step, status, message, details)
	}
	// Also log
	s.log.Debug("Connection test progress", "siteId", siteID, "step", step, "status", status, "message", message)
}

// GetDecryptedPassword returns the decrypted password for a site
func (s *Service) GetDecryptedPassword(ctx context.Context, id int64) (string, error) {
	site, err := s.GetByID(ctx, id)
	if err != nil {
		return "", err
	}

	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	return string(password), nil
}

// updateConnectionStatus updates the connection status and last tested time
func (s *Service) updateConnectionStatus(ctx context.Context, id int64, status string) {
	query := `
		UPDATE Sites 
		SET ConnectionStatus = ?, LastTestedAt = datetime('now'), UpdatedAt = datetime('now')
		WHERE Id = ?
	`
	_, err := s.db.ExecContext(ctx, query, status, id)
	if err != nil {
		s.log.Error("Failed to update connection status", "id", id, "error", err)
	}
}

// validateInput validates the create input
func (s *Service) validateInput(input CreateInput) error {
	if input.Name == "" {
		return apperror.New(apperror.ErrValidation, "name is required")
	}
	if input.URL == "" {
		return apperror.New(apperror.ErrValidation, "URL is required")
	}
	if input.Username == "" {
		return apperror.New(apperror.ErrValidation, "username is required")
	}
	if input.Password == "" {
		return apperror.New(apperror.ErrValidation, "application password is required")
	}

	// Validate URL format
	if _, err := url.Parse(input.URL); err != nil {
		return apperror.New(apperror.ErrValidation, "invalid URL format")
	}

	return nil
}

// scanSite scans a site from database rows
func (s *Service) scanSite(rows *sql.Rows) (*models.Site, error) {
	var site models.Site
	var category sql.NullString
	var lastTestedAt, lastSyncAt, createdAt, updatedAt sql.NullString

	err := rows.Scan(
		&site.ID,
		&site.Name,
		&site.URL,
		&site.Username,
		&site.PasswordEncrypted,
		&category,
		&site.ConnectionStatus,
		&lastTestedAt,
		&lastSyncAt,
		&createdAt,
		&updatedAt,
	)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to scan site")
	}

	if category.Valid {
		site.Category = category.String
	}
	site.LastTestedAt = parseNullTime(lastTestedAt)
	site.LastSyncAt = parseNullTime(lastSyncAt)
	site.CreatedAt = parseTime(createdAt.String)
	site.UpdatedAt = parseTime(updatedAt.String)

	return &site, nil
}

// scanSiteRow scans a site from a single row
func (s *Service) scanSiteRow(row *sql.Row) (*models.Site, error) {
	var site models.Site
	var category sql.NullString
	var lastTestedAt, lastSyncAt, createdAt, updatedAt sql.NullString

	err := row.Scan(
		&site.ID,
		&site.Name,
		&site.URL,
		&site.Username,
		&site.PasswordEncrypted,
		&category,
		&site.ConnectionStatus,
		&lastTestedAt,
		&lastSyncAt,
		&createdAt,
		&updatedAt,
	)
	if err != nil {
		return nil, err
	}

	if category.Valid {
		site.Category = category.String
	}
	site.LastTestedAt = parseNullTime(lastTestedAt)
	site.LastSyncAt = parseNullTime(lastSyncAt)
	site.CreatedAt = parseTime(createdAt.String)
	site.UpdatedAt = parseTime(updatedAt.String)

	return &site, nil
}

// ConnectionResult represents the result of a connection test
type ConnectionResult struct {
	Success         bool   `json:"success"`
	WPVersion       string `json:"wpVersion,omitempty"`
	PluginsEndpoint bool   `json:"pluginsEndpoint"`
	Message         string `json:"message,omitempty"`
}

// normalizeURL normalizes a URL for consistent storage
// Removes common paths like /wp-admin, /wp-login.php, trailing slashes
func normalizeURL(rawURL string) string {
	rawURL = strings.TrimSpace(rawURL)
	
	// Ensure protocol
	if !strings.HasPrefix(rawURL, "http://") && !strings.HasPrefix(rawURL, "https://") {
		rawURL = "https://" + rawURL
	}
	
	// Parse URL to properly handle paths
	parsed, err := url.Parse(rawURL)
	if err != nil {
		// Fallback to simple cleanup
		rawURL = strings.TrimSuffix(rawURL, "/")
		return rawURL
	}
	
	// Remove common WordPress admin paths
	pathsToStrip := []string{
		"/wp-admin/",
		"/wp-admin",
		"/wp-login.php",
		"/wp-json/",
		"/wp-json",
	}
	
	path := parsed.Path
	for _, p := range pathsToStrip {
		if strings.HasPrefix(path, p) {
			path = strings.TrimPrefix(path, strings.TrimSuffix(p, "/"))
			break
		}
		if strings.HasSuffix(path, p) {
			path = strings.TrimSuffix(path, p)
			break
		}
	}
	
	// Clean up the path
	path = strings.TrimSuffix(path, "/")
	parsed.Path = path
	parsed.RawQuery = "" // Remove query params
	parsed.Fragment = "" // Remove fragments
	
	return parsed.String()
}

// parseNullTime parses a nullable time string
func parseNullTime(ns sql.NullString) *time.Time {
	if !ns.Valid || ns.String == "" {
		return nil
	}
	t := parseTime(ns.String)
	return &t
}

// parseTime parses a time string from SQLite
func parseTime(s string) time.Time {
	if s == "" {
		return time.Time{}
	}
	t, err := time.Parse("2006-01-02 15:04:05", s)
	if err != nil {
		return time.Time{}
	}
	return t
}

// BootstrapUploader deploys the Riseup Asia Uploader plugin to a site
func (s *Service) BootstrapUploader(ctx context.Context, id int64, uploaderPath string) (*BootstrapResult, error) {
	// Get site details
	site, err := s.GetByID(ctx, id)
	if err != nil {
		return nil, err
	}

	// Broadcast start
	if s.wsHub != nil {
		s.wsHub.BroadcastLog("info", "Starting Riseup Asia Uploader deployment", map[string]interface{}{
			"siteId":   id,
			"siteName": site.Name,
			"siteUrl":  site.URL,
		})
	}

	// Decrypt password
	decrypted, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt site password")
	}

	// Create WordPress client with progress callback
	progressCallback := func(step, status, message string, details map[string]interface{}) {
		if s.wsHub != nil {
			s.wsHub.BroadcastLog("info", fmt.Sprintf("[%s] %s", step, message), map[string]interface{}{
				"siteId":   id,
				"siteName": site.Name,
				"step":     step,
				"status":   status,
				"details":  details,
			})
		}
	}
	client := s.wpClientFactory(site.URL, site.Username, string(decrypted), progressCallback)

	// If uploader path not specified, try to determine it
	if uploaderPath == "" {
		// Default to plugins-uploader-helper relative to project
		uploaderPath = "plugins-uploader-helper"
	}

	if s.wsHub != nil {
		s.wsHub.BroadcastLog("info", "Creating plugin ZIP archive", map[string]interface{}{
			"siteId": id,
			"path":   uploaderPath,
		})
	}

	// Create ZIP of the uploader plugin
	zipPath, err := s.createUploaderZip(uploaderPath)
	if err != nil {
		if s.wsHub != nil {
			s.wsHub.BroadcastLog("error", fmt.Sprintf("Failed to create ZIP: %v", err), map[string]interface{}{"siteId": id})
		}
		return nil, apperror.Wrap(err, apperror.ErrFSZip, "failed to create uploader ZIP")
	}
	defer os.Remove(zipPath)

	// Check if Riseup Asia is already available on the site
	available, namespace, _ := client.CheckRiseupAsiaAvailable()

	var uploadResult *wordpress.UploaderUploadResult

	if available && namespace != "" {
		// Uploader is already on the site - use it to update itself
		if s.wsHub != nil {
			s.wsHub.BroadcastLog("info", fmt.Sprintf("Riseup Asia Uploader found (%s), updating...", namespace), map[string]interface{}{"siteId": id})
		}
		uploadResult, err = client.UploadPluginViaUploader(zipPath, "riseup-asia-uploader", true)
	} else {
		// First-time installation - use Onboard plugin or standard upload
		if s.wsHub != nil {
			s.wsHub.BroadcastLog("info", "First-time installation - checking for Onboard plugin", map[string]interface{}{"siteId": id})
		}
		
		// Try Onboard plugin first
		onboardAvailable := s.checkOnboardAvailable(client)
		if onboardAvailable {
			if s.wsHub != nil {
				s.wsHub.BroadcastLog("info", "Using Onboard plugin for installation", map[string]interface{}{"siteId": id})
			}
			uploadResult, err = client.UploadPluginViaOnboard(zipPath, true)
		} else {
		// No helper plugin available - this is a limitation
			if s.wsHub != nil {
				s.wsHub.BroadcastLog("error", "No upload helper plugin found. Please install Riseup Asia Uploader or Plugins Onboard manually first.", map[string]interface{}{"siteId": id})
			}
			return nil, apperror.New(apperror.ErrWPUploadFailed, "No upload helper plugin available on site. Install Riseup Asia Uploader or Plugins Onboard plugin manually first, then retry.")
		}
	}

	if err != nil {
		if s.wsHub != nil {
			s.wsHub.BroadcastLog("error", fmt.Sprintf("Upload failed: %v", err), map[string]interface{}{"siteId": id})
		}
		return nil, apperror.Wrap(err, apperror.ErrWPUploadFailed, "failed to upload uploader plugin")
	}

	if s.wsHub != nil {
		s.wsHub.BroadcastLog("info", "Riseup Asia Uploader deployed successfully", map[string]interface{}{
			"siteId":    id,
			"siteName":  site.Name,
			"activated": uploadResult.Activated,
		})
	}

	s.log.Info("Successfully bootstrapped Riseup Asia Uploader to site", map[string]interface{}{
		"siteId":   id,
		"siteName": site.Name,
		"siteUrl":  site.URL,
		"result":   uploadResult,
	})

	return &BootstrapResult{
		Success:   true,
		SiteId:    id,
		SiteName:  site.Name,
		Message:   "Riseup Asia Uploader deployed successfully",
		Activated: uploadResult.Activated,
	}, nil
}

// checkOnboardAvailable checks if the Onboard plugin is available
func (s *Service) checkOnboardAvailable(client *wordpress.Client) bool {
	resp, err := client.CheckOnboardAvailable()
	return err == nil && resp
}

// BootstrapResult represents the result of bootstrapping the uploader to a site
type BootstrapResult struct {
	Success   bool   `json:"success"`
	SiteId    int64  `json:"siteId"`
	SiteName  string `json:"siteName"`
	Message   string `json:"message"`
	Activated bool   `json:"activated"`
}

// createUploaderZip creates a ZIP file of the uploader plugin
func (s *Service) createUploaderZip(uploaderPath string) (string, error) {
	// Resolve to absolute path first
	absUploaderPath, err := pathutil.ToAbsolute(uploaderPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSRead, "failed to resolve uploader path").
			WithContext("path", uploaderPath)
	}

	// Ensure path exists
	info, err := os.Stat(absUploaderPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSNotFound, "uploader path not found").
			WithContext("path", pathutil.ForDisplay(absUploaderPath))
	}
	if !info.IsDir() {
		return "", apperror.New(apperror.ErrFSInvalid, "uploader path is not a directory").
			WithContext("path", pathutil.ForDisplay(absUploaderPath))
	}

	// Create temp file for ZIP
	tempFile, err := os.CreateTemp("", "riseup-asia-uploader-*.zip")
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create temp file for uploader ZIP")
	}
	tempPath := tempFile.Name()

	// Create ZIP writer
	zipWriter := zip.NewWriter(tempFile)

	// Walk directory and add files
	baseName := filepath.Base(absUploaderPath)
	err = filepath.Walk(absUploaderPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return err
		}

		// Get relative path
		relPath, _ := filepath.Rel(absUploaderPath, path)
		if relPath == "." {
			return nil
		}

		// Skip files based on patterns
		if shouldSkipFile(relPath) {
			if info.IsDir() {
				return filepath.SkipDir
			}
			return nil
		}

		if info.IsDir() {
			return nil
		}

		// Create zip entry
		zipPath := baseName + "/" + filepath.ToSlash(relPath)
		writer, err := zipWriter.Create(zipPath)
		if err != nil {
			return err
		}

		// Copy file contents
		file, err := os.Open(path)
		if err != nil {
			return err
		}
		defer file.Close()

		_, err = io.Copy(writer, file)
		return err
	})

	// Close ZIP writer before closing file
	zipWriter.Close()
	tempFile.Close()

	if err != nil {
		os.Remove(tempPath)
		return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to create uploader ZIP").
			WithContext("path", pathutil.ForDisplay(absUploaderPath))
	}

	return tempPath, nil
}

// shouldSkipFile checks if a file should be skipped when creating the uploader ZIP
func shouldSkipFile(relPath string) bool {
	// Normalize path
	relPath = filepath.ToSlash(relPath)
	
	// Skip hidden files and directories (except .uploadignore itself which we include)
	parts := strings.Split(relPath, "/")
	for _, part := range parts {
		if strings.HasPrefix(part, ".") && part != ".uploadignore" {
			return true
		}
	}
	
	// Skip common development files/directories
	skipPatterns := []string{
		"node_modules",
		"vendor",
		"tests",
		"phpunit.xml",
		"phpunit.xml.dist",
		"composer.lock",
	}
	for _, pattern := range skipPatterns {
		if relPath == pattern || strings.HasPrefix(relPath, pattern+"/") {
			return true
		}
	}
	return false
}

// RemotePlugin represents a plugin installed on a remote WordPress site
type RemotePlugin struct {
	Plugin      string `json:"plugin"`
	Slug        string `json:"slug"`
	Name        string `json:"name"`
	Version     string `json:"version"`
	Status      string `json:"status"`
	Author      string `json:"author"`
	Description string `json:"description"`
	PluginURI   string `json:"pluginUri"`
	TextDomain  string `json:"textDomain"`
}

// RemotePluginsResult wraps remote plugins with cache metadata
type RemotePluginsResult struct {
	Plugins  []RemotePlugin `json:"plugins"`
	FromCache bool           `json:"fromCache"`
	CachedAt  *time.Time     `json:"cachedAt,omitempty"`
	ExpiresAt *time.Time     `json:"expiresAt,omitempty"`
}

// GetRemotePlugins fetches all plugins installed on a remote WordPress site (with caching)
func (s *Service) GetRemotePlugins(ctx context.Context, siteID int64) ([]RemotePlugin, error) {
	return s.GetRemotePluginsWithCache(ctx, siteID, false)
}

// GetRemotePluginsWithCache fetches remote plugins with optional cache bypass
func (s *Service) GetRemotePluginsWithCache(ctx context.Context, siteID int64, forceRefresh bool) ([]RemotePlugin, error) {
	// Try cache first (if enabled and not forcing refresh)
	if s.cacheEnabled && !forceRefresh {
		cached, err := s.getRemotePluginsFromCache(ctx, siteID)
		if err == nil && cached != nil {
			s.log.Debug("Remote plugins loaded from cache", "siteId", siteID, "count", len(cached))
			return cached, nil
		}
	}

	// Fetch from remote
	plugins, err := s.fetchRemotePlugins(ctx, siteID)
	if err != nil {
		return nil, err
	}

	// Cache the result (if enabled)
	if s.cacheEnabled {
		if err := s.cacheRemotePlugins(ctx, siteID, plugins); err != nil {
			s.log.Warn("Failed to cache remote plugins", "siteId", siteID, "error", err)
		}
	}

	return plugins, nil
}

// fetchRemotePlugins fetches plugins directly from the remote WordPress site.
// Prefers the Riseup Asia Uploader API for reliability; falls back to WP Core API.
func (s *Service) fetchRemotePlugins(ctx context.Context, siteID int64) ([]RemotePlugin, error) {
	site, err := s.GetByID(ctx, siteID)
	if err != nil {
		return nil, err
	}

	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	client := s.wpClientFactory(site.URL, site.Username, string(password), nil)

	// Try Riseup Asia Uploader API first (more reliable, avoids WP Core 500 errors)
	uploaderPlugins, uploaderErr := client.ListPluginsViaUploader()
	if uploaderErr == nil {
		result := make([]RemotePlugin, 0, len(uploaderPlugins))
		for _, p := range uploaderPlugins {
			slug := p.Slug
			if slug == "" {
				// Derive slug from file path (e.g., "akismet/akismet.php" -> "akismet")
				slug = p.File
				if idx := strings.Index(p.File, "/"); idx > 0 {
					slug = p.File[:idx]
				}
			}
			status := "inactive"
			if p.Active {
				status = "active"
			}
			result = append(result, RemotePlugin{
				Plugin:      p.File,
				Slug:        slug,
				Name:        p.Name,
				Version:     p.Version,
				Status:      status,
				Author:      p.Author,
				Description: p.Description,
			})
		}
		s.log.Debug("Remote plugins fetched via Uploader API", "siteId", siteID, "count", len(result))
		return result, nil
	}

	// No WP Core fallback — Riseup Asia Uploader is required for reliable plugin management
	s.log.Warn("Riseup Asia Uploader API unavailable on remote site", "siteId", siteID, "siteUrl", site.URL, "error", uploaderErr)
	return nil, apperror.Wrap(uploaderErr, apperror.ErrWPPluginList, "Riseup Asia Uploader is not available on this site. Please deploy or update the companion plugin.")
}

// getRemotePluginsFromCache retrieves cached plugins if not expired
func (s *Service) getRemotePluginsFromCache(ctx context.Context, siteID int64) ([]RemotePlugin, error) {
	query := `
		SELECT PluginsJSON, ExpiresAt 
		FROM RemotePluginsCache 
		WHERE SiteId = ? AND datetime(ExpiresAt) > datetime('now')
	`
	
	var pluginsJSON string
	var expiresAt string
	err := s.db.QueryRowContext(ctx, query, siteID).Scan(&pluginsJSON, &expiresAt)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, nil // No cache or expired
		}
		return nil, err
	}

	var plugins []RemotePlugin
	if err := json.Unmarshal([]byte(pluginsJSON), &plugins); err != nil {
		return nil, err
	}

	return plugins, nil
}

// cacheRemotePlugins stores plugins in the cache
func (s *Service) cacheRemotePlugins(ctx context.Context, siteID int64, plugins []RemotePlugin) error {
	pluginsJSON, err := json.Marshal(plugins)
	if err != nil {
		return err
	}

	expiresAt := time.Now().Add(time.Duration(s.cacheTTLMinutes) * time.Minute)
	
	// Use INSERT OR REPLACE to handle both insert and update
	query := `
		INSERT OR REPLACE INTO RemotePluginsCache (SiteId, PluginsJSON, CachedAt, ExpiresAt)
		VALUES (?, ?, datetime('now'), ?)
	`
	
	_, err = s.db.ExecContext(ctx, query, siteID, string(pluginsJSON), expiresAt.Format("2006-01-02 15:04:05"))
	return err
}

// ForceSyncRemotePlugins clears cache and fetches fresh data
func (s *Service) ForceSyncRemotePlugins(ctx context.Context, siteID int64) ([]RemotePlugin, error) {
	// Invalidate cache first
	if err := s.InvalidateRemotePluginsCache(ctx, siteID); err != nil {
		s.log.Warn("Failed to invalidate cache before force sync", "siteId", siteID, "error", err)
	}

	// Fetch fresh data
	return s.GetRemotePluginsWithCache(ctx, siteID, true)
}

// InvalidateRemotePluginsCache removes cached plugins for a site
func (s *Service) InvalidateRemotePluginsCache(ctx context.Context, siteID int64) error {
	query := `DELETE FROM RemotePluginsCache WHERE SiteId = ?`
	_, err := s.db.ExecContext(ctx, query, siteID)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseDelete, "failed to invalidate remote plugins cache")
	}
	s.log.Debug("Remote plugins cache invalidated", "siteId", siteID)
	return nil
}

// GetRemotePluginsCacheStatus returns cache status for a site
func (s *Service) GetRemotePluginsCacheStatus(ctx context.Context, siteID int64) (bool, *time.Time, *time.Time, error) {
	query := `
		SELECT CachedAt, ExpiresAt 
		FROM RemotePluginsCache 
		WHERE SiteId = ?
	`
	
	var cachedAtStr, expiresAtStr string
	err := s.db.QueryRowContext(ctx, query, siteID).Scan(&cachedAtStr, &expiresAtStr)
	if err != nil {
		if err == sql.ErrNoRows {
			return false, nil, nil, nil // No cache
		}
		return false, nil, nil, err
	}

	cachedAtVal := parseTime(cachedAtStr)
	expiresAtVal := parseTime(expiresAtStr)
	
	// Check if we got valid parsed times
	isValid := !expiresAtVal.IsZero() && expiresAtVal.After(time.Now())
	
	// Return pointers (nil if zero time)
	var cachedAtPtr, expiresAtPtr *time.Time
	if !cachedAtVal.IsZero() {
		cachedAtPtr = &cachedAtVal
	}
	if !expiresAtVal.IsZero() {
		expiresAtPtr = &expiresAtVal
	}

	return isValid, cachedAtPtr, expiresAtPtr, nil
}

// EnableRemotePlugin activates a plugin on a remote WordPress site
// Uses the Riseup Asia Uploader API for reliable plugin lifecycle management.
func (s *Service) EnableRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error {
	return s.executeRemotePluginAction(ctx, siteID, pluginSlug, "enable", func(client *wordpress.Client) error {
		return client.EnablePluginViaUploader(pluginSlug)
	})
}

// DisableRemotePlugin deactivates a plugin on a remote WordPress site
// Uses the Riseup Asia Uploader API for reliable plugin lifecycle management.
func (s *Service) DisableRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error {
	return s.executeRemotePluginAction(ctx, siteID, pluginSlug, "disable", func(client *wordpress.Client) error {
		return client.DisablePluginViaUploader(pluginSlug)
	})
}

// DeleteRemotePlugin removes a plugin from a remote WordPress site
// Uses the Riseup Asia Uploader API for reliable plugin lifecycle management.
func (s *Service) DeleteRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error {
	return s.executeRemotePluginAction(ctx, siteID, pluginSlug, "delete", func(client *wordpress.Client) error {
		// First deactivate, then delete via Riseup Asia Uploader
		_ = client.DisablePluginViaUploader(pluginSlug)
		return client.DeletePluginViaUploader(pluginSlug)
	})
}

// executeRemotePluginAction runs a remote plugin action with session logging and stack trace capture
func (s *Service) executeRemotePluginAction(ctx context.Context, siteID int64, pluginSlug, action string, execFn func(*wordpress.Client) error) error {
	startTime := time.Now()

	// Get site info
	site, err := s.GetByID(ctx, siteID)
	if err != nil {
		return err
	}

	// Start session if service available
	var sessionID string
	if s.sessionService != nil {
		var sessionType session.SessionType
		switch action {
		case "enable":
			sessionType = session.SessionTypeRemotePluginEnable
		case "disable":
			sessionType = session.SessionTypeRemotePluginDisable
		case "delete":
			sessionType = session.SessionTypeRemotePluginDelete
		default:
			sessionType = session.SessionType("remote_plugin_action")
		}
		sessionID, _ = s.sessionService.StartSession(sessionType, 0, siteID, pluginSlug, site.Name)
	}

	// Log start
	s.logRemoteAction(sessionID, siteID, action, "info", "start", fmt.Sprintf("Starting %s action for plugin: %s", action, pluginSlug), map[string]interface{}{
		"siteId":     siteID,
		"siteName":   site.Name,
		"siteUrl":    site.URL,
		"pluginSlug": pluginSlug,
	})

	// Broadcast start event
	if s.wsHub != nil {
		s.wsHub.BroadcastWithSession("remote_plugin_action_started", map[string]interface{}{
			"siteId":     siteID,
			"siteName":   site.Name,
			"action":     action,
			"pluginSlug": pluginSlug,
		}, sessionID)
	}

	// Step 1: Decrypt credentials
	s.logRemoteAction(sessionID, siteID, action, "info", "decrypt", "Decrypting site credentials...", nil)
	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		errMsg := "failed to decrypt password"
		s.logRemoteAction(sessionID, siteID, action, "error", "decrypt", errMsg, map[string]interface{}{"error": err.Error()})
		s.endRemoteSession(sessionID, "error", errMsg)
		return apperror.Wrap(err, apperror.ErrInternal, errMsg)
	}

	// Step 2: Create WordPress client
	s.logRemoteAction(sessionID, siteID, action, "info", "connect", fmt.Sprintf("Connecting to WordPress site: %s", site.URL), nil)
	client := s.wpClientFactory(site.URL, site.Username, string(password), nil)

	// Step 3: Execute the action
	if s.sessionService != nil && sessionID != "" {
		s.sessionService.LogStageStart(sessionID, action)
	}
	s.logRemoteAction(sessionID, siteID, action, "info", action, fmt.Sprintf("Executing %s action on plugin: %s", action, pluginSlug), map[string]interface{}{
		"targetUrl":  site.URL,
		"pluginSlug": pluginSlug,
	})

	err = execFn(client)
	durationMs := time.Since(startTime).Milliseconds()

	if err != nil {
		// Capture PHP stack trace from API error if available
		errDetails := s.extractErrorDetails(err)
		s.logRemoteAction(sessionID, siteID, action, "error", action, fmt.Sprintf("Failed to %s plugin: %s", action, pluginSlug), errDetails)

		if s.sessionService != nil && sessionID != "" {
			s.sessionService.LogStageEnd(sessionID, action, "error", durationMs)
		}

		// Write to error.log.txt
		s.logToErrorFile(action, siteID, pluginSlug, site.Name, site.URL, errDetails)

		s.endRemoteSession(sessionID, "error", err.Error())

		// Broadcast failure
		if s.wsHub != nil {
			s.wsHub.BroadcastWithSession("remote_plugin_action_complete", map[string]interface{}{
				"siteId":     siteID,
				"action":     action,
				"pluginSlug": pluginSlug,
				"success":    false,
				"error":      err.Error(),
				"errorDetails": errDetails,
				"durationMs": durationMs,
			}, sessionID)
		}

		errCode := apperror.ErrWPPluginActivate
		if action == "delete" {
			errCode = apperror.ErrWPPluginDelete
		}
		return apperror.Wrap(err, errCode, fmt.Sprintf("failed to %s plugin", action)).
			WithContext("siteId", siteID).
			WithContext("plugin", pluginSlug)
	}

	// Success
	if s.sessionService != nil && sessionID != "" {
		s.sessionService.LogStageEnd(sessionID, action, "success", durationMs)
	}
	s.logRemoteAction(sessionID, siteID, action, "info", action, fmt.Sprintf("Successfully %sd plugin: %s", action, pluginSlug), map[string]interface{}{
		"durationMs": durationMs,
	})

	// Invalidate cache since plugin status changed
	_ = s.InvalidateRemotePluginsCache(ctx, siteID)

	s.endRemoteSession(sessionID, "success", "")

	// Broadcast success
	if s.wsHub != nil {
		s.wsHub.BroadcastWithSession("remote_plugin_action_complete", map[string]interface{}{
			"siteId":     siteID,
			"siteName":   site.Name,
			"action":     action,
			"pluginSlug": pluginSlug,
			"success":    true,
			"durationMs": durationMs,
		}, sessionID)
	}

	s.log.Info(fmt.Sprintf("Remote plugin %sd", action), "siteId", siteID, "plugin", pluginSlug)
	return nil
}

// extractErrorDetails extracts PHP stack trace frames and other details from WordPress API errors
func (s *Service) extractErrorDetails(err error) map[string]interface{} {
	details := map[string]interface{}{
		"error": err.Error(),
	}

	// Try to extract API error details
	if apiErr, ok := err.(*wordpress.APIError); ok {
		details["method"] = apiErr.Method
		details["endpoint"] = apiErr.Endpoint
		details["url"] = apiErr.URL
		details["statusCode"] = apiErr.StatusCode
		details["responseBody"] = apiErr.ResponseBody
		if apiErr.StackTrace != "" {
			details["stackTrace"] = apiErr.StackTrace
		}
		if apiErr.PluginSlugIn != "" {
			details["pluginSlugIn"] = apiErr.PluginSlugIn
		}
		if apiErr.PluginIDUsed != "" {
			details["pluginIdUsed"] = apiErr.PluginIDUsed
		}

		// Try to parse stackTraceFrames from response body if present
		var respData map[string]interface{}
		if err := json.Unmarshal([]byte(apiErr.ResponseBody), &respData); err == nil {
			if errObj, ok := respData["error"].(map[string]interface{}); ok {
				if detailsObj, ok := errObj["details"].(map[string]interface{}); ok {
					if frames, ok := detailsObj["stackTraceFrames"].([]interface{}); ok {
						details["stackTraceFrames"] = frames
					}
					if file, ok := detailsObj["fileFull"].(string); ok {
						details["errorFile"] = file
					}
					if line, ok := detailsObj["line"].(float64); ok {
						details["errorLine"] = int(line)
					}
				}
				if code, ok := errObj["code"].(string); ok {
					details["errorCode"] = code
				}
				if msg, ok := errObj["message"].(string); ok {
					details["errorMessage"] = msg
				}
			}
		}
	}

	return details
}

// logRemoteAction logs a remote plugin action to session and WebSocket.
// It extracts human-readable names from the details map for structured logging.
func (s *Service) logRemoteAction(sessionID string, siteID int64, action, level, step, message string, details map[string]interface{}) {
	// Log to session file
	if s.sessionService != nil && sessionID != "" {
		s.sessionService.Log(sessionID, level, step, message, details)
	}

	// Broadcast via WebSocket
	if s.wsHub != nil {
		s.wsHub.BroadcastRemotePluginLogWithSession(siteID, action, sessionID, level, step, message, details)
	}

	// Resolve names from details or DB for structured logging
	siteName := ""
	siteURL := ""
	pluginSlug := ""
	if details != nil {
		if v, ok := details["siteName"].(string); ok {
			siteName = v
		}
		if v, ok := details["siteUrl"].(string); ok {
			siteURL = v
		}
		if v, ok := details["pluginSlug"].(string); ok {
			pluginSlug = v
		}
	}
	// DB fallback for site info
	if (siteName == "" || siteURL == "") && siteID > 0 {
		if site, err := s.GetByID(context.Background(), siteID); err == nil {
			if siteName == "" {
				siteName = site.Name
			}
			if siteURL == "" {
				siteURL = site.URL
			}
		}
	}
	if siteName == "" {
		siteName = fmt.Sprintf("site#%d", siteID)
	}

	// Log with names first, IDs second, then technical details
	logFields := []interface{}{
		"site", siteName,
	}
	if siteURL != "" {
		logFields = append(logFields, "siteUrl", siteURL)
	}
	logFields = append(logFields, "siteId", siteID, "action", action, "step", step)
	if pluginSlug != "" {
		logFields = append(logFields, "pluginSlug", pluginSlug)
	}

	if level == "error" {
		s.log.Error(message, logFields...)
	} else {
		s.log.Debug(message, logFields...)
	}
}

// endRemoteSession ends the session if service is available
func (s *Service) endRemoteSession(sessionID, status, errorMsg string) {
	if s.sessionService != nil && sessionID != "" {
		s.sessionService.EndSession(sessionID, status, errorMsg)
	}
}

// logToErrorFile writes error details to data/errors/error.log.txt
// Includes full request attribution: Go backend route and WordPress delegated URL.
func (s *Service) logToErrorFile(action string, siteID int64, pluginSlug, siteName, siteURL string, details map[string]interface{}) {
	// Build deduplication hash from stable error identity (excludes timestamp)
	statusCode := 0
	if sc, ok := details["statusCode"].(int); ok {
		statusCode = sc
	}
	endpoint := ""
	if ep, ok := details["endpoint"].(string); ok {
		endpoint = ep
	}
	responseBody := ""
	if rb, ok := details["responseBody"].(string); ok {
		responseBody = rb
	}
	hashInput := fmt.Sprintf("%s|%d|%s|%s|%d|%s", action, siteID, pluginSlug, endpoint, statusCode, responseBody)
	hashBytes := md5.Sum([]byte(hashInput))
	hashHex := hex.EncodeToString(hashBytes[:])

	s.errorLogHashesMu.Lock()
	if _, exists := s.errorLogHashes[hashHex]; exists {
		s.errorLogHashesMu.Unlock()
		s.log.Debug("Duplicate error log entry suppressed", "action", action, "siteId", siteID, "plugin", pluginSlug, "hash", hashHex)
		return
	}
	s.errorLogHashes[hashHex] = struct{}{}
	s.errorLogHashesMu.Unlock()

	errorsDir := pathutil.MustJoin(filepath.Dir(s.db.Path()), "errors")
	errorLogPath := pathutil.MustJoin(errorsDir, "error.log.txt")

	// Create directory if it doesn't exist
	if err := os.MkdirAll(errorsDir, 0755); err != nil {
		s.log.Error("Failed to create errors directory", "error", err)
		return
	}

	// Append to error log
	f, err := os.OpenFile(errorLogPath, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0644)
	if err != nil {
		s.log.Error("Failed to open error log file", "error", err)
		return
	}
	defer f.Close()

	timestamp := time.Now().UTC().Format("2006-01-02 15:04:05")
	logEntry := fmt.Sprintf("\n[%s] REMOTE PLUGIN %s FAILED\n", timestamp, strings.ToUpper(action))
	logEntry += fmt.Sprintf("  Site: %s (ID: %d)\n", siteName, siteID)
	logEntry += fmt.Sprintf("  Plugin: %s\n", pluginSlug)

	// Request attribution: Go backend route
	logEntry += fmt.Sprintf("  RequestedAt: GET /api/v1/sites/%d/remote-plugins/%s\n", siteID, action)

	// Request attribution: WordPress delegated URL
	method := "POST"
	if m, ok := details["method"].(string); ok && m != "" {
		method = m
	}
	fullURL := ""
	if u, ok := details["url"].(string); ok && u != "" {
		fullURL = u
	} else if endpoint != "" {
		fullURL = fmt.Sprintf("%s/wp-json%s", siteURL, endpoint)
	}
	logEntry += fmt.Sprintf("  DelegatedAt: %s %s\n", method, fullURL)

	// Error message
	if errMsg, ok := details["error"].(string); ok {
		logEntry += fmt.Sprintf("  Error: %s\n", errMsg)
	}
	if statusCode > 0 {
		logEntry += fmt.Sprintf("  Status Code: %d\n", statusCode)
	}
	if len(responseBody) > 0 {
		displayBody := responseBody
		if len(displayBody) > 2000 {
			displayBody = displayBody[:2000] + "... (truncated)"
		}
		logEntry += fmt.Sprintf("  Response Body:\n    %s\n", displayBody)
	}
	if frames, ok := details["stackTraceFrames"].([]interface{}); ok && len(frames) > 0 {
		logEntry += "  PHP Stack Trace Frames:\n"
		for i, frame := range frames {
			if frameMap, ok := frame.(map[string]interface{}); ok {
				file := frameMap["file"]
				line := frameMap["line"]
				fn := frameMap["function"]
				class := frameMap["class"]
				if class != nil {
					logEntry += fmt.Sprintf("    #%d %s::%s() at %s:%v\n", i, class, fn, file, line)
				} else {
					logEntry += fmt.Sprintf("    #%d %s() at %s:%v\n", i, fn, file, line)
				}
			}
		}
	}
	logEntry += "───────────────────────────────────────────────────────────────────────────────\n"

	f.WriteString(logEntry)
}

// SiteCredentials holds decrypted credentials for API access
type SiteCredentials struct {
	URL         string `json:"url"`
	Username    string `json:"username"`
	AppPassword string `json:"appPassword"`
}

// GetCredentials returns the decrypted credentials for a site (for API Explorer)
func (s *Service) GetCredentials(ctx context.Context, siteID int64) (*SiteCredentials, error) {
	site, err := s.GetByID(ctx, siteID)
	if err != nil {
		return nil, err
	}

	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	s.log.Debug("Credentials retrieved for site", "siteId", siteID, "siteName", site.Name)
	
	return &SiteCredentials{
		URL:         site.URL,
		Username:    site.Username,
		AppPassword: string(password),
	}, nil
}

// =============================================================================
// REMOTE PLUGIN FILE BROWSER (Phase 10)
// =============================================================================

// RemotePluginFile represents a file in a remote plugin
type RemotePluginFile struct {
	Path       string    `json:"path"`
	Hash       string    `json:"hash"`
	Size       int64     `json:"size"`
	ModifiedAt time.Time `json:"modifiedAt,omitempty"`
}

// RemotePluginFilesResult wraps the file list result
type RemotePluginFilesResult struct {
	PluginSlug string             `json:"pluginSlug"`
	TotalFiles int                `json:"totalFiles"`
	Files      []RemotePluginFile `json:"files"`
}

// GetRemotePluginFiles fetches the file list for a remote plugin via Riseup Asia Uploader
func (s *Service) GetRemotePluginFiles(ctx context.Context, siteID int64, pluginSlug string) (*RemotePluginFilesResult, error) {
	site, err := s.GetByID(ctx, siteID)
	if err != nil {
		return nil, err
	}

	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	client := s.wpClientFactory(site.URL, site.Username, string(password), nil)
	
	// Use the Riseup Asia Uploader endpoint
	files, err := client.GetPluginFilesViaRiseup(ctx, pluginSlug)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to fetch remote plugin files").
			WithContext("siteId", siteID).
			WithContext("pluginSlug", pluginSlug)
	}

	// Convert wordpress.RemoteFile to our local type
	result := &RemotePluginFilesResult{
		PluginSlug: pluginSlug,
		TotalFiles: len(files),
		Files:      make([]RemotePluginFile, 0, len(files)),
	}
	for _, f := range files {
		result.Files = append(result.Files, RemotePluginFile{
			Path:       f.Path,
			Hash:       f.Hash,
			Size:       f.Size,
			ModifiedAt: f.ModifiedAt,
		})
	}

	s.log.Debug("Remote plugin files fetched", "siteId", siteID, "pluginSlug", pluginSlug, "fileCount", len(result.Files))
	return result, nil
}

// GetRemotePluginFileContent fetches the content of a specific file from a remote plugin
func (s *Service) GetRemotePluginFileContent(ctx context.Context, siteID int64, pluginSlug, filePath string) (string, error) {
	site, err := s.GetByID(ctx, siteID)
	if err != nil {
		return "", err
	}

	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	client := s.wpClientFactory(site.URL, site.Username, string(password), nil)
	
	content, err := client.GetPluginFileContent(ctx, pluginSlug, filePath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrWPConnection, "failed to fetch remote file content").
			WithContext("siteId", siteID).
			WithContext("pluginSlug", pluginSlug).
			WithContext("filePath", filePath)
	}

	s.log.Debug("Remote file content fetched", "siteId", siteID, "pluginSlug", pluginSlug, "filePath", filePath, "contentLen", len(content))
	return content, nil
}
