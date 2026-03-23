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
// Each plugin check across each site runs as its own goroutine (6 goroutines for 3 sites × 2 plugins).
func (s *Service) DeployPreflight(ctx context.Context, siteIds []int64) ([]PreflightSiteResult, *apperror.AppError) {
	type siteContext struct {
		index  int
		siteId int64
		site   models.Site
		client *wordpress.Client
		err    string
	}

	// Phase 1: Resolve all sites and build clients (sequential — fast, DB-only)
	siteCtxs := make([]siteContext, len(siteIds))
	for i, id := range siteIds {
		sc := siteContext{index: i, siteId: id}
		result := s.GetById(ctx, id)
		if result.HasError() {
			sc.err = fmt.Sprintf("Site not found: %v", result.AppError())
		} else {
			sc.site = result.Value()
			client, clientErr := s.buildPreflightClient(sc.site)
			if clientErr != nil {
				sc.err = clientErr.Error()
			} else {
				sc.client = client
			}
		}
		siteCtxs[i] = sc
	}

	// Phase 2: Fire ALL plugin checks as independent goroutines (6 for 3 sites × 2 plugins)
	type pluginResult struct {
		siteIndex int
		plugin    string // "riseup" or "qupload"
		status    PreflightPluginStatus
	}

	results := make([]PreflightSiteResult, len(siteIds))
	// Initialize results with site info
	for _, sc := range siteCtxs {
		r := PreflightSiteResult{
			SiteId:   sc.siteId,
			SiteName: sc.site.Name,
			SiteUrl:  sc.site.Url,
		}
		if sc.err != "" {
			r.Error = sc.err
			r.IsReachable = false
			r.RiseupAsia = PreflightPluginStatus{Name: "riseup-asia-uploader", Status: "UNREACHABLE", Message: sc.err}
			r.QUpload = PreflightPluginStatus{Name: "qupload", Status: "UNREACHABLE", Message: sc.err}
		} else {
			r.IsReachable = true
		}
		results[sc.index] = r
	}

	// Collect plugin results via channel
	reachable := make([]siteContext, 0, len(siteCtxs))
	for _, sc := range siteCtxs {
		if sc.err == "" {
			reachable = append(reachable, sc)
		} else {
			// Broadcast unreachable immediately
			if s.wsHub != nil {
				s.wsHub.BroadcastPreflightSiteResult(results[sc.index])
			}
		}
	}

	if len(reachable) == 0 {
		return results, nil
	}

	ch := make(chan pluginResult, len(reachable)*2)
	var wg sync.WaitGroup

	for _, sc := range reachable {
		// Goroutine for Riseup Asia
		wg.Add(1)
		go func(sc siteContext) {
			defer wg.Done()
			riseupResult := sc.client.CheckRiseupAsiaAvailable()
			status := buildPluginPreflightStatus(sc.client, "riseup-asia-uploader", riseupResult)
			ch <- pluginResult{siteIndex: sc.index, plugin: "riseup", status: status}
		}(sc)

		// Goroutine for QUpload
		wg.Add(1)
		go func(sc siteContext) {
			defer wg.Done()
			quploadResult := sc.client.CheckQUploadAvailable()
			status := buildPluginPreflightStatus(sc.client, "qupload", quploadResult)
			ch <- pluginResult{siteIndex: sc.index, plugin: "qupload", status: status}
		}(sc)
	}

	// Close channel when all goroutines complete
	go func() {
		wg.Wait()
		close(ch)
	}()

	// Track completion per site to broadcast as soon as both plugins finish
	pluginsDone := make(map[int]int) // siteIndex -> count of completed plugins

	for pr := range ch {
		r := &results[pr.siteIndex]
		if pr.plugin == "riseup" {
			r.RiseupAsia = pr.status
			r.RiseupAsiaAvailable = pr.status.Available
			r.RiseupAsiaNamespace = pr.status.Namespace
		} else {
			r.QUpload = pr.status
			r.QUploadAvailable = pr.status.Available
			r.QUploadNamespace = pr.status.Namespace
		}

		pluginsDone[pr.siteIndex]++
		if pluginsDone[pr.siteIndex] == 2 {
			// Both plugins done for this site — broadcast
			if s.wsHub != nil {
				s.wsHub.BroadcastPreflightSiteResult(*r)
			}
		}
	}

	return results, nil
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


