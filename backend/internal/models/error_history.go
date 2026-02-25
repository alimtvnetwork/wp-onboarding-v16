// Package models - ErrorHistory model for persistent error storage
package models

import (
	"encoding/json"
	"time"
)

// ErrorHistory represents a captured error/notification for persistent storage
type ErrorHistory struct {
	Id                  int64
	ErrorId             string
	Code                string
	Level               string
	Message             string
	Details             string          `json:",omitempty"`
	ContextJSON         string          `json:"-"`
	Context             json.RawMessage `json:",omitempty"`
	StackTrace          string          `json:",omitempty"`
	Endpoint            string          `json:",omitempty"`
	Method              string          `json:",omitempty"`
	RequestBodyJSON     string          `json:"-"`
	RequestBody         json.RawMessage `json:",omitempty"`
	ResponseStatus      int             `json:",omitempty"`
	SessionId           string          `json:",omitempty"`
	SessionType         string          `json:",omitempty"`
	PhpStackFramesJson  string          `json:"-"`
	PhpStackFrames      []PhpStackFrame `json:",omitempty"`
	BackendLogsJSON     string          `json:"-"`
	BackendLogs         []string        `json:",omitempty"`
	BackendStackTrace   string          `json:",omitempty"`
	SiteUrl             string          `json:",omitempty"`
	TriggerComponent    string          `json:",omitempty"`
	TriggerAction       string          `json:",omitempty"`
	InvocationChainJSON string          `json:"-"`
	InvocationChain     []string        `json:",omitempty"`
	UIClickPath         string          `json:",omitempty"`
	MarkdownReport      string          `json:",omitempty"`
	CreatedAt           time.Time
}

// PhpStackFrame represents a single PHP stack trace frame
type PhpStackFrame struct {
	File     string `json:",omitempty"`
	FileBase string `json:",omitempty"`
	Line     int    `json:",omitempty"`
	Function string `json:",omitempty"`
	Class    string `json:",omitempty"`
}

// ErrorHistoryInput represents the input for creating an error history entry
type ErrorHistoryInput struct {
	ErrorId            string
	Code               string
	Level              string
	Message            string
	Details            string          `json:",omitempty"`
	Context            json.RawMessage `json:",omitempty"`
	StackTrace         string          `json:",omitempty"`
	Endpoint           string          `json:",omitempty"`
	Method             string          `json:",omitempty"`
	RequestBody        json.RawMessage `json:",omitempty"`
	ResponseStatus     int             `json:",omitempty"`
	SessionId          string          `json:",omitempty"`
	SessionType        string          `json:",omitempty"`
	PhpStackFrames     []PhpStackFrame `json:",omitempty"`
	BackendLogs        []string        `json:",omitempty"`
	BackendStackTrace  string          `json:",omitempty"`
	SiteUrl            string          `json:",omitempty"`
	TriggerComponent   string          `json:",omitempty"`
	TriggerAction      string          `json:",omitempty"`
	InvocationChain    []string        `json:",omitempty"`
	UIClickPath        string          `json:",omitempty"`
	MarkdownReport     string          `json:",omitempty"`
}

// ErrorHistoryStats contains aggregated error history statistics
type ErrorHistoryStats struct {
	Total   int
	ByLevel map[string]int
	ByCode  map[string]int
}

// ErrorHistoryFilters for querying error history
type ErrorHistoryFilters struct {
	Code      string `json:",omitempty"`
	Level     string `json:",omitempty"`
	StartDate string `json:",omitempty"`
	EndDate   string `json:",omitempty"`
	Search    string `json:",omitempty"`
}

// ParseJsonFields parses the JSON string fields into their structured counterparts
func (e *ErrorHistory) ParseJsonFields() {
	if e.ContextJSON != "" {
		e.Context = json.RawMessage(e.ContextJSON)
	}
	if e.RequestBodyJSON != "" {
		e.RequestBody = json.RawMessage(e.RequestBodyJSON)
	}
	if e.PhpStackFramesJson != "" {
		json.Unmarshal([]byte(e.PhpStackFramesJson), &e.PhpStackFrames)
	}
	if e.BackendLogsJSON != "" {
		json.Unmarshal([]byte(e.BackendLogsJSON), &e.BackendLogs)
	}
	if e.InvocationChainJSON != "" {
		json.Unmarshal([]byte(e.InvocationChainJSON), &e.InvocationChain)
	}
}
