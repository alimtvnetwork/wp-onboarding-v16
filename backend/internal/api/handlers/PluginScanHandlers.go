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
var ScanPlugin = handleActionById(
	handlerIdConfig{
		IsReady:     isWatcherServiceReady,
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
		IsReady:     isWatcherServiceReady,
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
// Returns a flat response (scan fields + detection metadata) so the frontend can
// read `isValid`, `pluginName`, `detectionCreated`, etc. without nested unwrapping.
func respondScanWithDetection(w http.ResponseWriter, r *http.Request, scanResult scanDetectionInput) {
	shouldCreateDetection := scanResult.Input.CreateDetection
	if !shouldCreateDetection {
		// Always return flat shape — never the bare *plugin.ScanResult — so the
		// frontend sees a consistent response regardless of the createDetection flag.
		respondSuccess(w, buildFlatScanResponse(scanResult.Result, false, ""))

		return
	}

	// Skip writing detection file if scan was invalid — avoids masking the real
	// reason ("not a WordPress plugin") with a generic ErrPathInvalid wrapper.
	canWriteDetection := scanResult.Result != nil && scanResult.Result.IsValid
	if !canWriteDetection {
		respondSuccess(w, buildFlatScanResponse(scanResult.Result, false, ""))

		return
	}

	appErr := Services.PluginService.WritePluginDetected(r.Context(), scanResult.Input.Path)
	if appErr != nil {
		respondSuccess(w, buildFlatScanResponse(scanResult.Result, false, appErr.Error()))

		return
	}

	respondSuccess(w, buildFlatScanResponse(scanResult.Result, true, ""))
}

// FlatScanResponse is the compile-time-enforced shape returned by ScanDirectoryPath.
// All optional metadata uses `omitempty` so the JSON shape remains identical to the
// previous map[string]any output (empty fields were skipped via addStringIfSet).
//
// Frontend (after PascalCase→camelCase transform) reads:
//   path, isValid, fileCount, totalSize, pluginName, version, mainFile,
//   description, author, authorUri, pluginUri, textDomain, requiresPhp,
//   requiresWP, error, detectionCreated, detectionError
type FlatScanResponse struct {
	Path             string `json:"Path,omitempty"`
	IsValid          bool   `json:"IsValid"`
	FileCount        int    `json:"FileCount,omitempty"`
	TotalSize        int64  `json:"TotalSize,omitempty"`
	PluginName       string `json:"PluginName,omitempty"`
	Version          string `json:"Version,omitempty"`
	MainFile         string `json:"MainFile,omitempty"`
	Description      string `json:"Description,omitempty"`
	Author           string `json:"Author,omitempty"`
	AuthorUri        string `json:"AuthorUri,omitempty"`
	PluginUri        string `json:"PluginUri,omitempty"`
	TextDomain       string `json:"TextDomain,omitempty"`
	RequiresPhp      string `json:"RequiresPhp,omitempty"`
	RequiresWP       string `json:"RequiresWP,omitempty"`
	Error            string `json:"Error,omitempty"`
	DetectionCreated bool   `json:"DetectionCreated"`
	DetectionError   string `json:"DetectionError,omitempty"`
}

// buildFlatScanResponse merges scan results with detection metadata into a typed
// FlatScanResponse. The struct is the single source of truth for the response
// shape — adding/removing fields requires a struct change, caught at compile time.
func buildFlatScanResponse(scan *plugin.ScanResult, detectionCreated bool, detectionError string) FlatScanResponse {
	out := FlatScanResponse{
		DetectionCreated: detectionCreated,
		DetectionError:   detectionError,
	}

	if scan == nil {
		return out
	}

	out.Path = scan.Path
	out.IsValid = scan.IsValid
	out.FileCount = scan.FileCount
	out.TotalSize = scan.TotalSize
	out.PluginName = scan.PluginName
	out.Version = scan.Version
	out.MainFile = scan.MainFile
	out.Description = scan.Description
	out.Author = scan.Author
	out.AuthorUri = scan.AuthorUri
	out.PluginUri = scan.PluginUri
	out.TextDomain = scan.TextDomain
	out.RequiresPhp = scan.RequiresPhp
	out.RequiresWP = scan.RequiresWP
	out.Error = scan.Error

	return out
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
	isServiceMissing := Services == nil || Services.SyncService == nil

	if isServiceMissing {
		respondSuccess(w, []struct{}{})

		return
	}

	id, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	siteId := parseSiteIdFromQuery(r)

	changes, appErr := Services.SyncService.GetFileChanges(r.Context(), id, siteId)
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

// parseSiteIdFromQuery extracts the optional siteId query parameter.
func parseSiteIdFromQuery(r *http.Request) int64 {
	s := r.URL.Query().Get("siteId")
	hasSiteId := s != ""

	if hasSiteId {
		id, _ := strconv.ParseInt(s, 10, 64)

		return id
	}

	return 0
}
