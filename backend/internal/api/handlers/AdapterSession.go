// Package handlers - Session and ErrorHistory service interfaces and adapters
package handlers

import (
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/error_history"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/pkg/apperror"
)

// SessionServiceInterface defines session service methods needed by handlers.
// All methods return *apperror.AppError — never raw error.
type SessionServiceInterface interface {
	ListSessions(limit int) ([]*session.SessionSummary, *apperror.AppError)
	GetSession(sessionId string) (*session.Session, *apperror.AppError)
	GetSessionLogs(sessionId string) (string, *apperror.AppError)
	GetSessionDiagnostics(sessionId string) (*session.SessionDiagnostics, *apperror.AppError)
	DeleteSession(sessionId string) *apperror.AppError
}

// ErrorHistoryServiceInterface defines error history service methods.
// All methods return *apperror.AppError — never raw error.
type ErrorHistoryServiceInterface interface {
	Save(input models.ErrorHistoryInput) (*models.ErrorHistory, *apperror.AppError)
	List(limit, offset int, filters models.ErrorHistoryFilters) (*ErrorHistoryListResult, *apperror.AppError)
	GetById(id int64) (*models.ErrorHistory, *apperror.AppError)
	GetByErrorId(errorId string) (*models.ErrorHistory, *apperror.AppError)
	Delete(id int64) *apperror.AppError
	Clear() (int64, *apperror.AppError)
	BulkExport(ids []int64) (string, *apperror.AppError)
	GetStats() (*models.ErrorHistoryStats, *apperror.AppError)
}

// ErrorHistoryListResult holds paginated error history results.
type ErrorHistoryListResult struct {
	Items []models.ErrorHistory
	Total int
}

// SessionServiceAdapter wraps *session.Service to implement SessionServiceInterface
type SessionServiceAdapter struct {
	*session.Service
}

func (a *SessionServiceAdapter) ListSessions(limit int) ([]*session.SessionSummary, *apperror.AppError) {
	result := a.Service.ListSessions(limit)
	if result.HasError() {
		return nil, result.AppError()
	}
	return result.Items(), nil
}

func (a *SessionServiceAdapter) GetSession(sessionId string) (*session.Session, *apperror.AppError) {
	result := a.Service.GetSession(sessionId)
	if result.HasError() {
		return nil, result.AppError()
	}
	return result.Value(), nil
}

func (a *SessionServiceAdapter) GetSessionLogs(sessionId string) (string, *apperror.AppError) {
	result := a.Service.GetSessionLogs(sessionId)
	if result.HasError() {
		return "", result.AppError()
	}
	return result.Value(), nil
}

func (a *SessionServiceAdapter) GetSessionDiagnostics(sessionId string) (*session.SessionDiagnostics, *apperror.AppError) {
	result := a.Service.GetSessionDiagnostics(sessionId)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *SessionServiceAdapter) DeleteSession(sessionId string) *apperror.AppError {
	return a.Service.DeleteSession(sessionId)
}

// ErrorHistoryServiceAdapter wraps *errorhistory.Service to implement ErrorHistoryServiceInterface
type ErrorHistoryServiceAdapter struct {
	*errorhistory.Service
}

func (a *ErrorHistoryServiceAdapter) Save(input models.ErrorHistoryInput) (*models.ErrorHistory, *apperror.AppError) {
	result := a.Service.Save(input)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *ErrorHistoryServiceAdapter) List(limit, offset int, filters models.ErrorHistoryFilters) (*ErrorHistoryListResult, *apperror.AppError) {
	result := a.Service.List(limit, offset, filters)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &ErrorHistoryListResult{Items: v.Items, Total: v.Total}, nil
}

func (a *ErrorHistoryServiceAdapter) GetById(id int64) (*models.ErrorHistory, *apperror.AppError) {
	result := a.Service.GetById(id)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *ErrorHistoryServiceAdapter) GetByErrorId(errorId string) (*models.ErrorHistory, *apperror.AppError) {
	result := a.Service.GetByErrorId(errorId)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *ErrorHistoryServiceAdapter) Delete(id int64) *apperror.AppError {
	return a.Service.Delete(id)
}

func (a *ErrorHistoryServiceAdapter) Clear() (int64, *apperror.AppError) {
	result := a.Service.Clear()
	if result.HasError() {
		return 0, result.AppError()
	}
	return result.Value(), nil
}

func (a *ErrorHistoryServiceAdapter) BulkExport(ids []int64) (string, *apperror.AppError) {
	result := a.Service.BulkExport(ids)
	if result.HasError() {
		return "", result.AppError()
	}
	return result.Value(), nil
}

func (a *ErrorHistoryServiceAdapter) GetStats() (*models.ErrorHistoryStats, *apperror.AppError) {
	result := a.Service.GetStats()
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}
