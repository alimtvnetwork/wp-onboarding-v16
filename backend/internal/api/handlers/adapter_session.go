// Package handlers - Session and ErrorHistory service interfaces and adapters
package handlers

import (
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/errorhistory"
	"wp-plugin-publish/internal/services/session"
)

// SessionServiceInterface defines session service methods needed by handlers
type SessionServiceInterface interface {
	ListSessions(limit int) ([]*session.SessionSummary, error)
	GetSession(sessionID string) (*session.Session, error)
	GetSessionLogs(sessionID string) (string, error)
	GetSessionDiagnostics(sessionID string) (*session.SessionDiagnostics, error)
	DeleteSession(sessionID string) error
}

// ErrorHistoryServiceInterface defines error history service methods
type ErrorHistoryServiceInterface interface {
	Save(input models.ErrorHistoryInput) (*models.ErrorHistory, error)
	List(limit, offset int, filters models.ErrorHistoryFilters) ([]models.ErrorHistory, int, error)
	GetByID(id int64) (*models.ErrorHistory, error)
	GetByErrorID(errorID string) (*models.ErrorHistory, error)
	Delete(id int64) error
	Clear() (int64, error)
	BulkExport(ids []int64) (string, error)
	GetStats() (*models.ErrorHistoryStats, error)
}

// SessionServiceAdapter wraps *session.Service to implement SessionServiceInterface
type SessionServiceAdapter struct {
	*session.Service
}

func (a *SessionServiceAdapter) ListSessions(limit int) ([]*session.SessionSummary, error) {
	result := a.Service.ListSessions(limit)
	if result.HasError() {
		return nil, result.Error()
	}
	return result.Items(), nil
}

func (a *SessionServiceAdapter) GetSession(sessionID string) (*session.Session, error) {
	result := a.Service.GetSession(sessionID)
	if result.HasError() {
		return nil, result.Error()
	}
	return result.Value(), nil
}

func (a *SessionServiceAdapter) GetSessionLogs(sessionID string) (string, error) {
	result := a.Service.GetSessionLogs(sessionID)
	if result.HasError() {
		return "", result.Error()
	}
	return result.Value(), nil
}

func (a *SessionServiceAdapter) GetSessionDiagnostics(sessionID string) (*session.SessionDiagnostics, error) {
	result := a.Service.GetSessionDiagnostics(sessionID)
	if result.HasError() {
		return nil, result.Error()
	}
	v := result.Value()
	return &v, nil
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

func (a *ErrorHistoryServiceAdapter) GetStats() (*models.ErrorHistoryStats, error) {
	return a.Service.GetStats()
}
