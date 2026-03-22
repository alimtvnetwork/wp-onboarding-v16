// Remote log management proxy methods for site service
package site

import (
	"context"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// GetRemoteLogsStatus fetches log file metadata from a remote WordPress site.
func (s *Service) GetRemoteLogsStatus(ctx context.Context, siteId int64) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	result := wordpress.DoApiCall[map[string]any](client, wordpress.ApiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  ep.LogsStatus.String(),
		Operation: operationtype.GetLogsStatus,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return wordpress.UnwrapPhpEnvelope(result.Value()), nil
}

// GetRemoteLogsRotationStatus fetches log rotation config from a remote WordPress site.
func (s *Service) GetRemoteLogsRotationStatus(ctx context.Context, siteId int64) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	result := wordpress.DoApiCall[map[string]any](client, wordpress.ApiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  ep.LogsRotationStatus.String(),
		Operation: operationtype.GetLogsRotationStatus,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return wordpress.UnwrapPhpEnvelope(result.Value()), nil
}

// RequestRemoteLogsClear initiates Step 1 of the two-step clearing flow.
func (s *Service) RequestRemoteLogsClear(ctx context.Context, siteId int64) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	result := wordpress.DoApiCall[map[string]any](client, wordpress.ApiCallInput{
		Method:    httpmethod.Delete,
		Endpoint:  ep.LogsClear.String(),
		Operation: operationtype.RequestLogsClear,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return wordpress.UnwrapPhpEnvelope(result.Value()), nil
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

	result := wordpress.DoApiCall[map[string]any](client, wordpress.ApiCallInput{
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

	result := wordpress.DoApiCall[map[string]any](client, wordpress.ApiCallInput{
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

// ClearAllPluginLogsResult holds the combined result of clearing logs for both plugins.
type ClearAllPluginLogsResult struct {
	Riseup  *pluginClearResult `json:"riseup"`
	QUpload *pluginClearResult `json:"qupload"`
}

type pluginClearResult struct {
	Cleared bool   `json:"cleared"`
	Error   string `json:"error,omitempty"`
}

// ClearAllRemoteLogs clears logs for both WP plugins (riseup-asia + qupload) on a site.
// Performs the two-step clear (request token → confirm) for each namespace.
func (s *Service) ClearAllRemoteLogs(ctx context.Context, siteId int64) (*ClearAllPluginLogsResult, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	result := &ClearAllPluginLogsResult{
		Riseup:  clearLogsForNamespace(client, wordpress.RiseupAsiaNamespace),
		QUpload: clearLogsForNamespace(client, wordpress.QUploadNamespace),
	}

	return result, nil
}

// clearLogsForNamespace performs the two-step log clear for a single namespace.
func clearLogsForNamespace(client *wordpress.Client, namespace string) *pluginClearResult {
	clearEndpoint := "/" + namespace + ep.LogsClear.String()
	confirmEndpoint := "/" + namespace + ep.LogsConfirm.String()

	// Step 1: Request token
	tokenResult := wordpress.DoApiCall[map[string]any](client, wordpress.ApiCallInput{
		Method:    httpmethod.Delete,
		Endpoint:  clearEndpoint,
		Operation: operationtype.RequestLogsClear,
	})
	if tokenResult.HasError() {
		return &pluginClearResult{Cleared: false, Error: tokenResult.AppError().Error()}
	}

	tokenData := tokenResult.Value()
	token, ok := tokenData["token"].(string)
	if !ok || token == "" {
		return &pluginClearResult{Cleared: false, Error: "no token returned from clear request"}
	}

	// Step 2: Confirm with token
	confirmResult := wordpress.DoApiCall[map[string]any](client, wordpress.ApiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  confirmEndpoint,
		Body:      wordpress.ClearTokenRequest{Token: token},
		Operation: operationtype.ConfirmLogsClear,
	})
	if confirmResult.HasError() {
		return &pluginClearResult{Cleared: false, Error: confirmResult.AppError().Error()}
	}

	return &pluginClearResult{Cleared: true}
}
