// Package ws provides WebSocket functionality for real-time updates
package ws

import (
	"encoding/json"
	"net/http"
	"sync"
	"time"

	connectionstatus "wp-plugin-publish/internal/enums/connectionstatustype"

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
	Type      string
	Data      any
	Timestamp string
	SessionId string `json:",omitempty"`
}

// --- Typed broadcast data structs (GE pattern: no raw map[string]any in business logic) ---

// SyncProgressData holds sync progress broadcast payload.
type SyncProgressData struct {
	PluginId int64
	SiteId   int64
	Progress int
	Total    int
	Message  string
}

// ScanProgressData holds scan progress broadcast payload.
type ScanProgressData struct {
	PluginId     int64
	FilesScanned int
	TotalFiles   int
	CurrentFile  string
}

// PublishProgressData holds publish progress broadcast payload.
type PublishProgressData struct {
	PluginId int64
	SiteId   int64
	Stage    string
	Progress int
	Message  string
}

// FileChangeData holds file change broadcast payload.
type FileChangeData struct {
	PluginId   int64
	FilePath   string
	ChangeType string
}

// ErrorData holds error broadcast payload.
type ErrorData struct {
	Code    string
	Message string
	Context json.RawMessage `json:",omitempty"`
}

// ConnectionTestProgressData holds connection test progress broadcast payload.
type ConnectionTestProgressData struct {
	SiteId  int64
	Step    string
	Status  string
	Message string
	Details json.RawMessage `json:",omitempty"`
}

// LogData holds log broadcast payload.
type LogData struct {
	Level   string
	Message string
	Context json.RawMessage `json:",omitempty"`
}

// OperationLogData holds operation log broadcast payload.
type OperationLogData struct {
	OperationType string
	PluginId      int64
	SiteId        int64
	SessionId     string            `json:",omitempty"`
	Log           OperationLogEntry
}

// ConnectionConfirmation holds connection confirmation broadcast payload.
type ConnectionConfirmation struct {
	Status   string
	ClientId string
}

