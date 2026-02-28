// Package wordpress — URL construction helpers.
// All WordPress REST API URL construction MUST go through these functions.
// Raw "/wp-json" or hardcoded path fragments are forbidden in business logic.
package wordpress

import (
	"fmt"

	ep "wp-plugin-publish/internal/enums/endpointtype"
)

// BuildWPJSONURL constructs a full WordPress JSON API URL: {baseURL}/wp-json{endpoint}.
// The endpoint should start with "/" (e.g., "/riseup-asia-uploader/v1/status").
func BuildWPJSONURL(baseURL, endpoint string) string {
	return fmt.Sprintf("%s%s%s", baseURL, WPCoreAPIRoot, endpoint)
}

// BuildWPPluginURL constructs a full WordPress plugin API URL: {baseURL}/wp-json/{namespace}{endpointPath}.
func BuildWPPluginURL(baseURL, namespace string, endpoint ep.Variant) string {
	return fmt.Sprintf("%s%s/%s%s", baseURL, WPCoreAPIRoot, namespace, endpoint.String())
}

// BuildWPProbeURL constructs the WordPress REST API probe URL: {baseURL}/wp-json/.
func BuildWPProbeURL(baseURL string) string {
	return fmt.Sprintf("%s%s/", baseURL, WPCoreAPIRoot)
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
