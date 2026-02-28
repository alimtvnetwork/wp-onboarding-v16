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
	func(ctx context.Context, id int64) (any, error) {
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
	func(ctx context.Context) (any, error) {
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

	if input.Path == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			apperror.ErrConfigParse,
			"Path is required",
		)

		return
	}

	result, err := Services.PluginService.ScanDirectory(r.Context(), input.Path)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			apperror.ErrBackupDelete,
			err.Error(),
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

	if err := Services.PluginService.WritePluginDetected(r.Context(), scanResult.Input.Path); err != nil {
		errorResponse := ScanResultResponse{
			Scan:           scanResult.Result,
			DetectionError: err.Error(),
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

	if len(input.Paths) == 0 {
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
	result, err := Services.PluginService.ScanDirectory(r.Context(), path)
	if err != nil {
		return DirectoryScanResult{
			Path:     path,
			IsPlugin: false,
			Error:    err.Error(),
		}
	}

	isPlugin := result != nil && result.IsValid

	sr := DirectoryScanResult{
		Path:     path,
		IsPlugin: isPlugin,
		Metadata: result,
	}

	if createDetection && isPlugin {
		if err := Services.PluginService.WritePluginDetected(r.Context(), path); err == nil {
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

	changes, err := Services.SyncService.GetFileChanges(r.Context(), id, siteID)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			apperror.ErrFSRead,
			err.Error(),
		)

		return
	}

	respondSuccess(w, changes)
}

// parseSiteIDFromQuery extracts the optional siteId query parameter.
func parseSiteIDFromQuery(r *http.Request) int64 {
	if s := r.URL.Query().Get("siteId"); s != "" {
		id, _ := strconv.ParseInt(s, 10, 64)

		return id
	}

	return 0
}
