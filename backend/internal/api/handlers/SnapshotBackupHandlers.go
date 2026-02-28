// Package handlers - Snapshot backup, import, and cleanup HTTP handlers
package handlers

import (
	"io"
	"net/http"
	"os"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/pathutil"
)

// FullBackupRemoteSnapshot triggers end-to-end full backup orchestration on a remote WordPress site.
func FullBackupRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, ok := parseSiteIdOrFail(w, r)
	if !ok {
		return
	}

	var opts wordpress.SnapshotBackupOptions
	_ = decodeJsonSilent(r, &opts)

	result, err := Services.SiteService.FullBackupRemoteSnapshot(r.Context(), siteId, opts)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3030", err.Error())
		return
	}

	respondCreated(w, result)
}

// IncrementalBackupRemoteSnapshot triggers an incremental backup on a remote WordPress site.
func IncrementalBackupRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, ok := parseSiteIdOrFail(w, r)
	if !ok {
		return
	}

	var opts wordpress.SnapshotBackupOptions
	_ = decodeJsonSilent(r, &opts)

	result, err := Services.SiteService.IncrementalBackupRemoteSnapshot(r.Context(), siteId, opts)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3031", err.Error())
		return
	}

	respondCreated(w, result)
}

// ImportRemoteSnapshot handles uploading a ZIP file to import as a snapshot on a remote site.
func ImportRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, ok := parseSiteIdOrFail(w, r)
	if !ok {
		return
	}

	tempPath, ok := receiveUploadToTemp(w, r)
	if !ok {
		return
	}
	defer pathutil.RemoveFileUnchecked(tempPath)

	result, err := Services.SiteService.ImportRemoteSnapshot(r.Context(), siteId, tempPath)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3032", err.Error())
		return
	}

	respondCreated(w, result)
}

// receiveUploadToTemp parses the multipart form, writes the file to a temp path, and returns it.
func receiveUploadToTemp(w http.ResponseWriter, r *http.Request) (string, bool) {
	parseErr := r.ParseMultipartForm(500 << 20)
	if parseErr != nil {
		respondBadRequest(w, "E1001", "Failed to parse multipart form: "+parseErr.Error())

		return "", false
	}

	return extractFormFileToTemp(w, r)
}

// copyToTemp copies data from src to dst.
func copyToTemp(dst *os.File, src io.Reader) error {
	_, err := io.Copy(dst, src)

	return err
}

// extractFormFileToTemp reads the "file" field and writes it to a temporary file.
func extractFormFileToTemp(w http.ResponseWriter, r *http.Request) (string, bool) {
	file, _, err := r.FormFile("file")
	if err != nil {
		respondBadRequest(w, "E1001", "No file provided: "+err.Error())
		return "", false
	}
	defer file.Close()

	return writeToTempFile(w, file)
}

// writeToTempFile creates a temp file, copies content into it, and returns the path.
func writeToTempFile(w http.ResponseWriter, src io.Reader) (string, bool) {
	tempFile, err := os.CreateTemp("", "snapshot-import-*.zip")
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E9002", "Failed to create temp file")
		return "", false
	}

	copyErr := copyToTemp(tempFile, src)
	if copyErr != nil {
		tempFile.Close()
		pathutil.RemoveFileUnchecked(tempFile.Name())
		respondError(w, wordpress.HttpStatusServerError, "E9002", "Failed to write temp file")

		return "", false
	}
	tempFile.Close()

	return tempFile.Name(), true
}

// CleanupRemoteSnapshots triggers cleanup of old/orphan/stuck snapshots on a remote WordPress site.
func CleanupRemoteSnapshots(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, ok := parseSiteIdOrFail(w, r)
	if !ok {
		return
	}

	var opts wordpress.SnapshotCleanupOptions
	_ = decodeJsonSilent(r, &opts)

	result, err := Services.SiteService.CleanupRemoteSnapshots(r.Context(), siteId, opts)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3033", err.Error())
		return
	}

	respondSuccess(w, result)
}
