// Package site provides WordPress site management services
package site

import (
	"context"
	"encoding/json"
	"net/url"
	"strings"
	"sync"

	"wp-plugin-publish/internal/database"
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
	StartSession(input session.StartSessionInput) apperror.Result[string]
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
	isTTLUnset := cacheTTL <= 0

	if isTTLUnset {
		cacheTTL = 60
	}
	return buildService(cfg, cacheTTL)
}

// buildService constructs the Service struct with resolved config.
func buildService(cfg Config, cacheTTL int) *Service {
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

// broadcastProgress sends connection test progress via WebSocket
func (s *Service) broadcastProgress(input ConnectionProgressInput) {
	if s.wsHub != nil {
		s.wsHub.BroadcastConnectionTestProgress(input)
	}
	s.log.Debug("Connection test progress", "siteId", input.SiteID, "step", input.Step, "status", input.Status, "message", input.Message)
}

// GetDecryptedPassword returns the decrypted password for a site
func (s *Service) GetDecryptedPassword(ctx context.Context, id int64) apperror.Result[string] {
	result := s.GetById(ctx, id)
	if result.HasError() {
		return apperror.Fail[string](result.AppError())
	}

	site := result.Value()
	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return apperror.FailWrap[string](err, apperror.ErrInternal, "failed to decrypt password")
	}

	return apperror.Ok(string(password))
}

// normalizeUrl normalizes a URL for consistent storage
func normalizeUrl(rawUrl string) string {
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

// SiteCredentials holds decrypted credentials for API access
type SiteCredentials struct {
	Url         string
	Username    string
	AppPassword string
}

// GetCredentials returns the decrypted credentials for a site
func (s *Service) GetCredentials(ctx context.Context, siteId int64) apperror.Result[SiteCredentials] {
	result := s.GetById(ctx, siteId)
	if result.HasError() {
		return apperror.Fail[SiteCredentials](result.AppError())
	}

	site := result.Value()

	return s.buildSiteCredentials(siteId, site)
}

// buildSiteCredentials decrypts the password and constructs SiteCredentials.
func (s *Service) buildSiteCredentials(siteId int64, site models.Site) apperror.Result[SiteCredentials] {
	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return apperror.FailWrap[SiteCredentials](err, apperror.ErrInternal, "failed to decrypt password")
	}

	s.log.Debug("Credentials retrieved for site", "siteId", siteId, "siteName", site.Name)

	return apperror.Ok(SiteCredentials{
		Url:         site.Url,
		Username:    site.Username,
		AppPassword: string(password),
	})
}

// derefInt safely dereferences an *int pointer, returning 0 if nil.
func derefInt(p *int) int {
	isNilPointer := p == nil

	if isNilPointer {
		return 0
	}
	return *p
}
