// Package ws provides WebSocket functionality for real-time updates
package ws

import (
	"encoding/json"
	"net/http"
	"sync"
	"time"

	"github.com/gorilla/websocket"
)

func utcTimestamp() string {
	// Human-readable UTC format: YYYY-MM-DD HH:MM:SS
	return time.Now().UTC().Format("2006-01-02 15:04:05")
}

// appVersion is set during hub initialization
var appVersion string = "0.0.0"

// SetAppVersion sets the app version for log formatting
func SetAppVersion(version string) {
	appVersion = version
}

// formatLogTimestamp creates the standardized log timestamp prefix
func formatLogTimestamp() string {
	return "[v" + appVersion + " " + time.Now().UTC().Format("2006-01-02 15:04:05") + "]"
}

var upgrader = websocket.Upgrader{
	CheckOrigin: func(r *http.Request) bool {
		// Allow connections from local development
		return true
	},
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
}

// Hub maintains active WebSocket connections and broadcasts messages
type Hub struct {
	clients    map[*Client]bool
	broadcast  chan *Message
	register   chan *Client
	unregister chan *Client
	mu         sync.RWMutex
}

// Client represents a single WebSocket connection
type Client struct {
	hub  *Hub
	conn *websocket.Conn
	send chan []byte
}

// Message represents a WebSocket message
type Message struct {
	Type      string `json:"type"`
	Data      any    `json:"data"`
	Timestamp string `json:"timestamp"`
	SessionID string `json:"sessionId,omitempty"`
}

// --- Typed broadcast data structs (GE pattern: no raw map[string]any in business logic) ---

// SyncProgressData holds sync progress broadcast payload.
type SyncProgressData struct {
	PluginID int64  `json:"pluginId"`
	SiteID   int64  `json:"siteId"`
	Progress int    `json:"progress"`
	Total    int    `json:"total"`
	Message  string `json:"message"`
}

// ScanProgressData holds scan progress broadcast payload.
type ScanProgressData struct {
	PluginID     int64  `json:"pluginId"`
	FilesScanned int    `json:"filesScanned"`
	TotalFiles   int    `json:"totalFiles"`
	CurrentFile  string `json:"currentFile"`
}

// PublishProgressData holds publish progress broadcast payload.
type PublishProgressData struct {
	PluginID int64  `json:"pluginId"`
	SiteID   int64  `json:"siteId"`
	Stage    string `json:"stage"`
	Progress int    `json:"progress"`
	Message  string `json:"message"`
}

// FileChangeData holds file change broadcast payload.
type FileChangeData struct {
	PluginID   int64  `json:"pluginId"`
	FilePath   string `json:"filePath"`
	ChangeType string `json:"changeType"`
}

// ErrorData holds error broadcast payload.
type ErrorData struct {
	Code    string         `json:"code"`
	Message string         `json:"message"`
	Context map[string]any `json:"context,omitempty"`
}

// ConnectionTestProgressData holds connection test progress broadcast payload.
type ConnectionTestProgressData struct {
	SiteID  int64          `json:"siteId"`
	Step    string         `json:"step"`
	Status  string         `json:"status"`
	Message string         `json:"message"`
	Details map[string]any `json:"details,omitempty"`
}

// LogData holds log broadcast payload.
type LogData struct {
	Level   string         `json:"level"`
	Message string         `json:"message"`
	Context map[string]any `json:"context,omitempty"`
}

// OperationLogData holds operation log broadcast payload.
type OperationLogData struct {
	OperationType string            `json:"operationType"`
	PluginID      int64             `json:"pluginId"`
	SiteID        int64             `json:"siteId"`
	SessionID     string            `json:"sessionId,omitempty"`
	Log           OperationLogEntry `json:"log"`
}

// ConnectionConfirmation holds connection confirmation broadcast payload.
type ConnectionConfirmation struct {
	Status   string `json:"status"`
	ClientID string `json:"clientId"`
}

// IncomingMessage represents a parsed incoming WebSocket message.
// Data is kept as json.RawMessage (parse boundary) to be narrowed per msg.Type.
type IncomingMessage struct {
	Type string          `json:"type"`
	Data json.RawMessage `json:"data"`
}

