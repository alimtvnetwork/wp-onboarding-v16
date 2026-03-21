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
func (s *Service) GetRemoteSiteSettings(ctx context.Context, siteId int64) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	result := wordpress.DoApiCall[map[string]any](client, wordpress.ApiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  ep.SiteSettings.String(),
		Operation: operationtype.GetSiteSettings,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}

// UpdateRemoteSiteSettings updates site settings on a remote WordPress site.
func (s *Service) UpdateRemoteSiteSettings(ctx context.Context, siteId int64, body map[string]any) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	result := wordpress.DoApiCall[map[string]any](client, wordpress.ApiCallInput{
		Method:    httpmethod.Put,
		Endpoint:  ep.SiteSettings.String(),
		Body:      body,
		Operation: operationtype.UpdateSiteSettings,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}

// GetRemoteSiteHealthSummary fetches health summary from a remote WordPress site.
func (s *Service) GetRemoteSiteHealthSummary(ctx context.Context, siteId int64) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	result := wordpress.DoApiCall[map[string]any](client, wordpress.ApiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  ep.SiteHealthSummary.String(),
		Operation: operationtype.GetSiteHealthSummary,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}
