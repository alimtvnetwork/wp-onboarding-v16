// Package site provides WordPress site management services
package site

import (
	"context"
	"encoding/json"
	"fmt"
	"net/url"
	"strings"
	"sync"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/enums/connection_status"
	"wp-plugin-publish/internal/enums/connection_step"
	"wp-plugin-publish/internal/enums/stage_status"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
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
	IsCacheEnabled   bool            // Enable remote plugins caching
	CacheTTLMinutes  int             // Cache TTL in minutes (default: 60)
}

// WPClientFactory creates WordPress clients with optional progress callback
type WPClientFactory func(url, user, pass string, onProgress func(event wordpress.ProgressEvent)) *wordpress.Client

// WSHub interface for broadcasting messages
type WSHub interface {
	BroadcastConnectionTestProgress(data ConnectionProgressInput)
	BroadcastLog(level string, message string, context json.RawMessage)
	BroadcastRemotePluginLogWithSession(input RemotePluginLogInput)
	BroadcastWithSession(eventType string, data any, sessionId string)
}

// ConnectionProgressInput holds connection test progress broadcast parameters.
type ConnectionProgressInput struct {
	SiteID  int64
	Step    string
	Status  string
	Message string
	Details json.RawMessage
}

// RemotePluginLogInput holds remote plugin log broadcast parameters.
type RemotePluginLogInput struct {
	SiteID    int64
	Action    string
	SessionID string
	Level     string
	Step      string
	Message   string
	Details   json.RawMessage
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
	isCacheEnabled  bool
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
		isCacheEnabled:  cfg.IsCacheEnabled,
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
	s.broadcastProgress(ConnectionProgressInput{SiteID: id, Step: "start", Status: stagestatus.Running.String(), Message: "Starting connection test..."})

	site, password, err := s.prepareConnectionTest(ctx, id)
	if err != nil {
		return nil, err
	}

	progressCallback := func(event wordpress.ProgressEvent) {
		s.broadcastProgress(ConnectionProgressInput{
			SiteID: id, Step: event.Step, Status: event.Status, Message: event.Message, Details: event.Details,
		})
	}
	client := s.wpClientFactory(site.Url, site.Username, string(password), progressCallback)

	return s.executeConnectionTest(ctx, id, site, client)
}

// prepareConnectionTest loads the site and decrypts credentials.
func (s *Service) prepareConnectionTest(ctx context.Context, id int64) (*models.Site, []byte, error) {
	siteResult := s.GetById(ctx, id)
	if siteResult.HasError() {
		s.broadcastProgress(ConnectionProgressInput{
			SiteID: id, Step: "fetch_site", Status: stagestatus.Failed.String(),
			Message: "Failed to retrieve site info",
			Details: toJson(ErrorDetail{Error: siteResult.AppError().Error()}),
		})
		return nil, nil, siteResult.AppError()
	}
	site := siteResult.Value()
	s.broadcastProgress(ConnectionProgressInput{
		SiteID: id, Step: "fetch_site", Status: stagestatus.Completed.String(),
		Message: fmt.Sprintf("Retrieved site: %s", site.Name),
	})

	password, err := s.decryptWithProgress(id, site.PasswordEncrypted)
	if err != nil {
		return nil, nil, err
	}

	return &site, password, nil
}

// decryptWithProgress decrypts a password with broadcast progress updates.
func (s *Service) decryptWithProgress(siteId int64, encrypted string) ([]byte, error) {
	s.broadcastProgress(ConnectionProgressInput{SiteID: siteId, Step: "decrypt", Status: stagestatus.Running.String(), Message: "Decrypting credentials..."})
	password, err := decrypt(encrypted, s.encryptionKey)
	if err != nil {
		s.broadcastProgress(ConnectionProgressInput{SiteID: siteId, Step: "decrypt", Status: stagestatus.Failed.String(), Message: "Failed to decrypt credentials", Details: toJson(ErrorDetail{Error: err.Error()})})
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}
	s.broadcastProgress(ConnectionProgressInput{SiteID: siteId, Step: "decrypt", Status: stagestatus.Completed.String(), Message: "Credentials decrypted"})
	return password, nil
}

// executeConnectionTest runs the connection test and processes the result.
func (s *Service) executeConnectionTest(ctx context.Context, id int64, site *models.Site, client *wordpress.Client) (*ConnectionResult, error) {
	s.broadcastProgress(ConnectionProgressInput{SiteID: id, Step: "connect", Status: stagestatus.Running.String(), Message: fmt.Sprintf("Connecting to %s...", site.Url)})

	result := &ConnectionResult{}
	connInfo, err := client.TestConnection()
	if err != nil {
		return s.handleConnectionFailure(ctx, id, site, result, err), nil
	}

	return s.handleConnectionSuccess(ctx, id, site, result, connInfo), nil
}

// handleConnectionFailure processes a failed connection test.
func (s *Service) handleConnectionFailure(ctx context.Context, id int64, site *models.Site, result *ConnectionResult, err error) *ConnectionResult {
	result.IsSuccess = false
	result.Message = err.Error()
	s.broadcastProgress(ConnectionProgressInput{
		SiteID: id, Step: "api_test", Status: stagestatus.Failed.String(),
		Message: fmt.Sprintf("Connection failed: %s", err.Error()),
		Details: toJson(ConnectionFailureDetails{Url: site.Url, Username: site.Username}),
	})
	s.updateConnectionStatus(ctx, id, connectionstatus.Disconnected.DBValue())
	s.broadcastProgress(ConnectionProgressInput{SiteID: id, Step: connectionstep.Complete.String(), Status: stagestatus.Failed.String(), Message: "Connection test failed"})
	return result
}

