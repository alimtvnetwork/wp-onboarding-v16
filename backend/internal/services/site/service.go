// Package site provides WordPress site management services
package site

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
	"net/url"
	"strings"
	"sync"
	"time"

	"wp-plugin-publish/internal/database"
	connectionstatus "wp-plugin-publish/internal/enums/connection_status"
	connectionstep "wp-plugin-publish/internal/enums/connection_step"
	stagestatus "wp-plugin-publish/internal/enums/stage_status"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/dbutil"
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
	BroadcastConnectionTestProgress(siteId int64, step string, status string, message string, details json.RawMessage)
	BroadcastLog(level string, message string, context json.RawMessage)
	BroadcastRemotePluginLogWithSession(siteId int64, action, sessionId, level, step, message string, details json.RawMessage)
	BroadcastWithSession(eventType string, data any, sessionId string)
}

// SessionService interface for session-based logging
type SessionService interface {
	StartSession(sessionType session.SessionType, pluginId, siteId int64, pluginName, siteName string) (string, error)
	Log(sessionId, level, step, message string, details json.RawMessage)
	LogStageStart(sessionId, stageName string)
	LogStageEnd(sessionId, stageName, status string, durationMs int64)
	EndSession(sessionId, status, errorMsg string)
	SaveRequest(sessionId string, req *session.SessionRequest)
	SaveResponse(sessionId string, resp *session.SessionResponse)
	SaveError(sessionId string, stackTrace *session.SessionStackTrace, errorMsg string, details json.RawMessage)
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
	errorLogHashes   map[string]struct{}
	errorLogHashesMu sync.Mutex
}

// ClearErrorLogHashes resets the in-memory deduplication map.
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
		cacheTTL = 60
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

// ConnectionResult represents the result of a connection test
type ConnectionResult struct {
	IsSuccess       bool
	WPVersion       string `json:",omitempty"`
	PluginsEndpoint bool
	Message         string `json:",omitempty"`
}

// TestConnection verifies the WordPress REST API is accessible
func (s *Service) TestConnection(ctx context.Context, id int64) (*ConnectionResult, error) {
	s.broadcastProgress(id, "start", stagestatus.Running.String(), "Starting connection test...", nil)

	siteResult := s.GetById(ctx, id)
	if siteResult.HasError() {
		s.broadcastProgress(id, "fetch_site", stagestatus.Failed.String(), "Failed to retrieve site info", toJson(ErrorDetail{Error: siteResult.AppError().Error()}))
		return nil, siteResult.AppError()
	}
	site := siteResult.Value()
	s.broadcastProgress(id, "fetch_site", stagestatus.Completed.String(), fmt.Sprintf("Retrieved site: %s", site.Name), nil)

	s.broadcastProgress(id, "decrypt", stagestatus.Running.String(), "Decrypting credentials...", nil)
	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		s.broadcastProgress(id, "decrypt", stagestatus.Failed.String(), "Failed to decrypt credentials", toJson(ErrorDetail{Error: err.Error()}))
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}
	s.broadcastProgress(id, "decrypt", stagestatus.Completed.String(), "Credentials decrypted", nil)

	s.broadcastProgress(id, "connect", stagestatus.Running.String(), fmt.Sprintf("Connecting to %s...", site.Url), nil)
	progressCallback := func(step, status, message string, details wordpress.ProgressDetails) {
		raw, _ := json.Marshal(details)
		s.broadcastProgress(id, step, status, message, raw)
	}
	client := s.wpClientFactory(site.Url, site.Username, string(password), progressCallback)

	result := &ConnectionResult{}
	connInfo, err := client.TestConnection()
	if err != nil {
		result.IsSuccess = false
		result.Message = err.Error()
		s.broadcastProgress(id, "api_test", stagestatus.Failed.String(), fmt.Sprintf("Connection failed: %s", err.Error()), toJson(ConnectionFailureDetails{
			Url: site.Url, Username: site.Username,
		}))
		s.updateConnectionStatus(ctx, id, connectionstatus.Disconnected.DBValue())
		s.broadcastProgress(id, connectionstep.Complete.String(), stagestatus.Failed.String(), "Connection test failed", nil)
		return result, nil
	}

	result.IsSuccess = true
	result.WPVersion = connInfo.WPVersion
	result.PluginsEndpoint = true
	result.Message = "Connection successful"

	s.broadcastProgress(id, "api_test", stagestatus.Completed.String(), fmt.Sprintf("WordPress %s detected, REST API accessible", connInfo.WPVersion), toJson(ConnectionSuccessDetails{WPVersion: connInfo.WPVersion}))
	s.updateConnectionStatus(ctx, id, connectionstatus.Connected.DBValue())
	s.broadcastProgress(id, connectionstep.Complete.String(), stagestatus.Completed.String(), "Connection test completed successfully", nil)
	s.log.Info("Site connection tested", "id", id, "success", result.IsSuccess)

	return result, nil
}

