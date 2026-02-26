// Package session provides session-based logging for operations
package session

import (
	"encoding/json"
	"os"
	"sync"
	"time"

	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// SessionType identifies the type of operation
type SessionType string

const (
	SessionTypePublish             SessionType = "publish"
	SessionTypeSync                SessionType = "sync"
	SessionTypeBackup              SessionType = "backup"
	SessionTypeConnect             SessionType = "connect"
	SessionTypeBulkPublish         SessionType = "bulk_publish"
	SessionTypeRemotePluginEnable  SessionType = "remote_plugin_enable"
	SessionTypeRemotePluginDisable SessionType = "remote_plugin_disable"
	SessionTypeRemotePluginDelete  SessionType = "remote_plugin_delete"
)

// Session represents an active or completed operation session
type Session struct {
	ID         string          `json:",omitempty"`
	Type       SessionType
	PluginID   int64           `json:",omitempty"`
	SiteID     int64           `json:",omitempty"`
	PluginName string          `json:",omitempty"`
	SiteName   string          `json:",omitempty"`
	Status     string          // running, success, error
	StartedAt  time.Time
	EndedAt    *time.Time      `json:",omitempty"`
	ErrorMsg   string          `json:",omitempty"`
	Metadata   json.RawMessage `json:",omitempty"`
	logFile    *os.File
	mu         sync.Mutex
}

// LogEntry represents a single log entry in a session
type LogEntry struct {
	Timestamp string
	Level     string          // debug, info, warn, error
	Step      string          // backup, package, upload, activate, etc.
	Message   string
	Details   json.RawMessage `json:",omitempty"`
}

// SessionDiagnostics is the structured payload returned for error modal / session detail view
type SessionDiagnostics struct {
	Request          *SessionRequest    `json:",omitempty"`
	Response         *SessionResponse   `json:",omitempty"`
	StackTrace       *SessionStackTrace `json:",omitempty"`
	PHPStackTraceLog string             `json:",omitempty"`
}

// SessionRequest captures the original inbound request
type SessionRequest struct {
	URL     string
	Method  string
	Headers map[string]string `json:",omitempty"`
	Body    json.RawMessage   `json:",omitempty"`
}

// SessionResponse captures the delegated response from WordPress
type SessionResponse struct {
	RequestURL  string
	ResponseURL string
	StatusCode  int
	Headers     map[string]string `json:",omitempty"`
	Body        json.RawMessage   `json:",omitempty"`
}

// SessionStackTrace holds dual Go + PHP stack traces
type SessionStackTrace struct {
	Golang []StackFrame `json:",omitempty"`
	PHP    []StackFrame `json:",omitempty"`
}

// StackFrame represents a single frame in a stack trace
type StackFrame struct {
	Function string
	File     string `json:",omitempty"`
	Line     int    `json:",omitempty"`
	Class    string `json:",omitempty"`
}

// Config holds session service configuration
type Config struct {
	DataDir       string         // Base data directory
	Logger        *logger.Logger
	RetentionDays int            // Days to keep old sessions (default 7)
}

// Service manages operation sessions and their logs
type Service struct {
	dataDir       string
	sessionsDir   string
	log           *logger.Logger
	retentionDays int
	sessions      map[string]*Session
	mu            sync.RWMutex
}

// New creates a new session service
func New(cfg Config) (*Service, error) {
	retentionDays := cfg.RetentionDays
	if retentionDays <= 0 {
		retentionDays = 7
	}

	sessionsDir, err := ensureSessionsDir(cfg.DataDir)
	if err != nil {
		return nil, err
	}

	return startService(cfg, sessionsDir, retentionDays), nil
}

// ensureSessionsDir resolves and creates the sessions directory.
func ensureSessionsDir(dataDir string) (string, error) {
	sessionsDir, err := pathutil.Join(dataDir, "sessions")
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrSessionInit, "resolve sessions directory")
	}
	if err := os.MkdirAll(sessionsDir, 0755); err != nil {
		return "", apperror.Wrap(err, apperror.ErrSessionInit, "create sessions directory").
			WithPath(sessionsDir)
	}
	return sessionsDir, nil
}

// startService builds the Service and starts the background cleanup loop.
func startService(cfg Config, sessionsDir string, retentionDays int) *Service {
	s := &Service{
		dataDir:       cfg.DataDir,
		sessionsDir:   sessionsDir,
		log:           cfg.Logger,
		retentionDays: retentionDays,
		sessions:      make(map[string]*Session),
	}
	go s.cleanupLoop()
	return s
}

// getSessionDir returns the directory path for a session
func (s *Service) getSessionDir(sessionID string) (string, error) {
	return pathutil.Join(s.sessionsDir, sessionID)
}

// getLogPath returns the file path for a session's main log
func (s *Service) getLogPath(sessionID string) (string, error) {
	dir, err := s.getSessionDir(sessionID)
	if err != nil {
		return "", err
	}
	return pathutil.Join(dir, "session.log")
}

// getRequestPath returns the file path for request.json
func (s *Service) getRequestPath(sessionID string) (string, error) {
	dir, err := s.getSessionDir(sessionID)
	if err != nil {
		return "", err
	}
	return pathutil.Join(dir, "request.json")
}

// getResponsePath returns the file path for response.json
func (s *Service) getResponsePath(sessionID string) (string, error) {
	dir, err := s.getSessionDir(sessionID)
	if err != nil {
		return "", err
	}
	return pathutil.Join(dir, "response.json")
}

// getErrorLogPath returns the file path for error.log
func (s *Service) getErrorLogPath(sessionID string) (string, error) {
	dir, err := s.getSessionDir(sessionID)
	if err != nil {
		return "", err
	}
	return pathutil.Join(dir, "error.log")
}

// SessionSummary provides a brief overview of a session
type SessionSummary struct {
	ID         string
	Type       SessionType
	PluginID   int64      `json:",omitempty"`
	SiteID     int64      `json:",omitempty"`
	PluginName string     `json:",omitempty"`
	SiteName   string     `json:",omitempty"`
	Status     string
	StartedAt  time.Time
	EndedAt    *time.Time `json:",omitempty"`
}
