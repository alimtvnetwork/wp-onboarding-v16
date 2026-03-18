// Remote user app password proxy methods for site service
package site

import (
	"context"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// CreateRemoteAppPassword creates an app password for a user on a remote WordPress site.
func (s *Service) CreateRemoteAppPassword(ctx context.Context, siteId int64, input wordpress.AppPasswordCreateRequest) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	result := doApiCall[wordpress.AppPasswordCreateResult](client, apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  ep.UserAppPassword.String(),
		Operation: operationtype.CreateAppPassword,
		Body:      input,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}

// RevokeRemoteAppPassword revokes an app password on a remote WordPress site.
func (s *Service) RevokeRemoteAppPassword(ctx context.Context, siteId int64, input wordpress.AppPasswordRevokeRequest) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	result := doApiCall[map[string]any](client, apiCallInput{
		Method:    httpmethod.Delete,
		Endpoint:  ep.UserAppPassword.String(),
		Operation: operationtype.RevokeAppPassword,
		Body:      input,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}

// ExportRemoteUsersCsv exports users as CSV from a remote WordPress site.
func (s *Service) ExportRemoteUsersCsv(ctx context.Context, siteId int64, query string) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	endpoint := ep.UsersExport.String()
	hasQuery := query != ""

	if hasQuery {
		endpoint += "?" + query
	}

	result := doApiCall[any](client, apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: operationtype.ExportUsersCsv,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}

// ExportRemoteUsersSqlite exports users as SQLite ZIP from a remote WordPress site.
func (s *Service) ExportRemoteUsersSqlite(ctx context.Context, siteId int64) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	result := doApiCall[any](client, apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  ep.UsersExportSqlite.String(),
		Operation: operationtype.ExportUsersSqlite,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}
