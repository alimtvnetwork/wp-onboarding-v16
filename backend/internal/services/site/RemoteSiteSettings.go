// Remote site settings proxy methods
package site

import (
	"context"
	"sync"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// GetRemoteSiteSettings fetches site settings from a remote WordPress site.
func (s *Service) GetRemoteSiteSettings(ctx context.Context, siteId int64) (*wordpress.SiteSettingsData, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	endpoint := wordpress.BuildNamespacedEndpoint(client.ResolveNamespace(), ep.SiteSettings)

	result := wordpress.DoApiCall[wordpress.PhpEnvelope[wordpress.SiteSettingsData]](client, wordpress.ApiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: operationtype.GetSiteSettings,
	})
	if result.HasError() {
		if isRemote404(result.AppError()) {
			return wordpress.BuildOutdatedSiteSettings(), nil
		}
		return nil, result.AppError()
	}

	data, unwrapErr := wordpress.UnwrapPhpResult(result.Value())
	if unwrapErr != nil {
		return nil, unwrapErr
	}

	return &data, nil
}

// UpdateRemoteSiteSettings updates site settings on a remote WordPress site.
func (s *Service) UpdateRemoteSiteSettings(ctx context.Context, siteId int64, body map[string]any) (*wordpress.SiteSettingsUpdateResult, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	endpoint := wordpress.BuildNamespacedEndpoint(client.ResolveNamespace(), ep.SiteSettings)

	result := wordpress.DoApiCall[wordpress.PhpEnvelope[wordpress.SiteSettingsUpdateResult]](client, wordpress.ApiCallInput{
		Method:    httpmethod.Put,
		Endpoint:  endpoint,
		Body:      body,
		Operation: operationtype.UpdateSiteSettings,
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

// GetRemoteSiteHealthSummary fetches health summary from a remote WordPress site.
// Tries /site-health-summary and /status in parallel — uses the richer result if available.
func (s *Service) GetRemoteSiteHealthSummary(ctx context.Context, siteId int64) (*wordpress.HealthSummaryData, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	namespace := client.ResolveNamespace()

	var wg sync.WaitGroup
	wg.Add(2)

	// Channel 1: try dedicated /site-health-summary endpoint
	var healthResult *wordpress.HealthSummaryData
	var healthErr *apperror.AppError
	go func() {
		defer wg.Done()
		endpoint := wordpress.BuildNamespacedEndpoint(namespace, ep.SiteHealthSummary)
		result := wordpress.DoApiCall[wordpress.PhpEnvelope[wordpress.HealthSummaryData]](client, wordpress.ApiCallInput{
			Method:    httpmethod.Get,
			Endpoint:  endpoint,
			Operation: operationtype.GetSiteHealthSummary,
		})
		if result.HasError() {
			healthErr = result.AppError()
			return
		}
		data, unwrapErr := wordpress.UnwrapPhpResult(result.Value())
		if unwrapErr != nil {
			healthErr = unwrapErr
			return
		}
		healthResult = &data
	}()

	// Channel 2: try /status endpoint (same as PowerShell -pas)
	var statusResult *wordpress.HealthSummaryData
	go func() {
		defer wg.Done()
		metaResult := client.GetStatusMetadataByNamespace(namespace)
		if metaResult.HasError() {
			return
		}
		statusResult = wordpress.BuildHealthSummaryFromStatus(metaResult.Value())
	}()

	wg.Wait()

	// Prefer the richer /site-health-summary if it succeeded
	if healthResult != nil {
		return healthResult, nil
	}

	// Fall back to /status data
	if statusResult != nil {
		return statusResult, nil
	}

	// Both failed
	if healthErr != nil {
		return nil, healthErr
	}

	return wordpress.BuildOutdatedHealthSummary(), nil
}
