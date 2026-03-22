package site

import (
	"context"
	"fmt"
	"sync"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// PreflightSiteResult is the pre-flight result for a single site.
type PreflightSiteResult struct {
	SiteId              int64
	SiteName            string
	SiteUrl             string
	IsReachable         bool
	RiseupAsiaAvailable bool
	RiseupAsiaNamespace string
	QUploadAvailable    bool
	QUploadNamespace    string
	Error               string
}

// DeployPreflight checks endpoint availability on all requested sites in parallel.
func (s *Service) DeployPreflight(ctx context.Context, siteIds []int64) ([]PreflightSiteResult, *apperror.AppError) {
	results := make([]PreflightSiteResult, len(siteIds))
	var wg sync.WaitGroup

	for i, siteId := range siteIds {
		wg.Add(1)

		go func(idx int, id int64) {
			defer wg.Done()
			results[idx] = s.preflightSingleSite(ctx, id)
		}(i, siteId)
	}

	wg.Wait()

	return results, nil
}

// preflightSingleSite checks both plugin endpoints on a single site.
func (s *Service) preflightSingleSite(ctx context.Context, siteId int64) PreflightSiteResult {
	result := s.GetById(ctx, siteId)
	if result.HasError() {
		return PreflightSiteResult{
			SiteId: siteId,
			Error:  fmt.Sprintf("Site not found: %v", result.AppError()),
		}
	}
	site := result.Value()

	client, clientErr := s.buildPreflightClient(site)
	if clientErr != nil {
		return buildUnreachablePreflight(siteId, site, clientErr.Error())
	}

	return s.checkSiteEndpoints(siteId, site, client)
}

// buildPreflightClient creates a WordPress client for pre-flight checks.
func (s *Service) buildPreflightClient(site models.Site) (*wordpress.Client, *apperror.AppError) {
	decrypted, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt site password")
	}

	client := s.wpClientFactory(site.Url, site.Username, string(decrypted), nil)

	return client, nil
}

// checkSiteEndpoints probes both Riseup Asia and QUpload endpoints.
func (s *Service) checkSiteEndpoints(siteId int64, site models.Site, client *wordpress.Client) PreflightSiteResult {
	preflight := PreflightSiteResult{
		SiteId:      siteId,
		SiteName:    site.Name,
		SiteUrl:     site.Url,
		IsReachable: true,
	}

	riseupResult := client.CheckRiseupAsiaAvailable()
	if !riseupResult.HasError() && riseupResult.Value().IsAvailable() {
		preflight.RiseupAsiaAvailable = true
		preflight.RiseupAsiaNamespace = riseupResult.Value().Namespace
	}

	quploadResult := client.CheckQUploadAvailable()
	if !quploadResult.HasError() && quploadResult.Value().IsAvailable() {
		preflight.QUploadAvailable = true
		preflight.QUploadNamespace = quploadResult.Value().Namespace
	}

	return preflight
}

// buildUnreachablePreflight constructs a preflight result for an unreachable site.
func buildUnreachablePreflight(siteId int64, site models.Site, errMsg string) PreflightSiteResult {
	return PreflightSiteResult{
		SiteId:      siteId,
		SiteName:    site.Name,
		SiteUrl:     site.Url,
		IsReachable: false,
		Error:       errMsg,
	}
}
