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
)

// --- Remote Snapshot Management Handlers (Phase 28) ---

// GetRemoteSnapshots returns all snapshots from a remote WordPress site
var GetRemoteSnapshots = handleSiteActionByID("E3020",
	func(ctx context.Context, siteID int64) (interface{}, error) {
		return Services.SiteService.GetRemoteSnapshots(ctx, siteID)
	},
)

// GetRemoteSnapshot returns a specific snapshot from a remote WordPress site
func GetRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	snapshotID, err := getSnapshotIDParam(r)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid snapshot ID")
		return
	}

	snapshot, err := Services.SiteService.GetRemoteSnapshot(r.Context(), siteID, snapshotID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3021", err.Error())
		return
	}
	respondSuccess(w, snapshot)
}

// CreateRemoteSnapshot triggers a new snapshot on a remote WordPress site
var CreateRemoteSnapshot = handleSiteActionByIDWithOpts("E3022",
	func(ctx context.Context, siteID int64, opts map[string]interface{}) (interface{}, error) {
		return Services.SiteService.CreateRemoteSnapshot(ctx, siteID, opts)
	},
)

// DeleteRemoteSnapshot removes a snapshot from a remote WordPress site
func DeleteRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	snapshotID, err := getSnapshotIDParam(r)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid snapshot ID")
		return
	}

	if err := Services.SiteService.DeleteRemoteSnapshot(r.Context(), siteID, snapshotID); err != nil {
		respondError(w, http.StatusInternalServerError, "E3023", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"deleted": true, "snapshotId": snapshotID})
}

// RestoreRemoteSnapshot triggers a restore from snapshot on a remote WordPress site
func RestoreRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	snapshotID, err := getSnapshotIDParam(r)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid snapshot ID")
		return
	}

	opts := decodeOptionalOpts(r)
	opts["confirm"] = true

	result, err := Services.SiteService.RestoreRemoteSnapshot(r.Context(), siteID, snapshotID, opts)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3024", err.Error())
		return
	}
	respondSuccess(w, result)
}

// GetRemoteSnapshotSettings fetches snapshot settings from a remote WordPress site
var GetRemoteSnapshotSettings = handleSiteActionByID("E3025",
	func(ctx context.Context, siteID int64) (interface{}, error) {
		return Services.SiteService.GetRemoteSnapshotSettings(ctx, siteID)
	},
)

// UpdateRemoteSnapshotSettings updates snapshot settings on a remote WordPress site
func UpdateRemoteSnapshotSettings(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	var settings map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&settings); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
		return
	}

	result, err := Services.SiteService.UpdateRemoteSnapshotSettings(r.Context(), siteID, settings)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3026", err.Error())
		return
	}
	respondSuccess(w, result)
}

// GetRemoteSnapshotProviders returns available snapshot providers on a remote WordPress site
var GetRemoteSnapshotProviders = handleSiteActionByID("E3027",
	func(ctx context.Context, siteID int64) (interface{}, error) {
		return Services.SiteService.GetRemoteSnapshotProviders(ctx, siteID)
	},
)

// ExportRemoteSnapshot streams a snapshot ZIP file from a remote WordPress site
func ExportRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	snapshotID, err := getSnapshotIDParam(r)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid snapshot ID")
		return
	}

	resp, err := Services.SiteService.ExportRemoteSnapshot(r.Context(), siteID, snapshotID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3028", err.Error())
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

	w.WriteHeader(http.StatusOK)
	io.Copy(w, resp.Body)
}

// GetRemoteAvailableTables returns the list of database tables available for snapshotting
var GetRemoteAvailableTables = handleSiteActionByID("E3029",
	func(ctx context.Context, siteID int64) (interface{}, error) {
		return Services.SiteService.GetRemoteAvailableTables(ctx, siteID)
	},
)

// getSnapshotIDParam extracts the snapshot ID from URL parameters
func getSnapshotIDParam(r *http.Request) (int64, error) {
	vars := mux.Vars(r)
	return strconv.ParseInt(vars["snapshotId"], 10, 64)
}

// FullBackupRemoteSnapshot triggers end-to-end full backup orchestration on a remote WordPress site
var FullBackupRemoteSnapshot = handleSiteActionByIDWithOpts("E3030",
	func(ctx context.Context, siteID int64, opts map[string]interface{}) (interface{}, error) {
		return Services.SiteService.FullBackupRemoteSnapshot(ctx, siteID, opts)
	},
)

// IncrementalBackupRemoteSnapshot triggers an incremental backup on a remote WordPress site
var IncrementalBackupRemoteSnapshot = handleSiteActionByIDWithOpts("E3031",
	func(ctx context.Context, siteID int64, opts map[string]interface{}) (interface{}, error) {
		return Services.SiteService.IncrementalBackupRemoteSnapshot(ctx, siteID, opts)
	},
)

// ImportRemoteSnapshot handles uploading a ZIP file to import as a snapshot on a remote site.
func ImportRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	// Parse multipart form (max 500MB)
	if err := r.ParseMultipartForm(500 << 20); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Failed to parse multipart form: "+err.Error())
		return
	}

	file, header, err := r.FormFile("file")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "No file provided: "+err.Error())
		return
	}
	defer file.Close()

	// Write to temp file
	tempFile, err := os.CreateTemp("", "snapshot-import-*.zip")
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E9002", "Failed to create temp file")
		return
	}
	defer os.Remove(tempFile.Name())
	defer tempFile.Close()

	if _, err := io.Copy(tempFile, file); err != nil {
		respondError(w, http.StatusInternalServerError, "E9002", "Failed to write temp file")
		return
	}
	tempFile.Close()

	_ = header // used for logging if needed

	result, err := Services.SiteService.ImportRemoteSnapshot(r.Context(), siteID, tempFile.Name())
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3032", err.Error())
		return
	}
	respondCreated(w, result)
}

// CleanupRemoteSnapshots triggers cleanup of old/orphan/stuck snapshots on a remote WordPress site
func CleanupRemoteSnapshots(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	opts := decodeOptionalOpts(r)
	result, err := Services.SiteService.CleanupRemoteSnapshots(r.Context(), siteID, opts)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3033", err.Error())
		return
	}
	respondSuccess(w, result)
}
