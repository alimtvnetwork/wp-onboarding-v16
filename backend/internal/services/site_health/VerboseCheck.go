// VerboseCheck performs HEAD-based probing of multiple WP plugin endpoints.
package sitehealth

import (
	"context"
	"fmt"
	"net/http"
	"time"

	endpoint "wp-plugin-publish/internal/enums/endpointtype"
	healthstatus "wp-plugin-publish/internal/enums/healthstatustype"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// VerboseCheckResult contains per-endpoint probe results for a site.
type VerboseCheckResult struct {
	SiteId    int64
	SiteName  string
	SiteUrl   string
	Status    string // overall: healthy, degraded, down
	Duration  int64  // total milliseconds
	Endpoints []EndpointProbe
}

// EndpointProbe is the result of a single HEAD request to an endpoint.
type EndpointProbe struct {
	Name       string
	Path       string
	StatusCode int
	Status     string // ok, auth_required, error, timeout
	ResponseMs int64
	Error      string `json:",omitempty"`
}

// probeEndpoints lists the endpoints to check in verbose mode.
var probeEndpoints = []struct {
	Name     string
	Endpoint endpoint.Variant
}{
	{"status", endpoint.Status},
	{"plugins", endpoint.Plugins},
	{"logs/status", endpoint.LogsStatus},
	{"logs/rotation-status", endpoint.LogsRotationStatus},
}

// VerboseCheck performs HEAD requests against multiple endpoints on a site.
func (s *Service) VerboseCheck(ctx context.Context, siteId int64) apperror.Result[VerboseCheckResult] {
	site, siteErr := s.querySiteForCheck(ctx, siteId)
	if siteErr != nil {
		return apperror.Fail[VerboseCheckResult](siteErr)
	}

	result := s.probeAllEndpoints(ctx, site)

	return apperror.Ok(result)
}

// probeAllEndpoints HEAD-probes each endpoint and builds the result.
func (s *Service) probeAllEndpoints(ctx context.Context, site *siteCheckInfo) VerboseCheckResult {
	start := time.Now()
	probes := make([]EndpointProbe, 0, len(probeEndpoints))

	for _, ep := range probeEndpoints {
		probe := s.headProbe(ctx, site, ep.Name, ep.Endpoint)
		probes = append(probes, probe)
	}

	elapsed := time.Since(start).Milliseconds()
	overall := deriveOverallStatus(probes)

	return VerboseCheckResult{
		SiteId:    site.Id,
		SiteName:  site.Name,
		SiteUrl:   site.Url,
		Status:    overall,
		Duration:  elapsed,
		Endpoints: probes,
	}
}

// headProbe sends a single HEAD request to an endpoint.
func (s *Service) headProbe(ctx context.Context, site *siteCheckInfo, name string, ep endpoint.Variant) EndpointProbe {
	url := wordpress.BuildWpPluginUrl(site.Url, wordpress.RiseupAsiaNamespace, ep)
	probe := EndpointProbe{
		Name: name,
		Path: ep.String(),
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodHead, url, nil)
	if err != nil {
		probe.Status = "error"
		probe.Error = err.Error()

		return probe
	}

	start := time.Now()
	resp, httpErr := s.client.Do(req)
	probe.ResponseMs = time.Since(start).Milliseconds()

	if httpErr != nil {
		probe.Status = "timeout"
		probe.Error = httpErr.Error()

		return probe
	}
	defer resp.Body.Close()

	probe.StatusCode = resp.StatusCode
	probe.Status = classifyProbeStatus(resp.StatusCode)

	return probe
}

// classifyProbeStatus maps an HTTP status code to a probe status string.
func classifyProbeStatus(code int) string {
	httpStatus := wordpress.HttpStatusType(code)

	switch {
	case httpStatus.IsSuccess():
		return "ok"
	case code == wordpress.HttpStatusUnauthorized.Int() || code == wordpress.HttpStatusForbidden.Int():
		return "auth_required"
	default:
		return fmt.Sprintf("error_%d", code)
	}
}

// deriveOverallStatus computes the overall status from individual probes.
func deriveOverallStatus(probes []EndpointProbe) string {
	hasError := false
	hasAuthOnly := false

	for _, p := range probes {
		switch p.Status {
		case "ok":
			continue
		case "auth_required":
			hasAuthOnly = true
		default:
			hasError = true
		}
	}

	if hasError {
		return healthstatus.Down.DBValue()
	}

	if hasAuthOnly {
		return healthstatus.Healthy.DBValue()
	}

	return healthstatus.Healthy.DBValue()
}