// Event types for WebSocket messages
const (
	// File and sync events
	EventFileChange     = "file_change"
	EventSyncStarted    = "sync_started"
	EventSyncProgress   = "sync_progress"
	EventSyncComplete   = "sync_complete"
	
	// Publish events
	EventPublishStarted  = "publish_started"
	EventPublishProgress = "publish_progress"
	EventPublishComplete = "publish_complete"
	
	// Auto-publish events
	EventAutoPublishTriggered = "auto_publish_triggered"
	EventAutoPublishComplete  = "auto_publish_complete"
	EventAutoPublishFailed    = "auto_publish_failed"
	
	// Scan events
	EventScanStarted  = "scan_started"
	EventScanProgress = "scan_progress"
	EventScanComplete = "scan_complete"
	
	// Git events
	EventGitPullStarted   = "git_pull_started"
	EventGitPullComplete  = "git_pull_complete"
	EventGitPullFailed    = "git_pull_failed"
	EventGitPullAllComplete = "git_pull_all_complete"
	EventGitCommitComplete = "git_commit_complete"
	EventGitPushComplete  = "git_push_complete"
	
	// Build events
	EventBuildStarted  = "build_started"
	EventBuildComplete = "build_complete"
	EventBuildFailed   = "build_failed"
	
	// Connection test events
	EventConnectionTestStarted  = "connection_test_started"
	EventConnectionTestProgress = "connection_test_progress"
	EventConnectionTestComplete = "connection_test_complete"
	
	// Remote plugin action events
	EventRemotePluginActionStarted  = "remote_plugin_action_started"
	EventRemotePluginActionProgress = "remote_plugin_action_progress"
	EventRemotePluginActionComplete = "remote_plugin_action_complete"
	
	// Version history events
	EventVersionCreated   = "version_created"
	EventRollbackStarted  = "rollback_started"
	EventRollbackComplete = "rollback_complete"
	EventRollbackFailed   = "rollback_failed"
	
	// E2E test events
	EventE2ERunStarted    = "e2e_run_started"
	EventE2ETestStarted   = "e2e_test_started"
	EventE2ETestCompleted = "e2e_test_completed"
	EventE2ERunCompleted  = "e2e_run_completed"
	
	// General events
	EventError      = "error"
	EventConnection = "connection"
	EventLog        = "log"
)

// NewHub creates a new Hub instance
func NewHub() *Hub {
	return &Hub{
		clients:    make(map[*Client]bool),
		broadcast:  make(chan *Message, 256),
		register:   make(chan *Client),
		unregister: make(chan *Client),
	}
}

// Run starts the hub's event loop
func (h *Hub) Run() {
	for {
		select {
		case client := <-h.register:
			h.mu.Lock()
			h.clients[client] = true
			h.mu.Unlock()

		case client := <-h.unregister:
			h.mu.Lock()
			if _, ok := h.clients[client]; ok {
				delete(h.clients, client)
				close(client.send)
			}
			h.mu.Unlock()

		case message := <-h.broadcast:
			data, err := json.Marshal(message)
			if err != nil {
				continue
			}

			h.mu.RLock()
			for client := range h.clients {
				select {
				case client.send <- data:
				default:
					close(client.send)
					delete(h.clients, client)
				}
			}
			h.mu.RUnlock()
		}
	}
}

// Broadcast sends a message to all connected clients
func (h *Hub) Broadcast(eventType string, data any) {
	h.BroadcastWithSession(eventType, data, "")
}

// BroadcastWithSession sends a message to all connected clients with a session ID
func (h *Hub) BroadcastWithSession(eventType string, data any, sessionID string) {
	h.broadcast <- &Message{
		Type:      eventType,
		Data:      data,
		Timestamp: utcTimestamp(),
		SessionID: sessionID,
	}
}

// BroadcastSyncProgress sends a sync progress update
func (h *Hub) BroadcastSyncProgress(pluginID, siteID int64, progress int, total int, message string) {
	h.Broadcast(EventSyncProgress, SyncProgressData{
		PluginID: pluginID,
		SiteID:   siteID,
		Progress: progress,
		Total:    total,
		Message:  message,
	})
}

// BroadcastScanProgress sends a scan progress update
func (h *Hub) BroadcastScanProgress(pluginID int64, filesScanned int, totalFiles int, currentFile string) {
	h.Broadcast(EventScanProgress, ScanProgressData{
		PluginID:     pluginID,
		FilesScanned: filesScanned,
		TotalFiles:   totalFiles,
		CurrentFile:  currentFile,
	})
}

