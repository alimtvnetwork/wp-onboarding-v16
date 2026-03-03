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

// --- Typed broadcast data structs ---

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
type IncomingMessage struct {
	Type string          `json:"type"` // external key
	Data json.RawMessage `json:"data"` // external key
}

// Event types for WebSocket messages
const (
	EventFileChange     = "fileChange"
	EventSyncStarted    = "syncStarted"
	EventSyncProgress   = "syncProgress"
	EventSyncComplete   = "syncComplete"
	EventPublishStarted  = "publishStarted"
	EventPublishProgress = "publishProgress"
	EventPublishComplete = "publishComplete"
	EventAutoPublishTriggered = "autoPublishTriggered"
	EventAutoPublishComplete  = "autoPublishComplete"
	EventAutoPublishFailed    = "autoPublishFailed"
	EventScanStarted  = "scanStarted"
	EventScanProgress = "scanProgress"
	EventScanComplete = "scanComplete"
	EventGitPullStarted    = "gitPullStarted"
	EventGitPullComplete   = "gitPullComplete"
	EventGitPullFailed     = "gitPullFailed"
	EventGitPullAllComplete = "gitPullAllComplete"
	EventGitCommitComplete = "gitCommitComplete"
	EventGitPushComplete   = "gitPushComplete"
	EventBuildStarted  = "buildStarted"
	EventBuildComplete = "buildComplete"
	EventBuildFailed   = "buildFailed"
	EventConnectionTestStarted  = "connectionTestStarted"
	EventConnectionTestProgress = "connectionTestProgress"
	EventConnectionTestComplete = "connectionTestComplete"
	EventRemotePluginActionStarted  = "remotePluginActionStarted"
	EventRemotePluginActionProgress = "remotePluginActionProgress"
	EventRemotePluginActionComplete = "remotePluginActionComplete"
	EventVersionCreated   = "versionCreated"
	EventRollbackStarted  = "rollbackStarted"
	EventRollbackComplete = "rollbackComplete"
	EventRollbackFailed   = "rollbackFailed"
	EventBulkPublishStarted  = "bulkPublishStarted"
	EventBulkPublishProgress = "bulkPublishProgress"
	EventBulkPublishComplete = "bulkPublishComplete"
	EventE2ERunStarted    = "e2eRunStarted"
	EventE2ETestStarted   = "e2eTestStarted"
	EventE2ETestCompleted = "e2eTestCompleted"
	EventE2ERunCompleted  = "e2eRunCompleted"
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
			_, isRegistered := h.clients[client]

			if isRegistered {
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

// BroadcastInput bundles parameters for BroadcastWithSession.
type BroadcastInput[T any] struct {
	Hub       *Hub
	EventType string
	Data      T
	SessionId string
}

// Broadcast sends a typed message to all connected clients.
func Broadcast[T any](h *Hub, eventType string, data T) {
	BroadcastWithSession(BroadcastInput[T]{
		Hub:       h,
		EventType: eventType,
		Data:      data,
	})
}

// BroadcastWithSession sends a typed message with an optional session ID.
func BroadcastWithSession[T any](input BroadcastInput[T]) {
	input.Hub.broadcast <- &Message{
		Type:      input.EventType,
		Data:      input.Data,
		Timestamp: utcTimestamp(),
		SessionId: input.SessionId,
	}
}

// OperationLogEntry represents a single log entry for an operation
type OperationLogEntry struct {
	Timestamp string
	Level     string
	Step      string
	Message   string
	Details   json.RawMessage `json:",omitempty"`
	File      string          `json:",omitempty"`
	Line      int             `json:",omitempty"`
}

// OperationLogInput holds parameters for operation log broadcasts.
type OperationLogInput struct {
	OperationType string
	PluginId      int64
	SiteId        int64
	SessionId     string
	Entry         OperationLogEntry
}

// RemotePluginLogInput holds parameters for remote plugin log broadcasts.
type RemotePluginLogInput struct {
	SiteId    int64
	Action    string
	SessionId string
	Level     string
	Step      string
	Message   string
	Details   json.RawMessage
}
