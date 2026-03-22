package site

import (
	"context"
	"errors"
	"fmt"
	"sync"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// PreflightPluginStatus is the normalized per-plugin status data shown in the dashboard.
type PreflightPluginStatus struct {
	Name          string
	Available     bool
	Namespace     string
	Status        string
	HttpStatus    int
	Message       string
	Version       string
	WpVersion     string
	PhpVersion    string
	PluginName    string
	ApiNamespace  string
	ServerTime    string
	DbAvailable   string
	RemoteSiteUrl string
}

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
	RiseupAsia          PreflightPluginStatus
	QUpload             PreflightPluginStatus
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

// checkSiteEndpoints probes both Riseup Asia and QUpload endpoints in parallel.
// Each plugin check (availability + metadata) runs concurrently for maximum speed.
func (s *Service) checkSiteEndpoints(siteId int64, site models.Site, client *wordpress.Client) PreflightSiteResult {
	preflight := PreflightSiteResult{
		SiteId:      siteId,
		SiteName:    site.Name,
		SiteUrl:     site.Url,
		IsReachable: true,
	}

	var wg sync.WaitGroup
	var riseupStatus, quploadStatus PreflightPluginStatus

	wg.Add(2)

	go func() {
		defer wg.Done()
		riseupResult := client.CheckRiseupAsiaAvailable()
		riseupStatus = buildPluginPreflightStatus(client, "riseup-asia-uploader", riseupResult)
	}()

	go func() {
		defer wg.Done()
		quploadResult := client.CheckQUploadAvailable()
		quploadStatus = buildPluginPreflightStatus(client, "qupload", quploadResult)
	}()

	wg.Wait()

	preflight.RiseupAsia = riseupStatus
	preflight.RiseupAsiaAvailable = riseupStatus.Available
	preflight.RiseupAsiaNamespace = riseupStatus.Namespace

	preflight.QUpload = quploadStatus
	preflight.QUploadAvailable = quploadStatus.Available
	preflight.QUploadNamespace = quploadStatus.Namespace

	return preflight
}

func buildPluginPreflightStatus(client *wordpress.Client, slug string, availabilityResult apperror.Result[*wordpress.UploaderAvailability]) PreflightPluginStatus {
	status := PreflightPluginStatus{
		Name:   slug,
		Status: "ERROR",
	}

	if availabilityResult.HasError() {
		status.HttpStatus = extractAppErrorStatus(availabilityResult.AppError())
		status.Status, status.Message = classifyPreflightFailure(status.HttpStatus, availabilityResult.AppError().Error())
		return status
	}

	availability := availabilityResult.ValueOr(nil)
	if availability == nil || !availability.IsAvailable() {
		status.Status = "NOT_INSTALLED"
		status.Message = "Plugin not found (404)"
		return status
	}

	status.Available = true
	status.Namespace = availability.Namespace

	metadataResult := client.GetStatusMetadataByNamespace(availability.Namespace)
	if metadataResult.HasError() {
		status.HttpStatus = extractAppErrorStatus(metadataResult.AppError())
		status.Status, status.Message = classifyPreflightFailure(status.HttpStatus, metadataResult.AppError().Error())
		return status
	}

	metadata := metadataResult.Value()
	status.Status = "OK"
	status.HttpStatus = 200
	status.Message = metadata.Message
	status.Version = metadata.Version
	status.WpVersion = metadata.WpVersion
	status.PhpVersion = metadata.PhpVersion
	status.PluginName = metadata.PluginName
	status.ApiNamespace = metadata.ApiNamespace
	status.ServerTime = metadata.ServerTime
	status.DbAvailable = metadata.DbAvailable
	status.RemoteSiteUrl = metadata.RemoteSiteUrl

	return status
}

func extractAppErrorStatus(err *apperror.AppError) int {
	if err == nil {
		return 0
	}
	if err.Diagnostic.StatusCode > 0 {
		return err.Diagnostic.StatusCode
	}

	var apiErr *wordpress.ApiError
	if errors.As(err, &apiErr) {
		return apiErr.StatusCode
	}

	return 0
}

func classifyPreflightFailure(statusCode int, fallback string) (string, string) {
	switch statusCode {
	case 404:
		return "NOT_INSTALLED", "Plugin not found (404)"
	case 401:
		return "AUTH_FAILED", "Unauthorized (401)"
	case 403:
		return "AUTH_FAILED", "Forbidden (403)"
	case 0:
		if fallback == "" {
			return "UNREACHABLE", "Site unreachable"
		}
		return "UNREACHABLE", fallback
	default:
		if fallback == "" {
			return "ERROR", fmt.Sprintf("HTTP %d", statusCode)
		}
		return "ERROR", fallback
	}
}

// buildUnreachablePreflight constructs a preflight result for an unreachable site.
func buildUnreachablePreflight(siteId int64, site models.Site, errMsg string) PreflightSiteResult {
	return PreflightSiteResult{
		SiteId:      siteId,
		SiteName:    site.Name,
		SiteUrl:     site.Url,
		IsReachable: false,
		RiseupAsia: PreflightPluginStatus{
			Name:    "riseup-asia-uploader",
			Status:  "UNREACHABLE",
			Message: errMsg,
		},
		QUpload: PreflightPluginStatus{
			Name:    "qupload",
			Status:  "UNREACHABLE",
			Message: errMsg,
		},
		Error: errMsg,
	}
}

