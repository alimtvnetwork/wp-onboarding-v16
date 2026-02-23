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
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"wp-plugin-publish/internal/database"
	action_enum "wp-plugin-publish/internal/enums/action"
	connectionstatus "wp-plugin-publish/internal/enums/connection_status"
	connectionstep "wp-plugin-publish/internal/enums/connection_step"
	loglevel "wp-plugin-publish/internal/enums/log_level"
	stagestatus "wp-plugin-publish/internal/enums/stage_status"
	"wp-plugin-publish/internal/logger"
	
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/dbutil"
	"wp-plugin-publish/pkg/pathutil"
	"wp-plugin-publish/pkg/ziputil"
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
type WPClientFactory func(url, user, pass string, onProgress func(step, status, message string, details wordpress.ProgressDetails)) *wordpress.Client

// WSHub interface for broadcasting messages
type WSHub interface {
	BroadcastConnectionTestProgress(siteID int64, step string, status string, message string, details json.RawMessage)
	BroadcastLog(level string, message string, context json.RawMessage)
	BroadcastRemotePluginLogWithSession(siteID int64, action, sessionID, level, step, message string, details json.RawMessage)
	BroadcastWithSession(eventType string, data any, sessionID string)
}

// SessionService interface for session-based logging
type SessionService interface {
	StartSession(sessionType session.SessionType, pluginID, siteID int64, pluginName, siteName string) (string, error)
	Log(sessionID, level, step, message string, details json.RawMessage)
	LogStageStart(sessionID, stageName string)
	LogStageEnd(sessionID, stageName, status string, durationMs int64)
	EndSession(sessionID, status, errorMsg string)
	SaveRequest(sessionID string, req *session.SessionRequest)
	SaveResponse(sessionID string, resp *session.SessionResponse)
	SaveError(sessionID string, stackTrace *session.SessionStackTrace, errorMsg string, details json.RawMessage)
}

