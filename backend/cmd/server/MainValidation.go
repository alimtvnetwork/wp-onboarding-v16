// Package main — startup validation checks.
package main

import (
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/wordpress"
)

// namespaceRule defines an expected namespace constant and its components.
type namespaceRule struct {
	Name             string
	ActualNamespace  string
	ExpectedPrefix   string
	ExpectedVersion  string
}

// validateNamespaces verifies that Go namespace constants match the expected
// format derived from PHP PluginConfigType::ApiNamespace + ApiVersion.
// This prevents silent namespace mismatches that cause 404 errors at runtime.
func validateNamespaces(log *logger.Logger) {
	rules := []namespaceRule{
		{
			Name:            "RiseupAsiaNamespace",
			ActualNamespace: wordpress.RiseupAsiaNamespace,
			ExpectedPrefix:  "riseup-asia-api",
			ExpectedVersion: "v1",
		},
		{
			Name:            "QUploadNamespace",
			ActualNamespace: wordpress.QUploadNamespace,
			ExpectedPrefix:  "qupload-api",
			ExpectedVersion: "v1",
		},
	}

	for _, rule := range rules {
		expected := rule.ExpectedPrefix + "/" + rule.ExpectedVersion
		isMatch := rule.ActualNamespace == expected

		if isMatch {
			log.Info("Namespace validated",
				"constant", rule.Name,
				"value", rule.ActualNamespace,
			)
			continue
		}

		log.Fatal("Namespace mismatch detected — Go constant does not match PHP ApiNamespace",
			"constant", rule.Name,
			"actual", rule.ActualNamespace,
			"expected", expected,
		)
	}
}
