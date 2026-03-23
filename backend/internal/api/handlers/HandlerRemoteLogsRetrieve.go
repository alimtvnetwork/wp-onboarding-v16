// Package handlers provides the remote log retrieval HTTP handler
package handlers

import (
	"net/http"
	"strconv"

	"wp-plugin-publish/internal/services/site"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// RetrieveRemoteLogs fetches log file contents from both plugin namespaces
func RetrieveRemoteLogs(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, err := getIdParam(r, "id")
	if err != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid site ID")
		return
	}

	params := parseRetrieveParams(r)

	result, appErr := Services.SiteService.RetrieveRemoteLogs(r.Context(), siteId, params)
	if appErr != nil {
		respondErrorWithDelegated(w, resolveHttpStatus(appErr, wordpress.HttpStatusServerError), apperror.ErrWPConnection, appErr.Error(), appErr)
		return
	}

	respondSuccess(w, result)
}

// parseRetrieveParams extracts log retrieval query params with defaults.
func parseRetrieveParams(r *http.Request) site.LogsRetrieveParams {
	q := r.URL.Query()

	params := site.LogsRetrieveParams{
		IncludeInfoLog:    parseBoolParam(q.Get("include_info_log"), true),
		IncludeErrorLog:   parseBoolParam(q.Get("include_error_log"), true),
		IncludeStacktrace: parseBoolParam(q.Get("include_stacktrace"), true),
		MaxLines:          parseIntParam(q.Get("max_lines"), 200),
	}

	return params
}

func parseBoolParam(val string, defaultVal bool) bool {
	if val == "" {
		return defaultVal
	}
	parsed, err := strconv.ParseBool(val)
	if err != nil {
		return defaultVal
	}
	return parsed
}

func parseIntParam(val string, defaultVal int) int {
	if val == "" {
		return defaultVal
	}
	parsed, err := strconv.Atoi(val)
	if err != nil {
		return defaultVal
	}
	return parsed
}