// TestConnectionWithCredentials tests a connection without saving
func (s *Service) TestConnectionWithCredentials(ctx context.Context, siteUrl, username, password string) (*ConnectionResult, error) {
	normalizedUrl := normalizeUrl(siteUrl)
	s.broadcastProgress(0, "start", stagestatus.Running.String(), "Testing connection with provided credentials...", nil)
	s.broadcastProgress(0, "normalize", stagestatus.Completed.String(), fmt.Sprintf("Normalized URL: %s", normalizedUrl), toJson(UrlNormalizeDetails{OriginalUrl: siteUrl, NormalizedUrl: normalizedUrl}))

	progressCallback := func(step, status, message string, details wordpress.ProgressDetails) {
		raw, _ := json.Marshal(details)
		s.broadcastProgress(0, step, status, message, raw)
	}
	client := s.wpClientFactory(normalizedUrl, username, password, progressCallback)

	result := &ConnectionResult{}
	connInfo, err := client.TestConnection()
	if err != nil {
		result.IsSuccess = false
		result.Message = err.Error()
		s.broadcastProgress(0, "api_test", stagestatus.Failed.String(), fmt.Sprintf("Connection failed: %s", err.Error()), toJson(ConnectionFailureDetails{Url: normalizedUrl, Username: username}))
		s.broadcastProgress(0, "complete", stagestatus.Failed.String(), "Connection test failed", nil)
		return result, nil
	}

	result.IsSuccess = true
	result.WPVersion = connInfo.WPVersion
	result.PluginsEndpoint = true
	result.Message = "Connection successful"
	s.broadcastProgress(0, "api_test", stagestatus.Completed.String(), fmt.Sprintf("WordPress %s detected", connInfo.WPVersion), toJson(ConnectionSuccessDetails{WPVersion: connInfo.WPVersion}))
	s.broadcastProgress(0, "complete", stagestatus.Completed.String(), "Connection test completed successfully", nil)

	return result, nil
}

// broadcastProgress sends connection test progress via WebSocket
func (s *Service) broadcastProgress(siteId int64, step, status, message string, details json.RawMessage) {
	if s.wsHub != nil {
		s.wsHub.BroadcastConnectionTestProgress(siteId, step, status, message, details)
	}
	s.log.Debug("Connection test progress", "siteId", siteId, "step", step, "status", status, "message", message)
}

// GetDecryptedPassword returns the decrypted password for a site
func (s *Service) GetDecryptedPassword(ctx context.Context, id int64) (string, error) {
	result := s.GetById(ctx, id)
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

// normalizeUrl normalizes a URL for consistent storage
func normalizeUrl(rawUrl string) string {
	rawUrl = strings.TrimSpace(rawUrl)
	if !strings.HasPrefix(rawUrl, "http://") && !strings.HasPrefix(rawUrl, "https://") {
		rawUrl = "https://" + rawUrl
	}
	parsed, err := url.Parse(rawUrl)
	if err != nil {
		return strings.TrimSuffix(rawUrl, "/")
	}
	pathsToStrip := []string{"/wp-admin/", "/wp-admin", "/wp-login.php", "/wp-json/", "/wp-json"}
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
	parsed.Path = strings.TrimSuffix(path, "/")
	parsed.RawQuery = ""
	parsed.Fragment = ""
	return parsed.String()
}

// SiteCredentials holds decrypted credentials for API access
type SiteCredentials struct {
	Url         string
	Username    string
	AppPassword string
}

// GetCredentials returns the decrypted credentials for a site
func (s *Service) GetCredentials(ctx context.Context, siteId int64) (*SiteCredentials, error) {
	result := s.GetById(ctx, siteId)
	if result.HasError() {
		return nil, result.AppError()
	}
	site := result.Value()
	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}
	s.log.Debug("Credentials retrieved for site", "siteId", siteId, "siteName", site.Name)
	return &SiteCredentials{Url: site.Url, Username: site.Username, AppPassword: string(password)}, nil
}

// derefInt safely dereferences an *int pointer, returning 0 if nil.
func derefInt(p *int) int {
	if p == nil {
		return 0
	}
	return *p
}

// parseTime parses a datetime string in standard format.
func parseTime(s string) time.Time {
	t, _ := time.Parse("2006-01-02 15:04:05", s)
	return t
}
