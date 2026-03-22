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
// Mirrors the PowerShell -pas pattern: probes all known namespaces' /status endpoints
// AND the rich /site-health-summary endpoint in parallel — no sequential ResolveNamespace() overhead.
func (s *Service) GetRemoteSiteHealthSummary(ctx context.Context, siteId int64) (*wordpress.HealthSummaryData, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	// All known namespaces to probe (same as PowerShell -pas probes all configured plugins)
	namespaces := []string{
		wordpress.QUploadNamespace,
		wordpress.RiseupAsiaNamespace,
	}

	var wg sync.WaitGroup

	// Channel 1: try rich /site-health-summary on each namespace in parallel
	type healthProbeResult struct {
		data *wordpress.HealthSummaryData
	}
	healthCh := make(chan healthProbeResult, len(namespaces))

	for _, ns := range namespaces {
		wg.Add(1)
		go func(namespace string) {
			defer wg.Done()
			endpoint := wordpress.BuildNamespacedEndpoint(namespace, ep.SiteHealthSummary)
			result := wordpress.DoApiCall[wordpress.PhpEnvelope[wordpress.HealthSummaryData]](client, wordpress.ApiCallInput{
				Method:    httpmethod.Get,
				Endpoint:  endpoint,
				Operation: operationtype.GetSiteHealthSummary,
			})
			if result.HasError() {
				return
			}
			data, unwrapErr := wordpress.UnwrapPhpResult(result.Value())
			if unwrapErr != nil {
				return
			}
			healthCh <- healthProbeResult{data: &data}
		}(ns)
	}

	// Channel 2: try /status on each namespace in parallel (same as PowerShell -pas)
	type statusProbeResult struct {
		data *wordpress.HealthSummaryData
	}
	statusCh := make(chan statusProbeResult, len(namespaces))

	for _, ns := range namespaces {
		wg.Add(1)
		go func(namespace string) {
			defer wg.Done()
			metaResult := client.GetStatusMetadataByNamespace(namespace)
			if metaResult.HasError() {
				return
			}
			statusCh <- statusProbeResult{data: wordpress.BuildHealthSummaryFromStatus(metaResult.Value())}
		}(ns)
	}

	wg.Wait()
	close(healthCh)
	close(statusCh)

	// Prefer the richer /site-health-summary if any namespace returned it
	for probe := range healthCh {
		if probe.data != nil {
			return probe.data, nil
		}
	}

	// Fall back to /status data (same data as PowerShell -pas displays)
	for probe := range statusCh {
		if probe.data != nil {
			return probe.data, nil
		}
	}

	// All probes failed on all namespaces
	return wordpress.BuildOutdatedHealthSummary(), nil
}
