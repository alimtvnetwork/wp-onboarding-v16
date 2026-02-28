// Package ws — broadcast convenience methods.
package ws

import "encoding/json"

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
