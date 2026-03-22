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
// Probes all known namespaces in parallel — no sequential ResolveNamespace() overhead.
func (s *Service) GetRemoteSiteSettings(ctx context.Context, siteId int64) (*wordpress.SiteSettingsData, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	type probeResult struct {
		data *wordpress.SiteSettingsData
	}
	ch := make(chan probeResult, len(allNamespaces))
	var wg sync.WaitGroup

	for _, ns := range allNamespaces {
		wg.Add(1)
		go func(namespace string) {
			defer wg.Done()
			endpoint := wordpress.BuildNamespacedEndpoint(namespace, ep.SiteSettings)
			result := wordpress.DoApiCall[wordpress.PhpEnvelope[wordpress.SiteSettingsData]](client, wordpress.ApiCallInput{
				Method:    httpmethod.Get,
				Endpoint:  endpoint,
				Operation: operationtype.GetSiteSettings,
			})
			if result.HasError() {
				return
			}
			data, unwrapErr := wordpress.UnwrapPhpResult(result.Value())
			if unwrapErr != nil {
				return
			}
			ch <- probeResult{data: &data}
		}(ns)
	}

	wg.Wait()
	close(ch)

	for probe := range ch {
		if probe.data != nil {
			return probe.data, nil
		}
	}

	return wordpress.BuildOutdatedSiteSettings(), nil
}

// UpdateRemoteSiteSettings updates site settings on a remote WordPress site.
// Probes all known namespaces in parallel — no sequential ResolveNamespace() overhead.
func (s *Service) UpdateRemoteSiteSettings(ctx context.Context, siteId int64, body map[string]any) (*wordpress.SiteSettingsUpdateResult, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	type probeResult struct {
		data   *wordpress.SiteSettingsUpdateResult
		appErr *apperror.AppError
	}
	ch := make(chan probeResult, len(allNamespaces))
	var wg sync.WaitGroup

	for _, ns := range allNamespaces {
		wg.Add(1)
		go func(namespace string) {
			defer wg.Done()
			endpoint := wordpress.BuildNamespacedEndpoint(namespace, ep.SiteSettings)
			result := wordpress.DoApiCall[wordpress.PhpEnvelope[wordpress.SiteSettingsUpdateResult]](client, wordpress.ApiCallInput{
				Method:    httpmethod.Put,
				Endpoint:  endpoint,
				Body:      body,
				Operation: operationtype.UpdateSiteSettings,
			})
			if result.HasError() {
				return
			}
			data, unwrapErr := wordpress.UnwrapPhpResult(result.Value())
			if unwrapErr != nil {
				return
			}
			ch <- probeResult{data: &data}
		}(ns)
	}

	wg.Wait()
	close(ch)

	for probe := range ch {
		if probe.data != nil {
			return probe.data, nil
		}
	}

	return nil, apperror.New(apperror.ErrWPConnection, "site settings update failed on all namespaces")
}

// GetRemoteSiteHealthSummary fetches health summary from a remote WordPress site.
// Mirrors the PowerShell -pas pattern: probes all known namespaces' /status endpoints
// AND the rich /site-health-summary endpoint in parallel — no sequential ResolveNamespace() overhead.
func (s *Service) GetRemoteSiteHealthSummary(ctx context.Context, siteId int64) (*wordpress.HealthSummaryData, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	var wg sync.WaitGroup

	// Channel 1: try rich /site-health-summary on each namespace in parallel
	type healthProbeResult struct {
		data *wordpress.HealthSummaryData
	}
	healthCh := make(chan healthProbeResult, len(allNamespaces))

	for _, ns := range allNamespaces {
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
	statusCh := make(chan statusProbeResult, len(allNamespaces))

	for _, ns := range allNamespaces {
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