// BroadcastPublishProgress sends a publish progress update
func (h *Hub) BroadcastPublishProgress(pluginID, siteID int64, stage string, progress int, message string) {
	h.Broadcast(EventPublishProgress, PublishProgressData{
		PluginID: pluginID,
		SiteID:   siteID,
		Stage:    stage,
		Progress: progress,
		Message:  message,
	})
}

// BroadcastFileChange notifies clients of a file change
func (h *Hub) BroadcastFileChange(pluginID int64, filePath, changeType string) {
	h.Broadcast(EventFileChange, FileChangeData{
		PluginID:   pluginID,
		FilePath:   filePath,
		ChangeType: changeType,
	})
}

// BroadcastError sends an error notification
func (h *Hub) BroadcastError(code, message string, context map[string]any) {
	h.Broadcast(EventError, ErrorData{
		Code:    code,
		Message: message,
		Context: context,
	})
}

// BroadcastConnectionTestProgress sends a connection test progress update
func (h *Hub) BroadcastConnectionTestProgress(siteID int64, step string, status string, message string, details map[string]any) {
	h.Broadcast(EventConnectionTestProgress, ConnectionTestProgressData{
		SiteID:  siteID,
		Step:    step,
		Status:  status,
		Message: message,
		Details: details,
	})
}

// BroadcastLog sends a log message to all clients
func (h *Hub) BroadcastLog(level string, message string, context map[string]any) {
	h.Broadcast(EventLog, LogData{
		Level:   level,
		Message: message,
		Context: context,
	})
}

// OperationLogEntry represents a single log entry for an operation
type OperationLogEntry struct {
	Timestamp string         `json:"timestamp"` // Format: [vX.X.X YYYY-MM-DD HH:MM:SS]
	Level     string         `json:"level"`  // debug, info, warn, error
	Step      string         `json:"step"`   // backup, package, upload, activate, etc.
	Message   string         `json:"message"`
	Details   map[string]any `json:"details,omitempty"`
	File      string         `json:"file,omitempty"`  // Source file path
	Line      int            `json:"line,omitempty"`  // Source line number
}

// BroadcastOperationLog sends a detailed operation log entry for publish/sync/backup
func (h *Hub) BroadcastOperationLog(operationType string, pluginID, siteID int64, entry OperationLogEntry) {
	h.BroadcastOperationLogWithSession(operationType, pluginID, siteID, "", entry)
}

// BroadcastOperationLogWithSession sends a detailed operation log entry with session ID
func (h *Hub) BroadcastOperationLogWithSession(operationType string, pluginID, siteID int64, sessionID string, entry OperationLogEntry) {
	if entry.Timestamp == "" {
		entry.Timestamp = formatLogTimestamp()
	}
	h.BroadcastWithSession(EventLog, OperationLogData{
		OperationType: operationType,
		PluginID:      pluginID,
		SiteID:        siteID,
		SessionID:     sessionID,
		Log:           entry,
	}, sessionID)
}

// BroadcastPublishLog is a convenience method for publish operation logs
func (h *Hub) BroadcastPublishLog(pluginID, siteID int64, level, step, message string, details map[string]any) {
	h.BroadcastPublishLogWithSession(pluginID, siteID, "", level, step, message, details)
}

// BroadcastPublishLogWithSession is a convenience method for publish operation logs with session
func (h *Hub) BroadcastPublishLogWithSession(pluginID, siteID int64, sessionID, level, step, message string, details map[string]any) {
	h.BroadcastOperationLogWithSession("publish", pluginID, siteID, sessionID, OperationLogEntry{
		Level:   level,
		Step:    step,
		Message: message,
		Details: details,
	})
}

// BroadcastSyncLog is a convenience method for sync operation logs
func (h *Hub) BroadcastSyncLog(pluginID, siteID int64, level, step, message string, details map[string]any) {
	h.BroadcastOperationLog("sync", pluginID, siteID, OperationLogEntry{
		Level:   level,
		Step:    step,
		Message: message,
		Details: details,
	})
}

// BroadcastSyncLogWithSession is a convenience method for sync operation logs with session
func (h *Hub) BroadcastSyncLogWithSession(pluginID, siteID int64, sessionID, level, step, message string, details map[string]any) {
	h.BroadcastOperationLogWithSession("sync", pluginID, siteID, sessionID, OperationLogEntry{
		Level:   level,
		Step:    step,
		Message: message,
		Details: details,
	})
}

