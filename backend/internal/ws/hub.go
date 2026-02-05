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
	Type      string      `json:"type"`
	Data      interface{} `json:"data"`
	Timestamp string      `json:"timestamp"`
	SessionID string      `json:"sessionId,omitempty"`
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
func (h *Hub) Broadcast(eventType string, data interface{}) {
	h.BroadcastWithSession(eventType, data, "")
}

// BroadcastWithSession sends a message to all connected clients with a session ID
func (h *Hub) BroadcastWithSession(eventType string, data interface{}, sessionID string) {
	h.broadcast <- &Message{
		Type:      eventType,
		Data:      data,
		Timestamp: utcTimestamp(),
		SessionID: sessionID,
	}
}

// BroadcastSyncProgress sends a sync progress update
func (h *Hub) BroadcastSyncProgress(pluginID, siteID int64, progress int, total int, message string) {
	h.Broadcast(EventSyncProgress, map[string]interface{}{
		"pluginId": pluginID,
		"siteId":   siteID,
		"progress": progress,
		"total":    total,
		"message":  message,
	})
}

// BroadcastScanProgress sends a scan progress update
func (h *Hub) BroadcastScanProgress(pluginID int64, filesScanned int, totalFiles int, currentFile string) {
	h.Broadcast(EventScanProgress, map[string]interface{}{
		"pluginId":     pluginID,
		"filesScanned": filesScanned,
		"totalFiles":   totalFiles,
		"currentFile":  currentFile,
	})
}

// BroadcastPublishProgress sends a publish progress update
func (h *Hub) BroadcastPublishProgress(pluginID, siteID int64, stage string, progress int, message string) {
	h.Broadcast(EventPublishProgress, map[string]interface{}{
		"pluginId": pluginID,
		"siteId":   siteID,
		"stage":    stage,
		"progress": progress,
		"message":  message,
	})
}

// BroadcastFileChange notifies clients of a file change
func (h *Hub) BroadcastFileChange(pluginID int64, filePath, changeType string) {
	h.Broadcast(EventFileChange, map[string]interface{}{
		"pluginId":   pluginID,
		"filePath":   filePath,
		"changeType": changeType,
	})
}

// BroadcastError sends an error notification
func (h *Hub) BroadcastError(code, message string, context map[string]interface{}) {
	h.Broadcast(EventError, map[string]interface{}{
		"code":    code,
		"message": message,
		"context": context,
	})
}

// BroadcastConnectionTestProgress sends a connection test progress update
func (h *Hub) BroadcastConnectionTestProgress(siteID int64, step string, status string, message string, details map[string]interface{}) {
	data := map[string]interface{}{
		"siteId":  siteID,
		"step":    step,
		"status":  status,
		"message": message,
	}
	if details != nil {
		data["details"] = details
	}
	h.Broadcast(EventConnectionTestProgress, data)
}

// BroadcastLog sends a log message to all clients
func (h *Hub) BroadcastLog(level string, message string, context map[string]interface{}) {
	h.Broadcast(EventLog, map[string]interface{}{
		"level":   level,
		"message": message,
		"context": context,
	})
}

// OperationLogEntry represents a single log entry for an operation
type OperationLogEntry struct {
	Timestamp string                 `json:"timestamp"` // Format: [vX.X.X YYYY-MM-DD HH:MM:SS]
	Level     string                 `json:"level"`  // debug, info, warn, error
	Step      string                 `json:"step"`   // backup, package, upload, activate, etc.
	Message   string                 `json:"message"`
	Details   map[string]interface{} `json:"details,omitempty"`
	File      string                 `json:"file,omitempty"`  // Source file path
	Line      int                    `json:"line,omitempty"`  // Source line number
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
	h.BroadcastWithSession(EventLog, map[string]interface{}{
		"operationType": operationType,
		"pluginId":      pluginID,
		"siteId":        siteID,
		"sessionId":     sessionID,
		"log":           entry,
	}, sessionID)
}

// BroadcastPublishLog is a convenience method for publish operation logs
func (h *Hub) BroadcastPublishLog(pluginID, siteID int64, level, step, message string, details map[string]interface{}) {
	h.BroadcastPublishLogWithSession(pluginID, siteID, "", level, step, message, details)
}

// BroadcastPublishLogWithSession is a convenience method for publish operation logs with session
func (h *Hub) BroadcastPublishLogWithSession(pluginID, siteID int64, sessionID, level, step, message string, details map[string]interface{}) {
	h.BroadcastOperationLogWithSession("publish", pluginID, siteID, sessionID, OperationLogEntry{
		Level:   level,
		Step:    step,
		Message: message,
		Details: details,
	})
}

// BroadcastSyncLog is a convenience method for sync operation logs
func (h *Hub) BroadcastSyncLog(pluginID, siteID int64, level, step, message string, details map[string]interface{}) {
	h.BroadcastOperationLog("sync", pluginID, siteID, OperationLogEntry{
		Level:   level,
		Step:    step,
		Message: message,
		Details: details,
	})
}

// BroadcastSyncLogWithSession is a convenience method for sync operation logs with session
func (h *Hub) BroadcastSyncLogWithSession(pluginID, siteID int64, sessionID, level, step, message string, details map[string]interface{}) {
	h.BroadcastOperationLogWithSession("sync", pluginID, siteID, sessionID, OperationLogEntry{
		Level:   level,
		Step:    step,
		Message: message,
		Details: details,
	})
}

// BroadcastBackupLog is a convenience method for backup operation logs
func (h *Hub) BroadcastBackupLog(pluginID int64, level, step, message string, details map[string]interface{}) {
	h.BroadcastOperationLog("backup", pluginID, 0, OperationLogEntry{
		Level:   level,
		Step:    step,
		Message: message,
		Details: details,
	})
}

// BroadcastBackupLogWithSession is a convenience method for backup operation logs with session
func (h *Hub) BroadcastBackupLogWithSession(pluginID int64, sessionID, level, step, message string, details map[string]interface{}) {
	h.BroadcastOperationLogWithSession("backup", pluginID, 0, sessionID, OperationLogEntry{
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
	h.Broadcast(EventConnection, map[string]string{
		"status":    "connected",
		"clientId":  conn.RemoteAddr().String(),
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
	var msg struct {
		Type string                 `json:"type"`
		Data map[string]interface{} `json:"data"`
	}

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