// Service provides site management operations
type Service struct {
	db              *database.DB
	dbu             *dbutil.DB
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

// ClearErrorLogHashes resets the in-memory deduplication map so previously
// suppressed errors will be logged again on next occurrence.
func (s *Service) ClearErrorLogHashes() int {
	s.errorLogHashesMu.Lock()
	count := len(s.errorLogHashes)
	s.errorLogHashes = make(map[string]struct{})
	s.errorLogHashesMu.Unlock()
	s.log.Info("Error log deduplication hashes cleared", "previousCount", count)
	return count
}

// New creates a new site service instance
func New(cfg Config) *Service {
	cacheTTL := cfg.CacheTTLMinutes
	if cacheTTL <= 0 {
		cacheTTL = 60 // default 1 hour
	}
	return &Service{
		db:              cfg.DB,
		dbu:             dbutil.New(cfg.DB.DB),
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

// CRUD operations (List, GetByID, GetByURL, Create, Update, Delete) are in crud.go.

// TestConnection verifies the WordPress REST API is accessible
func (s *Service) TestConnection(ctx context.Context, id int64) (*ConnectionResult, error) {
	// Broadcast start
	s.broadcastProgress(id, "start", stagestatus.Running.String(), "Starting connection test...", nil)

	siteResult := s.GetByID(ctx, id)
	if siteResult.HasError() {
		s.broadcastProgress(id, "fetch_site", stagestatus.Failed.String(), "Failed to retrieve site info", toJSON(ErrorDetail{Error: siteResult.AppError().Error()}))
		return nil, siteResult.AppError()
	}
	site := siteResult.Value()
	s.broadcastProgress(id, "fetch_site", stagestatus.Completed.String(), fmt.Sprintf("Retrieved site: %s", site.Name), nil)

	// Decrypt password
	s.broadcastProgress(id, "decrypt", stagestatus.Running.String(), "Decrypting credentials...", nil)
	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		s.broadcastProgress(id, "decrypt", stagestatus.Failed.String(), "Failed to decrypt credentials", toJSON(ErrorDetail{Error: err.Error()}))
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}
	s.broadcastProgress(id, "decrypt", stagestatus.Completed.String(), "Credentials decrypted", nil)

	// Create WordPress client with progress callback
	s.broadcastProgress(id, "connect", stagestatus.Running.String(), fmt.Sprintf("Connecting to %s...", site.URL), nil)
	progressCallback := func(step, status, message string, details wordpress.ProgressDetails) {
		raw, _ := json.Marshal(details)
		s.broadcastProgress(id, step, status, message, raw) // pass-through from WP client
	}
	client := s.wpClientFactory(site.URL, site.Username, string(password), progressCallback)

	// Test connection
	result := &ConnectionResult{}
	connInfo, err := client.TestConnection()
	if err != nil {
		result.Success = false
		result.Message = err.Error()
		s.broadcastProgress(id, "api_test", stagestatus.Failed.String(), fmt.Sprintf("Connection failed: %s", err.Error()), toJSON(ConnectionFailureDetails{
			URL:      site.URL,
			Username: site.Username,
		}))
		
		// Update connection status
		s.updateConnectionStatus(ctx, id, connectionstatus.Disconnected.DBValue())
		s.broadcastProgress(id, connectionstep.Complete.String(), stagestatus.Failed.String(), "Connection test failed", nil)
		
		return result, nil
	}

	result.Success = true
	result.WPVersion = connInfo.WPVersion
	result.PluginsEndpoint = true
	result.Message = "Connection successful"

	s.broadcastProgress(id, "api_test", stagestatus.Completed.String(), fmt.Sprintf("WordPress %s detected, REST API accessible", connInfo.WPVersion), toJSON(ConnectionSuccessDetails{
		WPVersion: connInfo.WPVersion,
	}))

	// Update connection status and last tested time
	s.updateConnectionStatus(ctx, id, connectionstatus.Connected.DBValue())
	s.broadcastProgress(id, connectionstep.Complete.String(), stagestatus.Completed.String(), "Connection test completed successfully", nil)

	s.log.Info("Site connection tested", "id", id, "success", result.Success)

	return result, nil
}

// TestConnectionWithCredentials tests a connection without saving (for pre-create validation)
func (s *Service) TestConnectionWithCredentials(ctx context.Context, siteURL, username, password string) (*ConnectionResult, error) {
	normalizedURL := normalizeURL(siteURL)
	
	// Broadcast progress (use 0 as siteId for pre-create tests)
	s.broadcastProgress(0, "start", stagestatus.Running.String(), "Testing connection with provided credentials...", nil)
	s.broadcastProgress(0, "normalize", stagestatus.Completed.String(), fmt.Sprintf("Normalized URL: %s", normalizedURL), toJSON(URLNormalizeDetails{
		OriginalURL:   siteURL,
		NormalizedURL: normalizedURL,
	}))
	
	// Create WordPress client with progress callback
	progressCallback := func(step, status, message string, details wordpress.ProgressDetails) {
		raw, _ := json.Marshal(details)
		s.broadcastProgress(0, step, status, message, raw) // pass-through from WP client
	}
	client := s.wpClientFactory(normalizedURL, username, password, progressCallback)

	result := &ConnectionResult{}
	connInfo, err := client.TestConnection()
	if err != nil {
		result.Success = false
		result.Message = err.Error()
		s.broadcastProgress(0, "api_test", stagestatus.Failed.String(), fmt.Sprintf("Connection failed: %s", err.Error()), toJSON(ConnectionFailureDetails{
			URL:      normalizedURL,
			Username: username,
		}))
		s.broadcastProgress(0, "complete", stagestatus.Failed.String(), "Connection test failed", nil)
		return result, nil
	}

	result.Success = true
	result.WPVersion = connInfo.WPVersion
	result.PluginsEndpoint = true
	result.Message = "Connection successful"

	s.broadcastProgress(0, "api_test", stagestatus.Completed.String(), fmt.Sprintf("WordPress %s detected", connInfo.WPVersion), toJSON(ConnectionSuccessDetails{
		WPVersion: connInfo.WPVersion,
	}))
	s.broadcastProgress(0, "complete", stagestatus.Completed.String(), "Connection test completed successfully", nil)

	return result, nil
}

// broadcastProgress sends connection test progress via WebSocket
func (s *Service) broadcastProgress(siteID int64, step, status, message string, details json.RawMessage) {
	if s.wsHub != nil {
		s.wsHub.BroadcastConnectionTestProgress(siteID, step, status, message, details)
	}
	// Also log
	s.log.Debug("Connection test progress", "siteId", siteID, "step", step, "status", status, "message", message)
}

// GetDecryptedPassword returns the decrypted password for a site
func (s *Service) GetDecryptedPassword(ctx context.Context, id int64) (string, error) {
	result := s.GetByID(ctx, id)
	if result.HasError() {
		return "", result.AppError()
	}
	site := result.Value()

	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	return string(password), nil
}

// updateConnectionStatus, validateInput, scanSite, scanSiteRow moved to crud.go.

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

// parseNullTime, parseTime moved to crud.go.

// BootstrapUploader deploys the Riseup Asia Uploader plugin to a site
func (s *Service) BootstrapUploader(ctx context.Context, id int64, uploaderPath string) (*BootstrapResult, error) {
	// Get site details
	result := s.GetByID(ctx, id)
	if result.HasError() {
		return nil, result.AppError()
	}
	site := result.Value()

	// Broadcast start
	if s.wsHub != nil {
		s.wsHub.BroadcastLog(loglevel.Info.Lower(), "Starting Riseup Asia Uploader deployment", toJSON(SiteContextDetails{
			SiteID:   id,
			SiteName: site.Name,
			SiteURL:  site.URL,
		}))
	}

	// Decrypt password
	decrypted, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt site password")
	}

	// Create WordPress client with progress callback
	progressCallback := func(step, status, message string, details wordpress.ProgressDetails) {
		if s.wsHub != nil {
			raw, _ := json.Marshal(details)
			s.wsHub.BroadcastLog(loglevel.Info.Lower(), fmt.Sprintf("[%s] %s", step, message), toJSON(BootstrapLogDetails{
				SiteID:   id,
				SiteName: site.Name,
				Step:     step,
				Status:   status,
				Details:  raw,
			}))
		}
	}
	client := s.wpClientFactory(site.URL, site.Username, string(decrypted), progressCallback)

	// If uploader path not specified, try to determine it
	if uploaderPath == "" {
		// Default to plugins-uploader-helper relative to project
		uploaderPath = "plugins-uploader-helper"
	}

	if s.wsHub != nil {
		s.wsHub.BroadcastLog(loglevel.Info.Lower(), "Creating plugin ZIP archive", toJSON(ZipCreationDetails{
			SiteID: id,
			Path:   uploaderPath,
		}))
	}

	// Create ZIP of the uploader plugin
	zipPath, err := s.createUploaderZip(uploaderPath)
	if err != nil {
		if s.wsHub != nil {
			s.wsHub.BroadcastLog(loglevel.Error.Lower(), fmt.Sprintf("Failed to create ZIP: %v", err), toJSON(SiteIDDetail{SiteID: id}))
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
			s.wsHub.BroadcastLog(loglevel.Info.Lower(), fmt.Sprintf("Riseup Asia Uploader found (%s), updating...", namespace), toJSON(SiteIDDetail{SiteID: id}))
		}
		uploadResult, err = client.UploadPluginViaUploader(zipPath, "riseup-asia-uploader", true, wordpress.UploadSourceRestAPI)
	} else {
		// First-time installation - use Onboard plugin or standard upload
		if s.wsHub != nil {
			s.wsHub.BroadcastLog(loglevel.Info.Lower(), "First-time installation - checking for Onboard plugin", toJSON(SiteIDDetail{SiteID: id}))
		}
		
		// Try Onboard plugin first
		onboardAvailable := s.checkOnboardAvailable(client)
		if onboardAvailable {
			if s.wsHub != nil {
				s.wsHub.BroadcastLog(loglevel.Info.Lower(), "Using Onboard plugin for installation", toJSON(SiteIDDetail{SiteID: id}))
			}
			uploadResult, err = client.UploadPluginViaOnboard(zipPath, true)
		} else {
		// No helper plugin available - this is a limitation
			if s.wsHub != nil {
				s.wsHub.BroadcastLog(loglevel.Error.Lower(), "No upload helper plugin found. Please install Riseup Asia Uploader or Plugins Onboard manually first.", toJSON(SiteIDDetail{SiteID: id}))
			}
			return nil, apperror.New(apperror.ErrWPUploadFailed, "No upload helper plugin available on site. Install Riseup Asia Uploader or Plugins Onboard plugin manually first, then retry.")
		}
	}

	if err != nil {
		if s.wsHub != nil {
			s.wsHub.BroadcastLog(loglevel.Error.Lower(), fmt.Sprintf("Upload failed: %v", err), toJSON(SiteIDDetail{SiteID: id}))
		}
		return nil, apperror.Wrap(err, apperror.ErrWPUploadFailed, "failed to upload uploader plugin")
	}

	if s.wsHub != nil {
		s.wsHub.BroadcastLog(loglevel.Info.Lower(), "Riseup Asia Uploader deployed successfully", toJSON(UploaderDeployDetails{
			SiteID:    id,
			SiteName:  site.Name,
			Activated: uploadResult.Activated,
		}))
	}

	s.log.Info("Successfully bootstrapped Riseup Asia Uploader to site",
		"siteId", id, "siteName", site.Name, "siteUrl", site.URL, "activated", uploadResult.Activated)

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
			WithPath(uploaderPath)
	}

	// Ensure path exists
	info, err := os.Stat(absUploaderPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSNotFound, "uploader path not found").
			WithPath(pathutil.ForDisplay(absUploaderPath))
	}
	if !info.IsDir() {
		return "", apperror.New(apperror.ErrFSInvalid, "uploader path is not a directory").
			WithPath(pathutil.ForDisplay(absUploaderPath))
	}

	// Create temp file for ZIP
	tempFile, err := os.CreateTemp("", "riseup-asia-uploader-*.zip")
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create temp file for uploader ZIP")
	}
	tempPath := tempFile.Name()

	// Create ZIP writer
	zipWriter := zip.NewWriter(tempFile)
	ziputil.RegisterBestCompression(zipWriter)

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
			WithPath(pathutil.ForDisplay(absUploaderPath))
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
	result := s.GetByID(ctx, siteID)
	if result.HasError() {
		return nil, result.AppError()
	}
	site := result.Value()

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
			// Skip plugins with no file identifier — these cannot be managed
			if p.File == "" && p.Slug == "" {
				s.log.Warn("Skipping remote plugin with empty file and slug", "name", p.Name, "siteId", siteID)
				continue
			}

			slug := p.Slug
			if slug == "" {
				// Derive slug from file path (e.g., "akismet/akismet.php" -> "akismet")
				slug = p.File
				if idx := strings.Index(p.File, "/"); idx > 0 {
					slug = p.File[:idx]
				}
			}

			pluginFile := p.File
			if pluginFile == "" {
				// Derive file from slug if missing (e.g., "akismet" -> "akismet/akismet.php")
				pluginFile = slug + "/" + slug + ".php"
				s.log.Warn("Remote plugin missing file path, derived from slug", "slug", slug, "derivedFile", pluginFile, "siteId", siteID)
			}

			status := "inactive"
			if p.Active {
				status = "active"
			}
			result = append(result, RemotePlugin{
				Plugin:      pluginFile,
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

// getRemotePluginsFromCache retrieves cached plugins if not expired.
func (s *Service) getRemotePluginsFromCache(ctx context.Context, siteID int64) ([]RemotePlugin, error) {
	type cacheRow struct {
		PluginsJSON string
		ExpiresAt   string
	}
	result := dbutil.QueryOne[cacheRow](ctx, s.dbu, cacheSelectQuery, func(row *sql.Row) (cacheRow, error) {
		var r cacheRow
		err := row.Scan(&r.PluginsJSON, &r.ExpiresAt)
		return r, err
	}, siteID)

	if result.HasError() {
		return nil, result.AppError()
	}
	if result.IsEmpty() {
		return nil, nil // No cache or expired
	}

	var plugins []RemotePlugin
	if err := json.Unmarshal([]byte(result.Value().PluginsJSON), &plugins); err != nil {
		return nil, err
	}
	return plugins, nil
}

// cacheRemotePlugins stores plugins in the cache.
func (s *Service) cacheRemotePlugins(ctx context.Context, siteID int64, plugins []RemotePlugin) error {
	pluginsJSON, err := json.Marshal(plugins)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to marshal remote plugins for cache")
	}

	expiresAt := time.Now().Add(time.Duration(s.cacheTTLMinutes) * time.Minute)
	res := dbutil.Exec(
		ctx,
		s.dbu,
		cacheUpsertQuery,
		siteID,
		string(pluginsJSON),
		expiresAt.Format("2006-01-02 15:04:05"),
	)
	if res.HasError() {
		return res.AppError()
	}
	return nil
}

// ForceSyncRemotePlugins clears cache and fetches fresh data.
func (s *Service) ForceSyncRemotePlugins(ctx context.Context, siteID int64) ([]RemotePlugin, error) {
	if err := s.InvalidateRemotePluginsCache(ctx, siteID); err != nil {
		s.log.Warn("Failed to invalidate cache before force sync", "siteId", siteID, "error", err)
	}
	return s.GetRemotePluginsWithCache(ctx, siteID, true)
}

// InvalidateRemotePluginsCache removes cached plugins for a site.
func (s *Service) InvalidateRemotePluginsCache(ctx context.Context, siteID int64) error {
	res := dbutil.Exec(
		ctx,
		s.dbu,
		cacheDeleteQuery,
		siteID,
	)
	if res.HasError() {
		return res.AppError()
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

// CheckRemotePluginExists performs a lightweight pre-flight check to verify
// a plugin slug is installed on the remote WordPress site before lifecycle actions.
func (s *Service) CheckRemotePluginExists(ctx context.Context, siteID int64, pluginSlug string) (bool, string, string, error) {
	result := s.GetByID(ctx, siteID)
	if result.HasError() {
		return false, "", "", apperror.Wrap(result.AppError(), apperror.ErrNotFound, "site not found")
	}
	site := result.Value()

	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return false, "", "", apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	client := s.wpClientFactory(site.URL, site.Username, string(password), nil)
	return client.CheckPluginExistsViaUploader(pluginSlug)
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
		// Ignore 404 on disable — plugin may not be installed yet (nothing to deactivate)
		if disableErr := client.DisablePluginViaUploader(pluginSlug); disableErr != nil {
			if apiErr, ok := disableErr.(*wordpress.APIError); ok && apiErr.StatusCode == http.StatusNotFound {
				s.log.Info("Plugin not found during pre-delete disable (skipped safely)", "slug", pluginSlug)
			} else {
				s.log.Warn("Pre-delete disable failed (continuing with delete)", "slug", pluginSlug, "error", disableErr.Error())
			}
		}
		return client.DeletePluginViaUploader(pluginSlug)
	})
}

// executeRemotePluginAction runs a remote plugin action with session logging and stack trace capture
func (s *Service) executeRemotePluginAction(ctx context.Context, siteID int64, pluginSlug, action string, execFn func(*wordpress.Client) error) error {
	startTime := time.Now()

	// Get site info
	siteResult := s.GetByID(ctx, siteID)
	if siteResult.HasError() {
		return siteResult.AppError()
	}
	site := siteResult.Value()

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
	s.logRemoteAction(sessionID, siteID, action, "info", "start", fmt.Sprintf("Starting %s action for plugin: %s", action, pluginSlug), session.ToJSON(RemoteActionContext{
		SiteID:     siteID,
		SiteName:   site.Name,
		SiteURL:    site.URL,
		PluginSlug: pluginSlug,
	}))

	// Save request.json — the inbound request from frontend
	if s.sessionService != nil && sessionID != "" {
		s.sessionService.SaveRequest(sessionID, &session.SessionRequest{
			URL:    fmt.Sprintf("/api/v1/sites/%d/remote-plugins/%s/%s", siteID, pluginSlug, action),
			Method: "POST",
		Body: toJSON(RemoteActionRequestBody{
				SiteID:     siteID,
				PluginSlug: pluginSlug,
				Action:     action,
			}),
		})
	}

	// Broadcast start event
	if s.wsHub != nil {
		s.wsHub.BroadcastWithSession("remote_plugin_action_started", RemoteActionStartedEvent{
			SiteID:     siteID,
			SiteName:   site.Name,
			Action:     action,
			PluginSlug: pluginSlug,
		}, sessionID)
	}

	// Step 1: Decrypt credentials
	s.logRemoteAction(sessionID, siteID, action, "info", "decrypt", "Decrypting site credentials...", nil)
	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		errMsg := "failed to decrypt password"
		s.logRemoteAction(sessionID, siteID, action, "error", "decrypt", errMsg, session.ToJSON(ErrorDetail{Error: err.Error()}))
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
	s.logRemoteAction(sessionID, siteID, action, "info", action, fmt.Sprintf("Executing %s action on plugin: %s", action, pluginSlug), session.ToJSON(RemoteActionExecDetails{
		TargetURL:  site.URL,
		PluginSlug: pluginSlug,
	}))

	err = execFn(client)
	durationMs := time.Since(startTime).Milliseconds()

	if err != nil {
		// Capture PHP stack trace from API error if available
		errDetails := s.extractErrorDetails(err)

		// Save response.json with error data
		if s.sessionService != nil && sessionID != "" {
			// Parse response body for structured storage
			var bodyJSON json.RawMessage
			if errDetails.ResponseBody != "" {
				// Try to store as parsed JSON; fall back to raw string
				if json.Valid([]byte(errDetails.ResponseBody)) {
					bodyJSON = json.RawMessage(errDetails.ResponseBody)
				} else {
					bodyJSON, _ = json.Marshal(errDetails.ResponseBody)
				}
			}
			s.sessionService.SaveResponse(sessionID, &session.SessionResponse{
				RequestURL:  errDetails.URL,
				ResponseURL: errDetails.URL,
				StatusCode:  errDetails.StatusCode,
				Body:        bodyJSON,
			})

			// Build PHP stack frames for error.log
			phpFrames := s.buildPHPStackFrames(errDetails)
			// Capture Go runtime stack trace (skip 2: CaptureGoStack + this closure)
			goFrames := session.CaptureGoStack(2)
			s.sessionService.SaveError(sessionID, &session.SessionStackTrace{
				Golang: goFrames,
				PHP:    phpFrames,
			}, err.Error(), session.ToJSON(errDetails))
		}

		s.logRemoteAction(sessionID, siteID, action, "error", action, fmt.Sprintf("Failed to %s plugin: %s", action, pluginSlug), session.ToJSON(errDetails))

		if s.sessionService != nil && sessionID != "" {
			s.sessionService.LogStageEnd(sessionID, action, "error", durationMs)
		}

		// Pull recent PHP error sessions from the remote site for deeper diagnostics
		s.fetchAndAttachRemotePHPErrors(client, sessionID, siteID, action, pluginSlug, site.Name, site.URL, errDetails)

		// Write to error.log.txt (now enriched with PHP errors)
		s.logToErrorFile(action, siteID, pluginSlug, site.Name, site.URL, errDetails)

		s.endRemoteSession(sessionID, "error", err.Error())

		// Broadcast failure
		if s.wsHub != nil {
		s.wsHub.BroadcastWithSession("remote_plugin_action_complete", RemoteActionCompleteEvent{
				SiteID:       siteID,
				Action:       action,
				PluginSlug:   pluginSlug,
				Success:      false,
				Error:        err.Error(),
				ErrorDetails: errDetails,
				DurationMs:   durationMs,
			}, sessionID)
		}

		errCode := apperror.ErrWPPluginActivate
		if action == action_enum.Delete.String() {
			errCode = apperror.ErrWPPluginDelete
		}
		return apperror.Wrap(err, errCode, fmt.Sprintf("failed to %s plugin", action)).
			WithSiteID(siteID).
			WithPlugin(pluginSlug).
			WithSessionID(sessionID)
	}

	// Success — save response.json
	if s.sessionService != nil && sessionID != "" {
		s.sessionService.SaveResponse(sessionID, &session.SessionResponse{
			RequestURL:  fmt.Sprintf("%s/wp-json/riseup-asia-uploader/v1/plugins/%s", site.URL, action),
			ResponseURL: site.URL,
			StatusCode:  200,
		Body:        toJSON(RemoteActionSuccessBody{Success: true, Action: action, Plugin: pluginSlug}),
		})
		s.sessionService.LogStageEnd(sessionID, action, "success", durationMs)
	}
	s.logRemoteAction(sessionID, siteID, action, "info", action, fmt.Sprintf("Successfully %sd plugin: %s", action, pluginSlug), session.ToJSON(DurationDetail{
		DurationMs: durationMs,
	}))

	// Invalidate cache since plugin status changed
	_ = s.InvalidateRemotePluginsCache(ctx, siteID)

	s.endRemoteSession(sessionID, "success", "")

	// Broadcast success
	if s.wsHub != nil {
		s.wsHub.BroadcastWithSession("remote_plugin_action_complete", RemoteActionCompleteEvent{
			SiteID:     siteID,
			SiteName:   site.Name,
			Action:     action,
			PluginSlug: pluginSlug,
			Success:    true,
			DurationMs: durationMs,
		}, sessionID)
	}

	s.log.Info(fmt.Sprintf("Remote plugin %sd", action), "siteId", siteID, "plugin", pluginSlug)
	return nil
}

// buildPHPStackFrames converts typed PHP stack frames into session StackFrame structs
func (s *Service) buildPHPStackFrames(details *ExtractedErrorDetails) []session.StackFrame {
	frames := make([]session.StackFrame, 0, len(details.StackTraceFrames))
	for _, f := range details.StackTraceFrames {
		frames = append(frames, session.StackFrame{
			Function: f.Function,
			File:     f.File,
			Line:     f.Line,
			Class:    f.Class,
		})
	}
	return frames
}

// extractErrorDetails extracts PHP stack trace frames and other details from WordPress API errors
func (s *Service) extractErrorDetails(err error) *ExtractedErrorDetails {
	details := &ExtractedErrorDetails{
		Error: err.Error(),
	}

	apiErr, ok := err.(*wordpress.APIError)
	if !ok {
		return details
	}

	details.Method = apiErr.Method
	details.Endpoint = apiErr.Endpoint
	details.URL = apiErr.URL
	details.StatusCode = apiErr.StatusCode
	details.RequestBody = apiErr.RequestBody
	details.ResponseBody = apiErr.ResponseBody
	if apiErr.StackTrace != "" {
		details.StackTrace = apiErr.StackTrace
	}
	if apiErr.PluginSlugIn != "" {
		details.PluginSlugIn = apiErr.PluginSlugIn
	}
	if apiErr.PluginIDUsed != "" {
		details.PluginIDUsed = apiErr.PluginIDUsed
	}

	// Try to parse structured data from response body using typed envelope structs
	var envResp errorResponseEnvelope
	if json.Unmarshal([]byte(apiErr.ResponseBody), &envResp) != nil {
		return details
	}

	// Envelope format: Errors.Backend and Errors.DelegatedServiceErrorStack
	if envResp.Errors.BackendMessage != "" {
		details.ErrorMessage = envResp.Errors.BackendMessage
	}
	if len(envResp.Errors.DelegatedServiceErrorStack) > 0 {
		details.DelegatedServiceErrorStack = envResp.Errors.DelegatedServiceErrorStack
	}
	if len(envResp.Errors.Backend) > 0 {
		details.PHPBackendStack = envResp.Errors.Backend
	}

	// Legacy format: error.details.stackTraceFrames
	if envResp.ErrorLegacy.Details.StackTraceFrames != nil {
		parsed := make([]PHPStackFrame, 0, len(envResp.ErrorLegacy.Details.StackTraceFrames))
		for _, fm := range envResp.ErrorLegacy.Details.StackTraceFrames {
			parsed = append(parsed, PHPStackFrame{
				Function: fm.Function,
				File:     fm.File,
				Line:     fm.Line,
				Class:    fm.Class,
			})
		}
		details.StackTraceFrames = parsed
		if envResp.ErrorLegacy.Details.FileFull != "" {
			details.ErrorFile = envResp.ErrorLegacy.Details.FileFull
		}
		details.ErrorLine = envResp.ErrorLegacy.Details.Line
	}

	return details
}

// logRemoteAction logs a remote plugin action to session and WebSocket.
// It extracts human-readable names from the details for structured logging.
func (s *Service) logRemoteAction(sessionID string, siteID int64, action, level, step, message string, details json.RawMessage) {
	// Log to session file
	if s.sessionService != nil && sessionID != "" {
		s.sessionService.Log(sessionID, level, step, message, details)
	}

	// Broadcast via WebSocket (now accepts json.RawMessage directly)
	if s.wsHub != nil {
		s.wsHub.BroadcastRemotePluginLogWithSession(siteID, action, sessionID, level, step, message, details)
	}

	// Parse details for name resolution in structured logging
	var logCtx remoteActionLogContext
	if len(details) > 0 {
		_ = json.Unmarshal(details, &logCtx)
	}

	// Resolve names from details or DB for structured logging
	siteName := logCtx.SiteName
	siteURL := logCtx.SiteURL
	pluginSlug := logCtx.PluginSlug
	// DB fallback for site info
	if (siteName == "" || siteURL == "") && siteID > 0 {
		if siteResult := s.GetByID(context.Background(), siteID); siteResult.IsSafe() {
			site := siteResult.Value()
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

	if level == loglevel.Error.String() {
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

// fetchAndAttachRemotePHPErrors pulls recent PHP error sessions from the remote WordPress
// site and enriches the error details map with them. It also logs them to the session.
// This runs best-effort and never returns errors — failures are silently logged.
func (s *Service) fetchAndAttachRemotePHPErrors(client *wordpress.Client, sessionID string, siteID int64, action, pluginSlug, siteName, siteURL string, errDetails *ExtractedErrorDetails) {
	s.logRemoteAction(sessionID, siteID, action, "info", "fetch_php_errors", "Pulling recent PHP error sessions from remote site...", nil)

	// Fetch error sessions
	result, fetchErr := client.FetchRemoteErrorSessions("error", "", 0, 10, 0)
	if fetchErr != nil {
		s.logRemoteAction(sessionID, siteID, action, "warn", "fetch_php_errors",
			fmt.Sprintf("Could not fetch remote PHP errors: %s", fetchErr.Error()), nil)
	}

	if result != nil && len(result.Entries) > 0 {
		// Attach to error details so they appear in error.log.txt and WebSocket broadcast
		phpErrors := make([]PHPErrorEntry, 0, len(result.Entries))
		for _, entry := range result.Entries {
			phpErr := PHPErrorEntry{
				ID:        entry.ID,
				Level:     entry.Level,
				Message:   entry.Message,
				File:      entry.File,
				Line:      derefInt(entry.Line),
				CreatedAt: entry.CreatedAt,
			}
			if len(entry.StackTraceFrames) > 0 {
				if raw, marshalErr := json.Marshal(entry.StackTraceFrames); marshalErr == nil {
					phpErr.StackTraceFrames = raw
				}
			}
			phpErrors = append(phpErrors, phpErr)
		}
		errDetails.RemotePHPErrors = phpErrors
		errDetails.RemotePHPErrorCount = len(result.Entries)

		if result.Flash.HasUnseen {
			errDetails.RemotePHPFlashUnseen = result.Flash.UnseenCount
		}

		s.logRemoteAction(sessionID, siteID, action, "info", "fetch_php_errors",
			fmt.Sprintf("Retrieved %d recent PHP error(s) from remote site", len(result.Entries)),
			session.ToJSON(PHPErrorCountDetail{PHPErrorCount: len(result.Entries)}))

		// Log each PHP error to session for full traceability
		if s.sessionService != nil && sessionID != "" {
			for _, entry := range result.Entries {
				s.sessionService.Log(sessionID, "error", "remote_php_error", entry.Message, session.ToJSON(PHPErrorDetail{
					PHPFile:    entry.File,
					PHPLine:    derefInt(entry.Line),
					PHPLevel:   entry.Level,
					PHPCreated: entry.CreatedAt,
				}))
			}
		}
	} else if fetchErr == nil {
		s.logRemoteAction(sessionID, siteID, action, "info", "fetch_php_errors", "No recent PHP error sessions found on remote site", nil)
	}

	// Fetch stacktrace.txt via error-logs endpoint for deep PHP diagnostics
	s.logRemoteAction(sessionID, siteID, action, "info", "fetch_php_stacktrace", "Pulling PHP stacktrace.txt from remote site...", nil)

	logsResult, logsErr := client.FetchRemoteErrorLogs()
	if logsErr != nil {
		s.logRemoteAction(sessionID, siteID, action, "warn", "fetch_php_stacktrace",
			fmt.Sprintf("Could not fetch remote error logs: %s", logsErr.Error()), nil)
		return
	}

	if logsResult != nil && logsResult.StackTraceLog != nil && logsResult.StackTraceLog.Exists && logsResult.StackTraceLog.Content != "" {
		errDetails.RemotePHPStackTrace = logsResult.StackTraceLog.Content
		errDetails.RemotePHPStackTraceLines = logsResult.StackTraceLog.Lines

		s.logRemoteAction(sessionID, siteID, action, "info", "fetch_php_stacktrace",
			fmt.Sprintf("Retrieved PHP stacktrace.txt (%d lines, %d bytes)", logsResult.StackTraceLog.Lines, logsResult.StackTraceLog.TotalSize),
			session.ToJSON(StackTraceLogDetails{
				Lines:     logsResult.StackTraceLog.Lines,
				TotalSize: int(logsResult.StackTraceLog.TotalSize),
				Truncated: logsResult.StackTraceLog.Truncated,
			}))

		// Persist stacktrace content to session error.log for diagnostics API
		if s.sessionService != nil && sessionID != "" {
			s.sessionService.Log(sessionID, "info", "remote_php_stacktrace", "PHP stacktrace.txt content from remote site", session.ToJSON(StackTraceContentDetails{
				Content:   logsResult.StackTraceLog.Content,
				Lines:     logsResult.StackTraceLog.Lines,
				Truncated: logsResult.StackTraceLog.Truncated,
			}))
		}
	} else {
		s.logRemoteAction(sessionID, siteID, action, "info", "fetch_php_stacktrace", "No stacktrace.txt content available on remote site", nil)
	}
}

// logToErrorFile writes error details to data/errors/error.log.txt
// Uses MD5 deduplication to suppress identical error entries.
// Format matches the standardized error log spec with full request attribution.
func (s *Service) logToErrorFile(action string, siteID int64, pluginSlug, siteName, siteURL string, details *ExtractedErrorDetails) {
	// Build deduplication hash from stable error identity (excludes timestamp)
	hashInput := fmt.Sprintf("%s|%d|%s|%s|%d|%s", action, siteID, pluginSlug, details.Endpoint, details.StatusCode, details.ResponseBody)
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

	errorsDir, err := pathutil.Join(filepath.Dir(s.db.Path()), "errors")
	if err != nil {
		s.log.Error("Failed to resolve errors directory path", "error", err)
		return
	}
	errorLogPath, err := pathutil.Join(errorsDir, "error.log.txt")
	if err != nil {
		s.log.Error("Failed to resolve error log path", "error", err)
		return
	}

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

	// Build delegated URL (the full WordPress endpoint that was actually called)
	method := details.Method
	if method == "" {
		method = "POST"
	}
	delegatedURL := details.URL
	if delegatedURL == "" && details.Endpoint != "" {
		delegatedURL = fmt.Sprintf("%s/wp-json%s", siteURL, details.Endpoint)
	}

	// Detect if a WP Core mutation endpoint was used (guard rail)
	isWPCoreMutation := false
	blockedEndpoint := ""
	requiredEndpoint := ""
	if strings.Contains(details.Endpoint, "/wp/v2/plugins") && method != "GET" {
		isWPCoreMutation = true
		blockedEndpoint = details.Endpoint
		// Determine the correct Riseup Uploader endpoint
		switch action {
		case "disable":
			requiredEndpoint = fmt.Sprintf("%s/wp-json/%s%s", siteURL, wordpress.RiseupAsiaNamespace, wordpress.EndpointDisable)
		case "enable":
			requiredEndpoint = fmt.Sprintf("%s/wp-json/%s%s", siteURL, wordpress.RiseupAsiaNamespace, wordpress.EndpointEnable)
		case "delete":
			requiredEndpoint = fmt.Sprintf("%s/wp-json/%s%s", siteURL, wordpress.RiseupAsiaNamespace, wordpress.EndpointDelete)
		default:
			requiredEndpoint = fmt.Sprintf("%s/wp-json/%s/plugins/%s", siteURL, wordpress.RiseupAsiaNamespace, action)
		}
	}

	// Plugin identifier from request body (pluginSlugIn from APIError)
	pluginIdentifier := pluginSlug
	if details.PluginSlugIn != "" {
		pluginIdentifier = details.PluginSlugIn
	}

	// Request body - use actual request body from APIError, fall back to reconstruction
	requestBody := details.RequestBody
	if requestBody == "" {
		requestBody = fmt.Sprintf(`{"plugin":"%s"}`, pluginIdentifier)
	}

	// Build log entry in the redefined format
	timestamp := time.Now().UTC().Format("2006-01-02 15:04:05")
	logEntry := fmt.Sprintf("\n[%s] REMOTE PLUGIN %s FAILED\n", timestamp, strings.ToUpper(action))
	logEntry += fmt.Sprintf("  Site Request URL: %s\n", delegatedURL)
	logEntry += fmt.Sprintf("  Site ID: %d\n", siteID)
	logEntry += fmt.Sprintf("  Site Name: %s\n", siteName)
	logEntry += fmt.Sprintf("  Site Base URL: %s\n", siteURL)
	logEntry += fmt.Sprintf("  Plugin Identifier: %s\n", pluginIdentifier)
	logEntry += fmt.Sprintf("  Requested Action: %s\n", action)
	logEntry += "  Delegated Request:\n"
	logEntry += fmt.Sprintf("    Method: %s\n", method)
	logEntry += fmt.Sprintf("    Endpoint: %s\n", details.Endpoint)
	logEntry += "    Request Body:\n"
	logEntry += fmt.Sprintf("      %s\n", requestBody)
	logEntry += "  Delegated Response:\n"
	logEntry += fmt.Sprintf("    Status Code: %d\n", details.StatusCode)

	// Response body
	if len(details.ResponseBody) > 0 {
		displayBody := details.ResponseBody
		if len(displayBody) > 2000 {
			displayBody = displayBody[:2000] + "... (truncated)"
		}
		logEntry += fmt.Sprintf("    Response Body:\n      %s\n", displayBody)
	}

	// Error summary
	logEntry += "  Error Summary:\n"
	logEntry += fmt.Sprintf("    %s\n", details.Error)
	if isWPCoreMutation {
		logEntry += "    WARNING: This request was sent to a WordPress Core endpoint instead of the Riseup Uploader.\n"
	} else {
		logEntry += "    This request was correctly delegated through the Riseup Uploader endpoint.\n"
	}

	// Guard rail section
	if isWPCoreMutation {
		logEntry += "  Guard Rail:\n"
		logEntry += "    Blocked Direct WP Core Mutation: true\n"
		logEntry += fmt.Sprintf("    Blocked Endpoint: %s\n", blockedEndpoint)
		logEntry += fmt.Sprintf("    Required Delegation Endpoint: %s\n", requiredEndpoint)
	}

	// PHP stack trace frames
	if len(details.StackTraceFrames) > 0 {
		logEntry += "  PHP Stack Trace Frames:\n"
		for i, frame := range details.StackTraceFrames {
			if frame.Class != "" {
				logEntry += fmt.Sprintf("    #%d %s::%s() at %s:%d\n", i, frame.Class, frame.Function, frame.File, frame.Line)
			} else {
				logEntry += fmt.Sprintf("    #%d %s() at %s:%d\n", i, frame.Function, frame.File, frame.Line)
			}
		}
	}

	// Remote PHP error sessions (fetched from plugin's SQLite DB)
	if len(details.RemotePHPErrors) > 0 {
		logEntry += fmt.Sprintf("  Remote PHP Error Sessions (%d entries):\n", len(details.RemotePHPErrors))
		for i, phpErr := range details.RemotePHPErrors {
			logEntry += fmt.Sprintf("    [%d] [%s] %s\n", i+1, strings.ToUpper(phpErr.Level), phpErr.Message)
			logEntry += fmt.Sprintf("        File: %s  Line: %d  At: %s\n", phpErr.File, phpErr.Line, phpErr.CreatedAt)
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
	result := s.GetByID(ctx, siteID)
	if result.HasError() {
		return nil, result.AppError()
	}
	site := result.Value()

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
	result := s.GetByID(ctx, siteID)
	if result.HasError() {
		return nil, result.AppError()
	}
	site := result.Value()

	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	client := s.wpClientFactory(site.URL, site.Username, string(password), nil)
	
	// Use the Riseup Asia Uploader endpoint
	files, err := client.GetPluginFilesViaRiseup(ctx, pluginSlug)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to fetch remote plugin files").
			WithSiteID(siteID).
			WithPluginSlug(pluginSlug)
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
	result := s.GetByID(ctx, siteID)
	if result.HasError() {
		return "", result.AppError()
	}
	site := result.Value()

	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	client := s.wpClientFactory(site.URL, site.Username, string(password), nil)
	
	content, err := client.GetPluginFileContent(ctx, pluginSlug, filePath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrWPConnection, "failed to fetch remote file content").
			WithSiteID(siteID).
			WithPluginSlug(pluginSlug).
			WithFilePath(filePath)
	}

	s.log.Debug("Remote file content fetched", "siteId", siteID, "pluginSlug", pluginSlug, "filePath", filePath, "contentLen", len(content))
	return content, nil
}

// derefInt safely dereferences an *int pointer, returning 0 if nil.
func derefInt(p *int) int {
	if p == nil {
		return 0
	}
	return *p
}
