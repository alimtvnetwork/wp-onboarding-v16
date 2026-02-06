// Package handlers - Session and ErrorHistory service adapters
package handlers

import (
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/errorhistory"
	"wp-plugin-publish/internal/services/session"
)

// SessionServiceAdapter wraps *session.Service to implement SessionServiceInterface
type SessionServiceAdapter struct {
	*session.Service
}

func (a *SessionServiceAdapter) ListSessions(limit int) (interface{}, error) {
	return a.Service.ListSessions(limit)
}

func (a *SessionServiceAdapter) GetSession(sessionID string) (interface{}, error) {
	return a.Service.GetSession(sessionID)
}

func (a *SessionServiceAdapter) GetSessionLogs(sessionID string) (string, error) {
	return a.Service.GetSessionLogs(sessionID)
}

func (a *SessionServiceAdapter) DeleteSession(sessionID string) error {
	return a.Service.DeleteSession(sessionID)
}

// ErrorHistoryServiceAdapter wraps *errorhistory.Service to implement ErrorHistoryServiceInterface
type ErrorHistoryServiceAdapter struct {
	*errorhistory.Service
}

func (a *ErrorHistoryServiceAdapter) Save(input models.ErrorHistoryInput) (*models.ErrorHistory, error) {
	return a.Service.Save(input)
}

func (a *ErrorHistoryServiceAdapter) List(limit, offset int, filters models.ErrorHistoryFilters) ([]models.ErrorHistory, int, error) {
	return a.Service.List(limit, offset, filters)
}

func (a *ErrorHistoryServiceAdapter) GetByID(id int64) (*models.ErrorHistory, error) {
	return a.Service.GetByID(id)
}

func (a *ErrorHistoryServiceAdapter) GetByErrorID(errorID string) (*models.ErrorHistory, error) {
	return a.Service.GetByErrorID(errorID)
}

func (a *ErrorHistoryServiceAdapter) Delete(id int64) error {
	return a.Service.Delete(id)
}

func (a *ErrorHistoryServiceAdapter) Clear() (int64, error) {
	return a.Service.Clear()
}

func (a *ErrorHistoryServiceAdapter) BulkExport(ids []int64) (string, error) {
	return a.Service.BulkExport(ids)
}

func (a *ErrorHistoryServiceAdapter) GetStats() (map[string]interface{}, error) {
	return a.Service.GetStats()
}
