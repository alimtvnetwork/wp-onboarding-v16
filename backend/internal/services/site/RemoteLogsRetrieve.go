package site

import (
	"context"
	"net/url"
	"strconv"
	"sync"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// LogsRetrieveParams holds the query parameters for log retrieval.
type LogsRetrieveParams struct {
	IncludeInfoLog    bool
	IncludeErrorLog   bool
	IncludeStacktrace bool
	MaxLines          int
}

// RetrieveRemoteLogs fetches log file contents from BOTH plugin namespaces in parallel.
// Unlike other log methods that return first-wins, this returns ALL available results
// so the UI can show tabs per plugin.
// The PHP /logs/retrieve endpoint returns a flat response (not envelope-wrapped).
func (s *Service) RetrieveRemoteLogs(ctx context.Context, siteId int64, params LogsRetrieveParams) (*wordpress.LogsRetrieveResult, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	queryString := buildRetrieveQuery(params)

	type nsResult struct {
		ns   string
		data *wordpress.PluginLogsData
	}

	ch := make(chan nsResult, len(allNamespaces))
	var wg sync.WaitGroup

	for _, ns := range allNamespaces {
		wg.Add(1)
		go func(namespace string) {
			defer wg.Done()

			basePath := wordpress.BuildNamespacedEndpoint(namespace, ep.LogsRetrieve)
			endpoint := basePath
			if queryString != "" {
				endpoint = basePath + "?" + queryString
			}

			// Try flat response first (v2.31.0+), fall back to envelope (v2.30.0)
			result := wordpress.DoApiCall[wordpress.LogsRetrievePhpResponse](client, wordpress.ApiCallInput{
				Method:    httpmethod.Get,
				Endpoint:  endpoint,
				Operation: operationtype.RetrieveLogs,
			})

			pluginData := &wordpress.PluginLogsData{
				Namespace: namespace,
				Label:     wordpress.NamespaceLabel(namespace),
				Available: false,
			}

			if result.HasError() {
				ch <- nsResult{ns: namespace, data: pluginData}
				return
			}

			php := result.Value()

			// Backward compat: if flat unmarshal yielded no log data, try envelope unwrap (v2.30.0)
			if php.InfoLog == nil && php.ErrorLog == nil && php.StacktraceLog == nil {
				envelopeResult := wordpress.DoApiCall[wordpress.PhpEnvelope[wordpress.LogsRetrievePhpResponse]](client, wordpress.ApiCallInput{
					Method:    httpmethod.Get,
					Endpoint:  endpoint,
					Operation: operationtype.RetrieveLogs,
				})
				if !envelopeResult.HasError() {
					unwrapped, unwrapErr := wordpress.UnwrapPhpResult(envelopeResult.Value())
					if unwrapErr == nil {
						php = unwrapped
					}
				}
			}

			pluginData.Available = true
			pluginData.InfoLog = php.InfoLog
			pluginData.ErrorLog = php.ErrorLog
			pluginData.Stacktrace = php.StacktraceLog

			ch <- nsResult{ns: namespace, data: pluginData}
		}(ns)
	}

	wg.Wait()
	close(ch)

	pluginsByNamespace := make(map[string]wordpress.PluginLogsData, len(allNamespaces))
	for probe := range ch {
		if probe.data != nil {
			pluginsByNamespace[probe.ns] = *probe.data
		}
	}

	plugins := make([]wordpress.PluginLogsData, 0, len(allNamespaces))
	for _, namespace := range allNamespaces {
		if plugin, ok := pluginsByNamespace[namespace]; ok {
			plugins = append(plugins, plugin)
		}
	}

	return &wordpress.LogsRetrieveResult{Plugins: plugins}, nil
}

// buildRetrieveQuery constructs query string from retrieval params.
func buildRetrieveQuery(p LogsRetrieveParams) string {
	q := url.Values{}

	if !p.IncludeInfoLog {
		q.Set("include_info_log", "false")
	}
	if !p.IncludeErrorLog {
		q.Set("include_error_log", "false")
	}
	if !p.IncludeStacktrace {
		q.Set("include_stacktrace", "false")
	}
	if p.MaxLines > 0 {
		q.Set("max_lines", strconv.Itoa(p.MaxLines))
	}

	return q.Encode()
}
