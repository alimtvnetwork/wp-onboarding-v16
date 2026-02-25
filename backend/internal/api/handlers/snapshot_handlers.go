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
	responsemessage "wp-plugin-publish/internal/enums/response_message"
	"wp-plugin-publish/internal/wordpress"
)

// --- Remote Snapshot Management Handlers (Phase 28) ---

// GetRemoteSnapshots returns all snapshots from a remote WordPress site
var GetRemoteSnapshots = handleSiteActionByID("E3020",
	func(ctx context.Context, siteId int64) (any, error) {
		return Services.SiteService.GetRemoteSnapshots(ctx, siteId)
	},
)

// GetRemoteSnapshot returns a specific snapshot from a remote WordPress site
func GetRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	siteId, err := getIDParam(r, "id")
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	snapshotId, err := getSnapshotIdParam(r)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	snapshot, err := Services.SiteService.GetRemoteSnapshot(r.Context(), siteId, snapshotId)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E3021",
			err.Error(),
		)

		return
	}

	respondSuccess(w, snapshot)
}

// CreateRemoteSnapshot triggers a new snapshot on a remote WordPress site
func CreateRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	siteId, err := getIDParam(r, "id")
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	var opts wordpress.SnapshotCreateOptions
	_ = decodeJSONSilent(r, &opts)

	result, err := Services.SiteService.CreateRemoteSnapshot(r.Context(), siteId, opts)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E3022",
			err.Error(),
		)

		return
	}

	respondCreated(w, result)
}

// DeleteRemoteSnapshot removes a snapshot from a remote WordPress site
func DeleteRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	siteId, err := getIDParam(r, "id")
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	snapshotId, err := getSnapshotIdParam(r)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	if err := Services.SiteService.DeleteRemoteSnapshot(r.Context(), siteId, snapshotId); err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E3023",
			err.Error(),
		)

		return
	}

	respondSuccess(w, SnapshotDeleteResponse{Deleted: true, SnapshotId: snapshotId})
}

// RestoreRemoteSnapshot triggers a restore from snapshot on a remote WordPress site
func RestoreRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	siteId, err := getIDParam(r, "id")
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	snapshotId, err := getSnapshotIdParam(r)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	result, err := Services.SiteService.RestoreRemoteSnapshot(r.Context(), siteId, snapshotId)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E3024",
			err.Error(),
		)

		return
	}

	respondSuccess(w, result)
}

// GetRemoteSnapshotSettings fetches snapshot settings from a remote WordPress site
var GetRemoteSnapshotSettings = handleSiteActionByID("E3025",
	func(ctx context.Context, siteId int64) (any, error) {
		return Services.SiteService.GetRemoteSnapshotSettings(ctx, siteId)
	},
)

// UpdateRemoteSnapshotSettings updates snapshot settings on a remote WordPress site
func UpdateRemoteSnapshotSettings(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	siteId, err := getIDParam(r, "id")
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	var settings wordpress.SnapshotSettings
	if err := json.NewDecoder(r.Body).Decode(&settings); err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1001",
			responsemessage.InvalidRequestBody.String(),
		)

		return
	}

	result, err := Services.SiteService.UpdateRemoteSnapshotSettings(r.Context(), siteId, settings)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E3026",
			err.Error(),
		)

		return
	}

	respondSuccess(w, result)
}

// GetRemoteSnapshotProviders returns available snapshot providers on a remote WordPress site
var GetRemoteSnapshotProviders = handleSiteActionByID("E3027",
	func(ctx context.Context, siteId int64) (any, error) {
		return Services.SiteService.GetRemoteSnapshotProviders(ctx, siteId)
	},
)

