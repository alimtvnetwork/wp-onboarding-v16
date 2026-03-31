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

// GetRemoteDedupRegistry fetches the dedup registry from both plugin namespaces in parallel.
func (s *Service) GetRemoteDedupRegistry(ctx context.Context, siteId int64) (*wordpress.DedupRegistryResult, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	type nsResult struct {
		ns   string
		data *wordpress.PluginDedupRegistryData
	}

	ch := make(chan nsResult, len(allNamespaces))
	var wg sync.WaitGroup

	for _, ns := range allNamespaces {
		wg.Add(1)
		go func(namespace string) {
			defer wg.Done()

			endpoint := wordpress.BuildNamespacedEndpoint(namespace, ep.LogsDedupRegistry)
			result := wordpress.DoApiCall[wordpress.DedupRegistryPhpResponse](client, wordpress.ApiCallInput{
				Method:    httpmethod.Get,
				Endpoint:  endpoint,
				Operation: operationtype.GetDedupRegistry,
			})

			if result.HasError() {
				ch <- nsResult{ns: namespace, data: &wordpress.PluginDedupRegistryData{
					Namespace: namespace,
					Label:     wordpress.NamespaceLabel(namespace),
					Available: false,
				}}
				return
			}

			php := result.Value()
			ch <- nsResult{ns: namespace, data: &wordpress.PluginDedupRegistryData{
				Namespace:     namespace,
				Label:         wordpress.NamespaceLabel(namespace),
				Available:     true,
				DedupRegistry: &php.DedupRegistry,
			}}
		}(ns)
	}

	wg.Wait()
	close(ch)

	pluginsByNamespace := make(map[string]wordpress.PluginDedupRegistryData, len(allNamespaces))
	for probe := range ch {
		if probe.data != nil {
			pluginsByNamespace[probe.ns] = *probe.data
		}
	}

	plugins := make([]wordpress.PluginDedupRegistryData, 0, len(allNamespaces))
	for _, namespace := range allNamespaces {
		if plugin, ok := pluginsByNamespace[namespace]; ok {
			plugins = append(plugins, plugin)
		}
	}

	return &wordpress.DedupRegistryResult{Plugins: plugins}, nil
}

// ClearRemoteDedupRegistry clears the dedup registry on both plugin namespaces in parallel.
func (s *Service) ClearRemoteDedupRegistry(ctx context.Context, siteId int64) (*wordpress.DedupRegistryClearResult, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	type nsResult struct {
		ns   string
		data *wordpress.PluginDedupClearData
	}

	ch := make(chan nsResult, len(allNamespaces))
	var wg sync.WaitGroup

	for _, ns := range allNamespaces {
		wg.Add(1)
		go func(namespace string) {
			defer wg.Done()

			endpoint := wordpress.BuildNamespacedEndpoint(namespace, ep.LogsDedupRegistry)
			result := wordpress.DoApiCall[wordpress.DedupRegistryClearPhpResponse](client, wordpress.ApiCallInput{
				Method:    httpmethod.Delete,
				Endpoint:  endpoint,
				Operation: operationtype.ClearDedupRegistry,
			})

			if result.HasError() {
				ch <- nsResult{ns: namespace, data: &wordpress.PluginDedupClearData{
					Namespace: namespace,
					Label:     wordpress.NamespaceLabel(namespace),
					Cleared:   false,
				}}
				return
			}

			php := result.Value()
			ch <- nsResult{ns: namespace, data: &wordpress.PluginDedupClearData{
				Namespace:          namespace,
				Label:              wordpress.NamespaceLabel(namespace),
				Cleared:            php.Success,
				PreviousEntryCount: php.PreviousEntryCount,
			}}
		}(ns)
	}

	wg.Wait()
	close(ch)

	pluginsByNamespace := make(map[string]wordpress.PluginDedupClearData, len(allNamespaces))
	for probe := range ch {
		if probe.data != nil {
			pluginsByNamespace[probe.ns] = *probe.data
		}
	}

	plugins := make([]wordpress.PluginDedupClearData, 0, len(allNamespaces))
	for _, namespace := range allNamespaces {
		if plugin, ok := pluginsByNamespace[namespace]; ok {
			plugins = append(plugins, plugin)
		}
	}

	return &wordpress.DedupRegistryClearResult{Plugins: plugins}, nil
}
