// Package handlers - Snapshot management HTTP handlers
package handlers

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"strconv"

	"github.com/gorilla/mux"
	"wp-plugin-publish/internal/wordpress"
)

// --- Remote Snapshot Management Handlers (Phase 28) ---

// GetRemoteSnapshots returns all snapshots from a remote WordPress site
var GetRemoteSnapshots = handleSiteActionByID("E3020",
	func(ctx context.Context, siteID int64) (any, error) {
		return Services.SiteService.GetRemoteSnapshots(ctx, siteID)
	},
)

// GetRemoteSnapshot returns a specific snapshot from a remote WordPress site
func GetRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", wordpress.ResponseMessageServiceNotAvailable.String())
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
		return
	}

	snapshotID, err := getSnapshotIDParam(r)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
		return
	}

	snapshot, err := Services.SiteService.GetRemoteSnapshot(r.Context(), siteID, snapshotID)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3021", err.Error())
		return
	}
	respondSuccess(w, snapshot)
}

// CreateRemoteSnapshot triggers a new snapshot on a remote WordPress site
func CreateRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", wordpress.ResponseMessageServiceNotAvailable.String())
		return
	}
	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
		return
	}

	var opts wordpress.SnapshotCreateOptions
	_ = decodeJSONSilent(r, &opts)

	result, err := Services.SiteService.CreateRemoteSnapshot(r.Context(), siteID, opts)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3022", err.Error())
		return
	}
	respondCreated(w, result)
}

// DeleteRemoteSnapshot removes a snapshot from a remote WordPress site
func DeleteRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", wordpress.ResponseMessageServiceNotAvailable.String())
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
		return
	}

	snapshotID, err := getSnapshotIDParam(r)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
		return
	}

	if err := Services.SiteService.DeleteRemoteSnapshot(r.Context(), siteID, snapshotID); err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3023", err.Error())
		return
	}
	respondSuccess(w, SnapshotDeleteResponse{Deleted: true, SnapshotID: snapshotID})
}

// RestoreRemoteSnapshot triggers a restore from snapshot on a remote WordPress site
func RestoreRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", wordpress.ResponseMessageServiceNotAvailable.String())
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
		return
	}

	snapshotID, err := getSnapshotIDParam(r)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
		return
	}

	result, err := Services.SiteService.RestoreRemoteSnapshot(r.Context(), siteID, snapshotID)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3024", err.Error())
		return
	}
	respondSuccess(w, result)
}

// GetRemoteSnapshotSettings fetches snapshot settings from a remote WordPress site
var GetRemoteSnapshotSettings = handleSiteActionByID("E3025",
	func(ctx context.Context, siteID int64) (any, error) {
		return Services.SiteService.GetRemoteSnapshotSettings(ctx, siteID)
	},
)

// UpdateRemoteSnapshotSettings updates snapshot settings on a remote WordPress site
func UpdateRemoteSnapshotSettings(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", wordpress.ResponseMessageServiceNotAvailable.String())
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
		return
	}

	var settings wordpress.SnapshotSettings
	if err := json.NewDecoder(r.Body).Decode(&settings); err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1001", wordpress.ResponseMessageInvalidRequestBody.String())
		return
	}

	result, err := Services.SiteService.UpdateRemoteSnapshotSettings(r.Context(), siteID, settings)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3026", err.Error())
		return
	}
	respondSuccess(w, result)
}

// GetRemoteSnapshotProviders returns available snapshot providers on a remote WordPress site
var GetRemoteSnapshotProviders = handleSiteActionByID("E3027",
	func(ctx context.Context, siteID int64) (any, error) {
		return Services.SiteService.GetRemoteSnapshotProviders(ctx, siteID)
	},
)

// ExportRemoteSnapshot streams a snapshot ZIP file from a remote WordPress site
func ExportRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", wordpress.ResponseMessageServiceNotAvailable.String())
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
		return
	}

	snapshotID, err := getSnapshotIDParam(r)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
		return
	}

	resp, err := Services.SiteService.ExportRemoteSnapshot(r.Context(), siteID, snapshotID)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3028", err.Error())
		return
	}
	defer resp.Body.Close()

	// Forward content type and disposition headers
	if ct := resp.Header.Get("Content-Type"); ct != "" {
		w.Header().Set("Content-Type", ct)
	} else {
		w.Header().Set("Content-Type", "application/zip")
	}
	if cd := resp.Header.Get("Content-Disposition"); cd != "" {
		w.Header().Set("Content-Disposition", cd)
	} else {
		w.Header().Set("Content-Disposition", fmt.Sprintf("attachment; filename=snapshot-%d.zip", snapshotID))
	}
	if cl := resp.Header.Get("Content-Length"); cl != "" {
		w.Header().Set("Content-Length", cl)
	}

	w.WriteHeader(wordpress.HttpStatusOk.Int())
	io.Copy(w, resp.Body)
}

