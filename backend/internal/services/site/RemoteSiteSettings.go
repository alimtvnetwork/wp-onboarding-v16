// Remote site settings proxy methods
package site

import (
	"context"

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
func (s *Service) GetRemoteSiteHealthSummary(ctx context.Context, siteId int64) (*wordpress.HealthSummaryData, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	endpoint := wordpress.BuildNamespacedEndpoint(client.ResolveNamespace(), ep.SiteHealthSummary)

	result := wordpress.DoApiCall[wordpress.PhpEnvelope[wordpress.HealthSummaryData]](client, wordpress.ApiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: operationtype.GetSiteHealthSummary,
	})
	if result.HasError() {
		if isRemote404(result.AppError()) {
			return wordpress.BuildOutdatedHealthSummary(), nil
		}
		return nil, result.AppError()
	}

	data, unwrapErr := wordpress.UnwrapPhpResult(result.Value())
	if unwrapErr != nil {
		return nil, unwrapErr
	}

	return &data, nil
}