// ExportRemoteSnapshot streams a snapshot ZIP file from a remote WordPress site
func ExportRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	siteId, err := getIDParam(r, "id")
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	snapshotId, err := getSnapshotIdParam(r)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	resp, err := Services.SiteService.ExportRemoteSnapshot(r.Context(), siteId, snapshotId)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E3028",
			err.Error(),
		)

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
		w.Header().Set("Content-Disposition", fmt.Sprintf("attachment; filename=snapshot-%d.zip", snapshotId))
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
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	siteId, err := getIDParam(r, "id")
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	// Read snapshotId from POST body
	var body struct {
		SnapshotId int64
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil || body.SnapshotId <= 0 {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Invalid or missing snapshotId",
		)

		return
	}

	zipResp, meta, err := Services.SiteService.DownloadSnapshotZip(r.Context(), siteId, body.SnapshotId)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E3040",
			err.Error(),
		)

		return
	}
	defer zipResp.Body.Close()

	// Set download headers
	filename := meta.Filename
	if filename == "" {
		filename = fmt.Sprintf("snapshot-%d.zip", body.SnapshotId)
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
	func(ctx context.Context, siteId int64) (any, error) {
		return Services.SiteService.GetRemoteAvailableTables(ctx, siteId)
	},
)

// getSnapshotIdParam extracts the snapshot ID from URL parameters
func getSnapshotIdParam(r *http.Request) (int64, error) {
	vars := mux.Vars(r)

	return strconv.ParseInt(vars["snapshotId"], 10, 64)
}

// FullBackupRemoteSnapshot triggers end-to-end full backup orchestration on a remote WordPress site
func FullBackupRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	siteId, err := getIDParam(r, "id")
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	var opts wordpress.SnapshotBackupOptions
	_ = decodeJSONSilent(r, &opts)

	result, err := Services.SiteService.FullBackupRemoteSnapshot(r.Context(), siteId, opts)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E3030",
			err.Error(),
		)

		return
	}

	respondCreated(w, result)
}

// IncrementalBackupRemoteSnapshot triggers an incremental backup on a remote WordPress site
func IncrementalBackupRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	siteId, err := getIDParam(r, "id")
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	var opts wordpress.SnapshotBackupOptions
	_ = decodeJSONSilent(r, &opts)

	result, err := Services.SiteService.IncrementalBackupRemoteSnapshot(r.Context(), siteId, opts)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E3031",
			err.Error(),
		)

		return
	}

	respondCreated(w, result)
}

// ImportRemoteSnapshot handles uploading a ZIP file to import as a snapshot on a remote site.
func ImportRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	siteId, err := getIDParam(r, "id")
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	// Parse multipart form (max 500MB)
	if err := r.ParseMultipartForm(500 << 20); err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1001",
			"Failed to parse multipart form: "+err.Error(),
		)

		return
	}

	file, header, err := r.FormFile("file")
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1001",
			"No file provided: "+err.Error(),
		)

		return
	}
	defer file.Close()

	// Write to temp file
	tempFile, err := os.CreateTemp("", "snapshot-import-*.zip")
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E9002",
			"Failed to create temp file",
		)

		return
	}
	defer os.Remove(tempFile.Name())
	defer tempFile.Close()

	if _, err := io.Copy(tempFile, file); err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E9002",
			"Failed to write temp file",
		)

		return
	}
	tempFile.Close()

	_ = header // used for logging if needed

	result, err := Services.SiteService.ImportRemoteSnapshot(r.Context(), siteId, tempFile.Name())
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E3032",
			err.Error(),
		)

		return
	}

	respondCreated(w, result)
}

// CleanupRemoteSnapshots triggers cleanup of old/orphan/stuck snapshots on a remote WordPress site
func CleanupRemoteSnapshots(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return
	}

	siteId, err := getIDParam(r, "id")
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return
	}

	var opts wordpress.SnapshotCleanupOptions
	_ = decodeJSONSilent(r, &opts)

	result, err := Services.SiteService.CleanupRemoteSnapshots(r.Context(), siteId, opts)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E3033",
			err.Error(),
		)

		return
	}

	respondSuccess(w, result)
}
