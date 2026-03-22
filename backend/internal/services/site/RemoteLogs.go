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
func (s *Service) GetRemoteLogsStatus(ctx context.Context, siteId int64) (*wordpress.LogsStatusData, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	endpoint := wordpress.BuildNamespacedEndpoint(client.ResolveNamespace(), ep.LogsStatus)

	result := wordpress.DoApiCall[wordpress.PhpEnvelope[wordpress.LogsStatusData]](client, wordpress.ApiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: operationtype.GetLogsStatus,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	data, unwrapErr := wordpress.UnwrapPhpResult(result.Value())
	if unwrapErr != nil {
		return nil, unwrapErr
	}

	return &data, nil
}

// GetRemoteLogsRotationStatus fetches log rotation config from a remote WordPress site.
func (s *Service) GetRemoteLogsRotationStatus(ctx context.Context, siteId int64) (*wordpress.LogsRotationStatusData, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	endpoint := wordpress.BuildNamespacedEndpoint(client.ResolveNamespace(), ep.LogsRotationStatus)

	result := wordpress.DoApiCall[wordpress.PhpEnvelope[wordpress.LogsRotationStatusData]](client, wordpress.ApiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: operationtype.GetLogsRotationStatus,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	data, unwrapErr := wordpress.UnwrapPhpResult(result.Value())
	if unwrapErr != nil {
		return nil, unwrapErr
	}

	return &data, nil
}

// RequestRemoteLogsClear initiates Step 1 of the two-step clearing flow.
func (s *Service) RequestRemoteLogsClear(ctx context.Context, siteId int64) (*wordpress.LogsClearRequestData, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	endpoint := wordpress.BuildNamespacedEndpoint(client.ResolveNamespace(), ep.LogsClear)

	result := wordpress.DoApiCall[wordpress.PhpEnvelope[wordpress.LogsClearRequestData]](client, wordpress.ApiCallInput{
		Method:    httpmethod.Delete,
		Endpoint:  endpoint,
		Operation: operationtype.RequestLogsClear,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	data, unwrapErr := wordpress.UnwrapPhpResult(result.Value())
	if unwrapErr != nil {
		return nil, unwrapErr
	}

	return &data, nil
}

// ConfirmRemoteLogsClear executes Step 2 with the provided token.
func (s *Service) ConfirmRemoteLogsClear(ctx context.Context, siteId int64, token string) (*wordpress.LogsClearConfirmData, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	endpoint := wordpress.BuildNamespacedEndpoint(client.ResolveNamespace(), ep.LogsConfirm)

	body := wordpress.ClearTokenRequest{
		Token: token,
	}

	result := wordpress.DoApiCall[wordpress.PhpEnvelope[wordpress.LogsClearConfirmData]](client, wordpress.ApiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  endpoint,
		Body:      body,
		Operation: operationtype.ConfirmLogsClear,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	data, unwrapErr := wordpress.UnwrapPhpResult(result.Value())
	if unwrapErr != nil {
		return nil, unwrapErr
	}

	return &data, nil
}

// EmailRemoteLogs proxies the email logs request to the WordPress site.
func (s *Service) EmailRemoteLogs(ctx context.Context, siteId int64, body wordpress.EmailLogsRequest) (*wordpress.LogsEmailResultData, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	endpoint := wordpress.BuildNamespacedEndpoint(client.ResolveNamespace(), ep.LogsEmail)

	result := wordpress.DoApiCall[wordpress.PhpEnvelope[wordpress.LogsEmailResultData]](client, wordpress.ApiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  endpoint,
		Body:      body,
		Operation: operationtype.EmailLogs,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	data, unwrapErr := wordpress.UnwrapPhpResult(result.Value())
	if unwrapErr != nil {
		return nil, unwrapErr
	}

	return &data, nil
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

// logsClearTokenResponse is the typed response from the PHP clear-logs Step 1 endpoint.
type logsClearTokenResponse struct {
	Token string `json:"token"`
}

// logsClearConfirmResponse is the typed response from the PHP clear-logs Step 2 endpoint.
type logsClearConfirmResponse struct {
	Cleared bool `json:"cleared"`
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

	// Step 1: Request token — typed response
	tokenResult := wordpress.DoApiCall[logsClearTokenResponse](client, wordpress.ApiCallInput{
		Method:    httpmethod.Delete,
		Endpoint:  clearEndpoint,
		Operation: operationtype.RequestLogsClear,
	})
	if tokenResult.HasError() {
		return &pluginClearResult{Cleared: false, Error: tokenResult.AppError().Error()}
	}

	tokenData := tokenResult.Value()
	if tokenData.Token == "" {
		return &pluginClearResult{Cleared: false, Error: "no token returned from clear request"}
	}

	// Step 2: Confirm with token — typed response
	confirmResult := wordpress.DoApiCall[logsClearConfirmResponse](client, wordpress.ApiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  confirmEndpoint,
		Body:      wordpress.ClearTokenRequest{Token: tokenData.Token},
		Operation: operationtype.ConfirmLogsClear,
	})
	if confirmResult.HasError() {
		return &pluginClearResult{Cleared: false, Error: confirmResult.AppError().Error()}
	}

	return &pluginClearResult{Cleared: true}
}
