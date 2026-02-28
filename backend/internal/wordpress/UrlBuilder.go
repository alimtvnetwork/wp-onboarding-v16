// Package wordpress — URL construction helpers.
// All WordPress REST API URL construction MUST go through these functions.
// Raw "/wp-json" or hardcoded path fragments are forbidden in business logic.
package wordpress

import (
	"fmt"

	ep "wp-plugin-publish/internal/enums/endpointtype"
)

// BuildWpJsonUrl constructs a full WordPress JSON API URL: {baseUrl}/wp-json{endpoint}.
// The endpoint should start with "/" (e.g., "/riseup-asia-uploader/v1/status").
func BuildWpJsonUrl(baseUrl, endpoint string) string {
	return fmt.Sprintf("%s%s%s", baseUrl, WPCoreApiRoot, endpoint)
}

// BuildWpPluginUrl constructs a full WordPress plugin API URL: {baseUrl}/wp-json/{namespace}{endpointPath}.
func BuildWpPluginUrl(baseUrl, namespace string, endpoint ep.Variant) string {
	return fmt.Sprintf("%s%s/%s%s", baseUrl, WPCoreApiRoot, namespace, endpoint.String())
}

// BuildWpProbeUrl constructs the WordPress REST API probe URL: {baseUrl}/wp-json/.
func BuildWpProbeUrl(baseUrl string) string {
	return fmt.Sprintf("%s%s/", baseUrl, WPCoreApiRoot)
}

// BuildNamespacedEndpoint constructs a namespaced endpoint path: /{namespace}{endpointPath}.
func BuildNamespacedEndpoint(namespace string, endpoint ep.Variant) string {
	return fmt.Sprintf("/%s%s", namespace, endpoint.String())
}

// OnboardMutationUploadEndpoint constructs the legacy Onboard mutation upload endpoint.
// Pattern: /{namespace}/mutations/{token}/plugins/upload
func OnboardMutationUploadEndpoint(namespace, mutationToken string) string {
	return fmt.Sprintf("/%s%s%s%s",
		namespace,
		OnboardMutationPrefix,
		mutationToken,
		OnboardMutationUploadSuffix,
	)
}