// IncomingMessage represents a parsed incoming WebSocket message.
// Data is kept as json.RawMessage (parse boundary) to be narrowed per msg.Type.
type IncomingMessage struct {
	Type string          `json:"type"` // external key (WebSocket client JSON)
	Data json.RawMessage `json:"data"` // external key
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

// Broadcast sends a typed message to all connected clients.
// Generic: callers get compile-time type checking on the data parameter.
// Note: Go methods cannot have type parameters, so this is a package-level function.
func Broadcast[T any](h *Hub, eventType string, data T) {
	BroadcastWithSession(h, eventType, data, "")
}

// BroadcastWithSession sends a typed message to all connected clients with a session ID.
func BroadcastWithSession[T any](h *Hub, eventType string, data T, sessionId string) {
	h.broadcast <- &Message{
		Type:      eventType,
		Data:      data,
		Timestamp: utcTimestamp(),
		SessionId: sessionId,
	}
}

// BroadcastSyncProgress sends a sync progress update
func (h *Hub) BroadcastSyncProgress(data SyncProgressData) {
	Broadcast(h, EventSyncProgress, data)
}

// BroadcastScanProgress sends a scan progress update
func (h *Hub) BroadcastScanProgress(data ScanProgressData) {
	Broadcast(h, EventScanProgress, data)
}

// BroadcastPublishProgress sends a publish progress update
func (h *Hub) BroadcastPublishProgress(data PublishProgressData) {
	Broadcast(h, EventPublishProgress, data)
}

// BroadcastFileChange notifies clients of a file change
func (h *Hub) BroadcastFileChange(pluginId int64, filePath, changeType string) {
	Broadcast(h, EventFileChange, FileChangeData{
		PluginId:   pluginId,
		FilePath:   filePath,
		ChangeType: changeType,
	})
}

// BroadcastError sends an error notification
func (h *Hub) BroadcastError(code, message string, context json.RawMessage) {
	Broadcast(h, EventError, ErrorData{
		Code:    code,
		Message: message,
		Context: context,
	})
}

// BroadcastConnectionTestProgress sends a connection test progress update
func (h *Hub) BroadcastConnectionTestProgress(data ConnectionTestProgressData) {
	Broadcast(h, EventConnectionTestProgress, data)
}

// BroadcastLog sends a log message to all clients
func (h *Hub) BroadcastLog(level string, message string, context json.RawMessage) {
	Broadcast(h, EventLog, LogData{
		Level:   level,
		Message: message,
		Context: context,
	})
}

// OperationLogEntry represents a single log entry for an operation
type OperationLogEntry struct {
	Timestamp string          // Format: [vX.X.X YYYY-MM-DD HH:MM:SS]
	Level     string          // debug, info, warn, error
	Step      string          // backup, package, upload, activate, etc.
	Message   string
	Details   json.RawMessage `json:",omitempty"`
	File      string          `json:",omitempty"` // Source file path
	Line      int             `json:",omitempty"` // Source line number
}

// OperationLogInput holds parameters for operation log broadcasts.
type OperationLogInput struct {
	OperationType string
	PluginID      int64
	SiteID        int64
	SessionID     string
	Entry         OperationLogEntry
}

// BroadcastOperationLog sends a detailed operation log entry for publish/sync/backup
func (h *Hub) BroadcastOperationLog(input OperationLogInput) {
	input.SessionID = ""
	h.BroadcastOperationLogWithSession(input)
}

// BroadcastOperationLogWithSession sends a detailed operation log entry with session ID
func (h *Hub) BroadcastOperationLogWithSession(input OperationLogInput) {
	if input.Entry.Timestamp == "" {
		input.Entry.Timestamp = formatLogTimestamp()
	}
	BroadcastWithSession(h, EventLog, OperationLogData{
		OperationType: input.OperationType,
		PluginId:      input.PluginID,
		SiteId:        input.SiteID,
		SessionId:     input.SessionID,
		Log:           input.Entry,
	}, input.SessionID)
}

// BroadcastPublishLog is a convenience method for publish operation logs
func (h *Hub) BroadcastPublishLog(input OperationLogInput) {
	input.OperationType = "publish"
	h.BroadcastOperationLog(input)
}

// BroadcastPublishLogWithSession is a convenience method for publish operation logs with session
func (h *Hub) BroadcastPublishLogWithSession(input OperationLogInput) {
	input.OperationType = "publish"
	h.BroadcastOperationLogWithSession(input)
}

// BroadcastSyncLog is a convenience method for sync operation logs
func (h *Hub) BroadcastSyncLog(input OperationLogInput) {
	input.OperationType = "sync"
	h.BroadcastOperationLog(input)
}

// BroadcastSyncLogWithSession is a convenience method for sync operation logs with session
func (h *Hub) BroadcastSyncLogWithSession(input OperationLogInput) {
	input.OperationType = "sync"
	h.BroadcastOperationLogWithSession(input)
}

// BroadcastBackupLog is a convenience method for backup operation logs
func (h *Hub) BroadcastBackupLog(input OperationLogInput) {
	input.OperationType = "backup"
	h.BroadcastOperationLog(input)
}

// BroadcastBackupLogWithSession is a convenience method for backup operation logs with session
func (h *Hub) BroadcastBackupLogWithSession(input OperationLogInput) {
	input.OperationType = "backup"
	h.BroadcastOperationLogWithSession(input)
}

// RemotePluginLogInput holds parameters for remote plugin log broadcasts.
type RemotePluginLogInput struct {
	SiteID    int64
	Action    string
	SessionID string
	Level     string
	Step      string
	Message   string
	Details   json.RawMessage
}

// BroadcastRemotePluginLog is a convenience method for remote plugin action logs
func (h *Hub) BroadcastRemotePluginLog(input RemotePluginLogInput) {
	input.SessionID = ""
	h.BroadcastRemotePluginLogWithSession(input)
}

// BroadcastRemotePluginLogWithSession is a convenience method for remote plugin action logs with session
func (h *Hub) BroadcastRemotePluginLogWithSession(input RemotePluginLogInput) {
	h.BroadcastOperationLogWithSession(OperationLogInput{
		OperationType: "remote_plugin_" + input.Action,
		SiteID:        input.SiteID,
		SessionID:     input.SessionID,
		Entry: OperationLogEntry{
			Level: input.Level, Step: input.Step, Message: input.Message, Details: input.Details,
		},
	})
}

// BroadcastWithSession is a method wrapper for the package-level generic BroadcastWithSession function,
// satisfying interfaces that require a method on *Hub.
func (h *Hub) BroadcastWithSession(eventType string, data any, sessionId string) {
	h.broadcast <- &Message{
		Type:      eventType,
		Data:      data,
		SessionId: sessionId,
	}
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
	Broadcast(h, EventConnection, ConnectionConfirmation{
		Status:   connectionstatus.Connected.DBValue(),
		ClientId: conn.RemoteAddr().String(),
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