// BroadcastBackupLog is a convenience method for backup operation logs
func (h *Hub) BroadcastBackupLog(pluginID int64, level, step, message string, details map[string]any) {
	h.BroadcastOperationLog("backup", pluginID, 0, OperationLogEntry{
		Level:   level,
		Step:    step,
		Message: message,
		Details: details,
	})
}

// BroadcastBackupLogWithSession is a convenience method for backup operation logs with session
func (h *Hub) BroadcastBackupLogWithSession(pluginID int64, sessionID, level, step, message string, details map[string]any) {
	h.BroadcastOperationLogWithSession("backup", pluginID, 0, sessionID, OperationLogEntry{
		Level:   level,
		Step:    step,
		Message: message,
		Details: details,
	})
}

// BroadcastRemotePluginLog is a convenience method for remote plugin action logs
func (h *Hub) BroadcastRemotePluginLog(siteID int64, action, level, step, message string, details map[string]any) {
	h.BroadcastRemotePluginLogWithSession(siteID, action, "", level, step, message, details)
}

// BroadcastRemotePluginLogWithSession is a convenience method for remote plugin action logs with session
func (h *Hub) BroadcastRemotePluginLogWithSession(siteID int64, action, sessionID, level, step, message string, details map[string]any) {
	h.BroadcastOperationLogWithSession("remote_plugin_"+action, 0, siteID, sessionID, OperationLogEntry{
		Level:   level,
		Step:    step,
		Message: message,
		Details: details,
	})
}

// HandleWebSocket handles WebSocket upgrade requests
func (h *Hub) HandleWebSocket(w http.ResponseWriter, r *http.Request) {
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		return
	}

	client := &Client{
		hub:  h,
		conn: conn,
		send: make(chan []byte, 256),
	}

	h.register <- client

	// Send connection confirmation
	h.Broadcast(EventConnection, ConnectionConfirmation{
		Status:   "connected",
		ClientID: conn.RemoteAddr().String(),
	})

	// Start goroutines for reading and writing
	go client.writePump()
	go client.readPump()
}

// readPump pumps messages from the WebSocket connection to the hub
func (c *Client) readPump() {
	defer func() {
		c.hub.unregister <- c
		c.conn.Close()
	}()

	c.conn.SetReadDeadline(time.Now().Add(60 * time.Second))
	c.conn.SetPongHandler(func(string) error {
		c.conn.SetReadDeadline(time.Now().Add(60 * time.Second))
		return nil
	})

	for {
		_, message, err := c.conn.ReadMessage()
		if err != nil {
			break
		}
		
		// Handle incoming messages (e.g., subscription requests)
		c.handleMessage(message)
	}
}

// handleMessage processes incoming WebSocket messages
func (c *Client) handleMessage(message []byte) {
	var msg IncomingMessage

	if err := json.Unmarshal(message, &msg); err != nil {
		return
	}

	switch msg.Type {
	case "subscribe_plugin":
		// Handle plugin subscription
	case "unsubscribe_plugin":
		// Handle plugin unsubscription
	case "ping":
		// Respond to ping
		c.send <- []byte(`{"type":"pong"}`)
	}
}

// writePump pumps messages from the hub to the WebSocket connection
func (c *Client) writePump() {
	ticker := time.NewTicker(30 * time.Second)
	defer func() {
		ticker.Stop()
		c.conn.Close()
	}()

	for {
		select {
		case message, ok := <-c.send:
			c.conn.SetWriteDeadline(time.Now().Add(10 * time.Second))
			if !ok {
				c.conn.WriteMessage(websocket.CloseMessage, []byte{})
				return
			}

			if err := c.conn.WriteMessage(websocket.TextMessage, message); err != nil {
				return
			}

		case <-ticker.C:
			c.conn.SetWriteDeadline(time.Now().Add(10 * time.Second))
			if err := c.conn.WriteMessage(websocket.PingMessage, nil); err != nil {
				return
			}
		}
	}
}

// ClientCount returns the number of connected clients
func (h *Hub) ClientCount() int {
	h.mu.RLock()
	defer h.mu.RUnlock()
	return len(h.clients)
}
