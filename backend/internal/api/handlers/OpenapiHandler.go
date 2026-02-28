// Package handlers provides the OpenAPI spec serving handler
package handlers

import (
	"encoding/json"
	"net/http"
	"os"
	"sync"

	"wp-plugin-publish/internal/wordpress"
)

var (
	openApiSpec     json.RawMessage
	openApiSpecOnce sync.Once
)

// ServeOpenApiSpec returns the OpenAPI 3.0 specification as JSON
func ServeOpenApiSpec(w http.ResponseWriter, r *http.Request) {
	openApiSpecOnce.Do(func() {
		// Try multiple paths (binary may run from different working dirs)
		paths := []string{
			"api/openapi.json",
			"backend/api/openapi.json",
			"../api/openapi.json",
		}

		for _, p := range paths {
			data, err := os.ReadFile(p)
			if err == nil {
				openApiSpec = data

				return
			}
		}
	})

	isSpecMissing := openApiSpec == nil

	if isSpecMissing {
		respondError(
			w,
			wordpress.HttpStatusNotFound,
			"E9001",
			"OpenAPI spec file not found",
		)

		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Cache-Control", "public, max-age=3600")
	w.WriteHeader(wordpress.HttpStatusOk.Int())
	w.Write(openApiSpec)
}
