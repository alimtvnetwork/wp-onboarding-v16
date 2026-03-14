// Remote log management proxy methods for site service
package site

import (
	"context"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/pkg/apperror"
)

// GetRemoteLogsStatus fetches log file metadata from a remote WordPress site.
func (s *Service) GetRemoteLogsStatus(ctx context.Context, siteId int64) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	result := doApiCall[map[string]any](client, apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  ep.LogsStatus.String(),
		Operation: operationtype.GetLogsStatus,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}

// RequestRemoteLogsClear initiates Step 1 of the two-step clearing flow.
func (s *Service) RequestRemoteLogsClear(ctx context.Context, siteId int64) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	result := doApiCall[map[string]any](client, apiCallInput{
		Method:    httpmethod.Delete,
		Endpoint:  ep.LogsClear.String(),
		Operation: operationtype.RequestLogsClear,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}

// ConfirmRemoteLogsClear executes Step 2 with the provided token.
func (s *Service) ConfirmRemoteLogsClear(ctx context.Context, siteId int64, token string) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	body := wordpress.ClearTokenRequest{
		Token: token,
	}

	result := doApiCall[map[string]any](client, apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  ep.LogsConfirm.String(),
		Body:      body,
		Operation: operationtype.ConfirmLogsClear,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}

// EmailRemoteLogs proxies the email logs request to the WordPress site.
func (s *Service) EmailRemoteLogs(ctx context.Context, siteId int64, body wordpress.EmailLogsRequest) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	result := doApiCall[map[string]any](client, apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  ep.LogsEmail.String(),
		Body:      body,
		Operation: operationtype.EmailLogs,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}
