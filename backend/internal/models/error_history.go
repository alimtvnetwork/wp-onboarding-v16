// Package models - ErrorHistory model for persistent error storage
package models

import (
	"encoding/json"
	"time"
)

// ErrorHistory represents a captured error/notification for persistent storage
type ErrorHistory struct {
	ID                  int64           `json:"id"`
	ErrorID             string          `json:"errorId"`
	Code                string          `json:"code"`
	Level               string          `json:"level"`
	Message             string          `json:"message"`
	Details             string          `json:"details,omitempty"`
	ContextJSON         string          `json:"-"`
	Context             json.RawMessage `json:"context,omitempty"`
	StackTrace          string          `json:"stackTrace,omitempty"`
	Endpoint            string          `json:"endpoint,omitempty"`
	Method              string          `json:"method,omitempty"`
	RequestBodyJSON     string          `json:"-"`
	RequestBody         json.RawMessage `json:"requestBody,omitempty"`
	ResponseStatus      int             `json:"responseStatus,omitempty"`
	SessionID           string          `json:"sessionId,omitempty"`
	SessionType         string          `json:"sessionType,omitempty"`
	PHPStackFramesJSON  string          `json:"-"`
	PHPStackFrames      []PHPStackFrame `json:"phpStackFrames,omitempty"`
	BackendLogsJSON     string          `json:"-"`
	BackendLogs         []string        `json:"backendLogs,omitempty"`
	BackendStackTrace   string          `json:"backendStackTrace,omitempty"`
	SiteURL             string          `json:"siteUrl,omitempty"`
	TriggerComponent    string          `json:"triggerComponent,omitempty"`
	TriggerAction       string          `json:"triggerAction,omitempty"`
	InvocationChainJSON string          `json:"-"`
	InvocationChain     []string        `json:"invocationChain,omitempty"`
	UIClickPath         string          `json:"uiClickPath,omitempty"`
	MarkdownReport      string          `json:"markdownReport,omitempty"`
	CreatedAt           time.Time       `json:"createdAt"`
}

// PHPStackFrame represents a single PHP stack trace frame
type PHPStackFrame struct {
	File     string `json:"file,omitempty"`
	FileBase string `json:"fileBase,omitempty"`
	Line     int    `json:"line,omitempty"`
	Function string `json:"function,omitempty"`
	Class    string `json:"class,omitempty"`
}

// ErrorHistoryInput represents the input for creating an error history entry
type ErrorHistoryInput struct {
	ErrorID            string          `json:"errorId"`
	Code               string          `json:"code"`
	Level              string          `json:"level"`
	Message            string          `json:"message"`
	Details            string          `json:"details,omitempty"`
	Context            json.RawMessage `json:"context,omitempty"`
	StackTrace         string          `json:"stackTrace,omitempty"`
	Endpoint           string          `json:"endpoint,omitempty"`
	Method             string          `json:"method,omitempty"`
	RequestBody        json.RawMessage `json:"requestBody,omitempty"`
	ResponseStatus     int             `json:"responseStatus,omitempty"`
	SessionID          string          `json:"sessionId,omitempty"`
	SessionType        string          `json:"sessionType,omitempty"`
	PHPStackFrames     []PHPStackFrame `json:"phpStackFrames,omitempty"`
	BackendLogs        []string        `json:"backendLogs,omitempty"`
	BackendStackTrace  string          `json:"backendStackTrace,omitempty"`
	SiteURL            string          `json:"siteUrl,omitempty"`
	TriggerComponent   string          `json:"triggerComponent,omitempty"`
	TriggerAction      string          `json:"triggerAction,omitempty"`
	InvocationChain    []string        `json:"invocationChain,omitempty"`
	UIClickPath        string          `json:"uiClickPath,omitempty"`
	MarkdownReport     string          `json:"markdownReport,omitempty"`
}

// ErrorHistoryFilters for querying error history
type ErrorHistoryFilters struct {
	Code      string `json:"code,omitempty"`
	Level     string `json:"level,omitempty"`
	StartDate string `json:"startDate,omitempty"`
	EndDate   string `json:"endDate,omitempty"`
	Search    string `json:"search,omitempty"`
}

// ParseJSONFields parses the JSON string fields into their structured counterparts
func (e *ErrorHistory) ParseJSONFields() {
	if e.ContextJSON != "" {
		e.Context = json.RawMessage(e.ContextJSON)
	}
	if e.RequestBodyJSON != "" {
		e.RequestBody = json.RawMessage(e.RequestBodyJSON)
	}
	if e.PHPStackFramesJSON != "" {
		json.Unmarshal([]byte(e.PHPStackFramesJSON), &e.PHPStackFrames)
	}
	if e.BackendLogsJSON != "" {
		json.Unmarshal([]byte(e.BackendLogsJSON), &e.BackendLogs)
	}
	if e.InvocationChainJSON != "" {
		json.Unmarshal([]byte(e.InvocationChainJSON), &e.InvocationChain)
	}
}