// handleConnectionSuccess processes a successful connection test.
func (s *Service) handleConnectionSuccess(ctx context.Context, id int64, site *models.Site, result *ConnectionResult, connInfo *wordpress.ConnectionInfo) *ConnectionResult {
	result.IsSuccess = true
	result.WPVersion = connInfo.WPVersion
	result.PluginsEndpoint = true
	result.Message = "Connection successful"

	s.broadcastProgress(ConnectionProgressInput{
		SiteID: id, Step: "api_test", Status: stagestatus.Completed.String(),
		Message: fmt.Sprintf("WordPress %s detected, REST API accessible", connInfo.WPVersion),
		Details: toJson(ConnectionSuccessDetails{WPVersion: connInfo.WPVersion}),
	})
	s.updateConnectionStatus(ctx, id, connectionstatus.Connected.DBValue())
	s.broadcastProgress(ConnectionProgressInput{SiteID: id, Step: connectionstep.Complete.String(), Status: stagestatus.Completed.String(), Message: "Connection test completed successfully"})
	s.log.Info("Site connection tested", "id", id, "success", result.IsSuccess)
	return result
}

// TestConnectionWithCredentials tests a connection without saving
func (s *Service) TestConnectionWithCredentials(ctx context.Context, siteUrl, username, password string) (*ConnectionResult, error) {
	normalizedUrl := normalizeUrl(siteUrl)
	s.broadcastProgress(ConnectionProgressInput{SiteID: 0, Step: "start", Status: stagestatus.Running.String(), Message: "Testing connection with provided credentials..."})
	s.broadcastProgress(ConnectionProgressInput{SiteID: 0, Step: "normalize", Status: stagestatus.Completed.String(), Message: fmt.Sprintf("Normalized URL: %s", normalizedUrl), Details: toJson(UrlNormalizeDetails{OriginalUrl: siteUrl, NormalizedUrl: normalizedUrl})})

	progressCallback := func(event wordpress.ProgressEvent) {
		s.broadcastProgress(ConnectionProgressInput{
			SiteID: 0, Step: event.Step, Status: event.Status, Message: event.Message, Details: event.Details,
		})
	}
	client := s.wpClientFactory(normalizedUrl, username, password, progressCallback)

	return s.executeCredentialsTest(client, normalizedUrl, username)
}

// executeCredentialsTest runs the connection test with provided credentials.
func (s *Service) executeCredentialsTest(client *wordpress.Client, normalizedUrl, username string) (*ConnectionResult, error) {
	result := &ConnectionResult{}
	connInfo, err := client.TestConnection()
	if err != nil {
		result.IsSuccess = false
		result.Message = err.Error()
		s.broadcastProgress(ConnectionProgressInput{SiteID: 0, Step: "api_test", Status: stagestatus.Failed.String(), Message: fmt.Sprintf("Connection failed: %s", err.Error()), Details: toJson(ConnectionFailureDetails{Url: normalizedUrl, Username: username})})
		s.broadcastProgress(ConnectionProgressInput{SiteID: 0, Step: "complete", Status: stagestatus.Failed.String(), Message: "Connection test failed"})
		return result, nil
	}

	return s.buildCredentialsSuccess(result, connInfo), nil
}

// buildCredentialsSuccess builds a success result for a credentials test.
func (s *Service) buildCredentialsSuccess(result *ConnectionResult, connInfo *wordpress.ConnectionInfo) *ConnectionResult {
	result.IsSuccess = true
	result.WPVersion = connInfo.WPVersion
	result.PluginsEndpoint = true
	result.Message = "Connection successful"
	s.broadcastProgress(ConnectionProgressInput{SiteID: 0, Step: "api_test", Status: stagestatus.Completed.String(), Message: fmt.Sprintf("WordPress %s detected", connInfo.WPVersion), Details: toJson(ConnectionSuccessDetails{WPVersion: connInfo.WPVersion})})
	s.broadcastProgress(ConnectionProgressInput{SiteID: 0, Step: "complete", Status: stagestatus.Completed.String(), Message: "Connection test completed successfully"})
	return result
}

// broadcastProgress sends connection test progress via WebSocket
func (s *Service) broadcastProgress(input ConnectionProgressInput) {
	if s.wsHub != nil {
		s.wsHub.BroadcastConnectionTestProgress(input)
	}
	s.log.Debug("Connection test progress", "siteId", input.SiteID, "step", input.Step, "status", input.Status, "message", input.Message)
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
	hasHttpPrefix := strings.HasPrefix(rawUrl, "http://")
	hasHttpsPrefix := strings.HasPrefix(rawUrl, "https://")
	isMissingScheme := !hasHttpPrefix && !hasHttpsPrefix

	if isMissingScheme {
		rawUrl = "https://" + rawUrl
	}
	parsed, err := url.Parse(rawUrl)
	if err != nil {
		return strings.TrimSuffix(rawUrl, "/")
	}

	parsed.Path = stripWordPressPaths(parsed.Path)
	parsed.RawQuery = ""
	parsed.Fragment = ""
	return parsed.String()
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

