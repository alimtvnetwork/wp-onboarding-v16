// Package handlers provides plugin scanning HTTP request handlers
package handlers

import (
	"context"
	"net/http"
	"strconv"

	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// --- Watcher/Scan Handlers ---

// ScanPlugin triggers a file scan for a specific plugin
var ScanPlugin = handleActionByID(
	handlerIDConfig{
		GetService:  watcherService,
		ServiceName: "Watcher service",
		ParamName:   "id",
		ErrCode:     apperror.ErrBackupCreate,
	},
	func(ctx context.Context, id int64) (any, *apperror.AppError) {
		return Services.WatcherService.TriggerScan(ctx, id)
	},
)

// ScanAllPlugins triggers a file scan for all plugins
var ScanAllPlugins = handleNoArgs(
	noArgsConfig{
		GetService:  watcherService,
		ServiceName: "Watcher service",
		ErrCode:     apperror.ErrBackupRestore,
	},
	func(ctx context.Context) (any, *apperror.AppError) {
		return Services.WatcherService.ScanAll(ctx)
	},
)

// scanPathInput is the JSON body for ScanDirectoryPath.
type scanPathInput struct {
	Path            string `json:"path"`            // external key (frontend request body)
	CreateDetection bool   `json:"createDetection"` // external key
}

// ScanDirectoryPath scans a directory path for WordPress plugin and creates wp-plugin-detected.json
func ScanDirectoryPath(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.PluginService, "Plugin service") {
		return
	}

	var input scanPathInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	isPathEmpty := input.Path == ""

	if isPathEmpty {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			apperror.ErrConfigParse,
			"Path is required",
		)

		return
	}

	result, appErr := Services.PluginService.ScanDirectory(r.Context(), input.Path)
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			apperror.ErrBackupDelete,
			appErr.Error(),
		)

		return
	}

	detection := scanDetectionInput{
		Result: result,
		Input:  input,
	}

	respondScanWithDetection(w, r, detection)
}

// respondScanWithDetection handles the detection file creation logic for ScanDirectoryPath.
func respondScanWithDetection(w http.ResponseWriter, r *http.Request, scanResult scanDetectionInput) {
	isDetectionSkipped := !scanResult.Input.CreateDetection
	if isDetectionSkipped {
		respondSuccess(w, scanResult.Result)

		return
	}

	appErr := Services.PluginService.WritePluginDetected(r.Context(), scanResult.Input.Path)
	if appErr != nil {
		errorResponse := ScanResultResponse{
			Scan:           scanResult.Result,
			DetectionError: appErr.Error(),
		}

		respondSuccess(w, errorResponse)

		return
	}

	successResponse := ScanResultResponse{
		Scan:               scanResult.Result,
		IsDetectionCreated: true,
	}

	respondSuccess(w, successResponse)
}

// scanDetectionInput bundles parameters for respondScanWithDetection.
type scanDetectionInput struct {
	Result *plugin.ScanResult
	Input  scanPathInput
}

// scanPathsInput is the JSON body for ScanDirectoriesPath.
type scanPathsInput struct {
	Paths           []string `json:"paths"`           // external key (frontend request body)
	CreateDetection bool     `json:"createDetection"` // external key
}

// ScanDirectoriesPath scans multiple directories for WordPress plugin info
func ScanDirectoriesPath(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.PluginService, "Plugin service") {
		return
	}

	var input scanPathsInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	isPathsEmpty := len(input.Paths) == 0

	if isPathsEmpty {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			apperror.ErrConfigParse,
			"At least one path is required",
		)

		return
	}

	results, detected := scanAllDirectories(r, input)

	response := MultiScanResponse{
		Scanned:  len(input.Paths),
		Detected: detected,
		Results:  results,
	}

	respondSuccess(w, response)
}

// scanAllDirectories scans each path and returns results with detection count.
func scanAllDirectories(r *http.Request, input scanPathsInput) ([]DirectoryScanResult, int) {
	results := make([]DirectoryScanResult, 0, len(input.Paths))
	detected := 0

	for _, path := range input.Paths {
		sr := scanSingleDirectory(r, path, input.CreateDetection)
		if sr.IsPlugin {
			detected++
		}

		results = append(results, sr)
	}

	return results, detected
}

// scanSingleDirectory scans one directory and optionally writes a detection file.
func scanSingleDirectory(r *http.Request, path string, createDetection bool) DirectoryScanResult {
	result, appErr := Services.PluginService.ScanDirectory(r.Context(), path)
	if appErr != nil {
		return DirectoryScanResult{
			Path:     path,
			IsPlugin: false,
			Error:    appErr.Error(),
		}
	}

	isPlugin :=
		result != nil &&
		result.IsValid

	sr := DirectoryScanResult{
		Path:     path,
		IsPlugin: isPlugin,
		Metadata: result,
	}

	if createDetection && isPlugin {
		detErr := Services.PluginService.WritePluginDetected(r.Context(), path)
		if detErr == nil {
			sr.IsDetectionCreated = true
		}
	}

	return sr
}

// GetFileChanges returns detected file changes for a plugin
func GetFileChanges(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SyncService == nil {
		respondSuccess(w, []struct{}{})

		return
	}

	id, ok := parseID(w, r, "id")
	if !ok {
		return
	}

	siteID := parseSiteIDFromQuery(r)

	changes, appErr := Services.SyncService.GetFileChanges(r.Context(), id, siteID)
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			apperror.ErrFSRead,
			appErr.Error(),
		)

		return
	}

	respondSuccess(w, changes)
}

// parseSiteIDFromQuery extracts the optional siteId query parameter.
func parseSiteIDFromQuery(r *http.Request) int64 {
	s := r.URL.Query().Get("siteId")
	hasSiteId := s != ""

	if hasSiteId {
		id, _ := strconv.ParseInt(s, 10, 64)

		return id
	}

	return 0
}
