// Package handlers - Snapshot management HTTP handlers
package handlers

import (
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
func GetRemoteSnapshots(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	snapshots, err := Services.SiteService.GetRemoteSnapshots(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3020", err.Error())
		return
	}
	respondSuccess(w, snapshots)
}

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
func CreateRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	var opts map[string]interface{}
	if r.Body != nil {
		_ = json.NewDecoder(r.Body).Decode(&opts)
	}
	if opts == nil {
		opts = map[string]interface{}{}
	}

	result, err := Services.SiteService.CreateRemoteSnapshot(r.Context(), siteID, opts)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3022", err.Error())
		return
	}
	respondJSON(w, http.StatusCreated, map[string]interface{}{
		"success": true,
		"data":    result,
	})
}

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

	var opts map[string]interface{}
	if r.Body != nil {
		_ = json.NewDecoder(r.Body).Decode(&opts)
	}
	if opts == nil {
		opts = map[string]interface{}{}
	}
	// Always require confirmation
	opts["confirm"] = true

	result, err := Services.SiteService.RestoreRemoteSnapshot(r.Context(), siteID, snapshotID, opts)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3024", err.Error())
		return
	}
	respondSuccess(w, result)
}

// GetRemoteSnapshotSettings fetches snapshot settings from a remote WordPress site
func GetRemoteSnapshotSettings(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	settings, err := Services.SiteService.GetRemoteSnapshotSettings(r.Context(), siteID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3025", err.Error())
		return
	}
	respondSuccess(w, settings)
}

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
func GetRemoteSnapshotProviders(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	providers, err := Services.SiteService.GetRemoteSnapshotProviders(r.Context(), siteID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3027", err.Error())
		return
	}
	respondSuccess(w, providers)
}

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
func GetRemoteAvailableTables(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	tables, err := Services.SiteService.GetRemoteAvailableTables(r.Context(), siteID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3029", err.Error())
		return
	}
	respondSuccess(w, tables)
}

// getSnapshotIDParam extracts the snapshot ID from URL parameters
func getSnapshotIDParam(r *http.Request) (int64, error) {
	vars := mux.Vars(r)
	return strconv.ParseInt(vars["snapshotId"], 10, 64)
}

// FullBackupRemoteSnapshot triggers end-to-end full backup orchestration on a remote WordPress site
func FullBackupRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	var opts map[string]interface{}
	if r.Body != nil {
		_ = json.NewDecoder(r.Body).Decode(&opts)
	}
	if opts == nil {
		opts = map[string]interface{}{}
	}

	result, err := Services.SiteService.FullBackupRemoteSnapshot(r.Context(), siteID, opts)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3030", err.Error())
		return
	}
	respondJSON(w, http.StatusCreated, map[string]interface{}{
		"success": true,
		"data":    result,
	})
}

// IncrementalBackupRemoteSnapshot triggers an incremental backup on a remote WordPress site
func IncrementalBackupRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	var opts map[string]interface{}
	if r.Body != nil {
		_ = json.NewDecoder(r.Body).Decode(&opts)
	}
	if opts == nil {
		opts = map[string]interface{}{}
	}

	result, err := Services.SiteService.IncrementalBackupRemoteSnapshot(r.Context(), siteID, opts)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3031", err.Error())
		return
	}
	respondJSON(w, http.StatusCreated, map[string]interface{}{
		"success": true,
		"data":    result,
	})
}

// ImportRemoteSnapshot handles uploading a ZIP file to import as a snapshot on a remote site.
// Accepts multipart form-data with a "file" field, streams the file to a temp location,
// then proxies to the WordPress plugin import endpoint.
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
	respondJSON(w, http.StatusCreated, map[string]interface{}{
		"success": true,
		"data":    result,
	})
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

	var opts map[string]interface{}
	if r.Body != nil {
		_ = json.NewDecoder(r.Body).Decode(&opts)
	}
	if opts == nil {
		opts = map[string]interface{}{}
	}

	result, err := Services.SiteService.CleanupRemoteSnapshots(r.Context(), siteID, opts)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3033", err.Error())
		return
	}
	respondSuccess(w, result)
}
