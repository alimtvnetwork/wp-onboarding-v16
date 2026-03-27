// Remote debug routes proxy method
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

// DebugRoutesData holds the response from the PHP /debug/routes endpoint.
type DebugRoutesData struct {
	Namespace   string                    `json:"namespace"`
	TotalRoutes int                       `json:"totalRoutes"`
	Categories  map[string]int            `json:"categories"`
	Routes      []DebugRouteEntry         `json:"routes"`
	Version     string                    `json:"version"`
}

// DebugRouteEntry represents a single registered REST API route.
type DebugRouteEntry struct {
	Pattern  string   `json:"pattern"`
	Path     string   `json:"path"`
	Methods  []string `json:"methods"`
	Category string   `json:"category"`
}

// GetRemoteDebugRoutes fetches registered REST API routes from a remote WordPress site.
// Probes all known namespaces in parallel — returns the first successful result.
func (s *Service) GetRemoteDebugRoutes(ctx context.Context, siteId int64) (*DebugRoutesData, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	type probeResult struct {
		data *DebugRoutesData
	}
	ch := make(chan probeResult, len(allNamespaces))
	var wg sync.WaitGroup

	for _, ns := range allNamespaces {
		wg.Add(1)
		go func(namespace string) {
			defer wg.Done()
			endpoint := wordpress.BuildNamespacedEndpoint(namespace, ep.DebugRoutes)
			result := wordpress.DoApiCall[DebugRoutesData](client, wordpress.ApiCallInput{
				Method:    httpmethod.Get,
				Endpoint:  endpoint,
				Operation: operationtype.GetDebugRoutes,
			})
			if result.HasError() {
				return
			}
			data := result.Value()
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

	return nil, apperror.New(apperror.ErrWPConnection, "no plugin namespace responded to /debug/routes")
}