// DownloadSnapshotZip proxies a cached ZIP download: requests build from WordPress, then streams the ZIP binary to the client.
func DownloadSnapshotZip(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", wordpress.ResponseMessageServiceNotAvailable.String())
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
		return
	}

	// Read snapshot_id from POST body
	var body struct {
		SnapshotID int64 `json:"snapshot_id"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil || body.SnapshotID <= 0 {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "Invalid or missing snapshot_id")
		return
	}

	zipResp, meta, err := Services.SiteService.DownloadSnapshotZip(r.Context(), siteID, body.SnapshotID)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3040", err.Error())
		return
	}
	defer zipResp.Body.Close()

	// Set download headers
	filename := meta.Filename
	if filename == "" {
		filename = fmt.Sprintf("snapshot-%d.zip", body.SnapshotID)
	}

	if ct := zipResp.Header.Get("Content-Type"); ct != "" {
		w.Header().Set("Content-Type", ct)
	} else {
		w.Header().Set("Content-Type", "application/zip")
	}
	w.Header().Set("Content-Disposition", fmt.Sprintf("attachment; filename=%q", filename))
	if cl := zipResp.Header.Get("Content-Length"); cl != "" {
		w.Header().Set("Content-Length", cl)
	}

	// Expose metadata as custom headers so React can read them
	if meta.Cached {
		w.Header().Set("X-Snapshot-Cached", "true")
	} else {
		w.Header().Set("X-Snapshot-Cached", "false")
	}
	if meta.Size > 0 {
		w.Header().Set("X-Snapshot-Size", strconv.FormatInt(meta.Size, 10))
	}

	w.WriteHeader(wordpress.HttpStatusOk.Int())
	io.Copy(w, zipResp.Body)
}

// GetRemoteAvailableTables returns the list of database tables available for snapshotting
var GetRemoteAvailableTables = handleSiteActionByID("E3029",
	func(ctx context.Context, siteID int64) (any, error) {
		return Services.SiteService.GetRemoteAvailableTables(ctx, siteID)
	},
)

// getSnapshotIDParam extracts the snapshot ID from URL parameters
func getSnapshotIDParam(r *http.Request) (int64, error) {
	vars := mux.Vars(r)
	return strconv.ParseInt(vars["snapshotId"], 10, 64)
}

// FullBackupRemoteSnapshot triggers end-to-end full backup orchestration on a remote WordPress site
func FullBackupRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", wordpress.ResponseMessageServiceNotAvailable.String())
		return
	}
	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
		return
	}

	var opts wordpress.SnapshotBackupOptions
	_ = decodeJSONSilent(r, &opts)

	result, err := Services.SiteService.FullBackupRemoteSnapshot(r.Context(), siteID, opts)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3030", err.Error())
		return
	}
	respondCreated(w, result)
}

// IncrementalBackupRemoteSnapshot triggers an incremental backup on a remote WordPress site
func IncrementalBackupRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", wordpress.ResponseMessageServiceNotAvailable.String())
		return
	}
	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
		return
	}

	var opts wordpress.SnapshotBackupOptions
	_ = decodeJSONSilent(r, &opts)

	result, err := Services.SiteService.IncrementalBackupRemoteSnapshot(r.Context(), siteID, opts)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3031", err.Error())
		return
	}
	respondCreated(w, result)
}

// ImportRemoteSnapshot handles uploading a ZIP file to import as a snapshot on a remote site.
func ImportRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", wordpress.ResponseMessageServiceNotAvailable.String())
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
		return
	}

	// Parse multipart form (max 500MB)
	if err := r.ParseMultipartForm(500 << 20); err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1001", "Failed to parse multipart form: "+err.Error())
		return
	}

	file, header, err := r.FormFile("file")
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1001", "No file provided: "+err.Error())
		return
	}
	defer file.Close()

	// Write to temp file
	tempFile, err := os.CreateTemp("", "snapshot-import-*.zip")
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E9002", "Failed to create temp file")
		return
	}
	defer os.Remove(tempFile.Name())
	defer tempFile.Close()

	if _, err := io.Copy(tempFile, file); err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E9002", "Failed to write temp file")
		return
	}
	tempFile.Close()

	_ = header // used for logging if needed

	result, err := Services.SiteService.ImportRemoteSnapshot(r.Context(), siteID, tempFile.Name())
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3032", err.Error())
		return
	}
	respondCreated(w, result)
}

// CleanupRemoteSnapshots triggers cleanup of old/orphan/stuck snapshots on a remote WordPress site
func CleanupRemoteSnapshots(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, wordpress.HttpStatusServiceUnavailable, "E9001", wordpress.ResponseMessageServiceNotAvailable.String())
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", wordpress.ResponseMessageInvalidId.String())
		return
	}

	var opts wordpress.SnapshotCleanupOptions
	_ = decodeJSONSilent(r, &opts)

	result, err := Services.SiteService.CleanupRemoteSnapshots(r.Context(), siteID, opts)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3033", err.Error())
		return
	}
	respondSuccess(w, result)
}
