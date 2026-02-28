// Package handlers - Snapshot export and ZIP download HTTP handlers
package handlers

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strconv"

	"wp-plugin-publish/internal/services/site"
	"wp-plugin-publish/internal/wordpress"
)

// ExportRemoteSnapshot streams a snapshot ZIP file from a remote WordPress site.
func ExportRemoteSnapshot(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, ok := parseSiteIdOrFail(w, r)
	if !ok {
		return
	}

	snapshotId, ok := parseSnapshotIdOrFail(w, r)
	if !ok {
		return
	}

	resp, err := Services.SiteService.ExportRemoteSnapshot(r.Context(), siteId, snapshotId)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3028", err.Error())
		return
	}
	defer resp.Body.Close()

	setExportHeaders(w, resp, snapshotId)
	w.WriteHeader(wordpress.HttpStatusOk.Int())
	io.Copy(w, resp.Body)
}

// setExportHeaders forwards content headers from the upstream response.
func setExportHeaders(w http.ResponseWriter, resp *http.Response, snapshotId int64) {
	ct := resp.Header.Get("Content-Type")
	hasContentType := ct != ""

	if hasContentType {
		w.Header().Set("Content-Type", ct)
	} else {
		w.Header().Set("Content-Type", "application/zip")
	}

	cd := resp.Header.Get("Content-Disposition")
	hasContentDisposition := cd != ""

	if hasContentDisposition {
		w.Header().Set("Content-Disposition", cd)
	} else {
		w.Header().Set("Content-Disposition", fmt.Sprintf("attachment; filename=snapshot-%d.zip", snapshotId))
	}

	cl := resp.Header.Get("Content-Length")
	hasContentLength := cl != ""

	if hasContentLength {
		w.Header().Set("Content-Length", cl)
	}
}

// DownloadSnapshotZip proxies a cached ZIP download: requests build from WordPress, then streams the ZIP binary.
func DownloadSnapshotZip(w http.ResponseWriter, r *http.Request) {
	if isSiteServiceMissing(w) {
		return
	}

	siteId, ok := parseSiteIdOrFail(w, r)
	if !ok {
		return
	}

	snapshotId, ok := parseZipDownloadBody(w, r)
	if !ok {
		return
	}

	download, err := Services.SiteService.DownloadSnapshotZip(r.Context(), siteId, snapshotId)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3040", err.Error())
		return
	}
	defer download.Response.Body.Close()

	writeZipDownloadResponse(w, download)
}

// parseZipDownloadBody reads the snapshotId from the POST body.
func parseZipDownloadBody(w http.ResponseWriter, r *http.Request) (int64, bool) {
	var body struct {
		SnapshotId int64
	}

	decodeErr := json.NewDecoder(r.Body).Decode(&body)
	if decodeErr != nil || body.SnapshotId <= 0 {
		respondBadRequest(w, "E1002", "Invalid or missing snapshotId")
		return 0, false
	}

	return body.SnapshotId, true
}

// writeZipDownloadResponse sets headers and streams the ZIP binary to the client.
func writeZipDownloadResponse(w http.ResponseWriter, download *site.SnapshotZipDownload) {
	setZipContentHeaders(w, download)
	setZipMetadataHeaders(w, download.Meta)
	w.WriteHeader(wordpress.HttpStatusOk.Int())
	io.Copy(w, download.Response.Body)
}

// setZipContentHeaders sets Content-Type, Content-Disposition, and Content-Length.
func setZipContentHeaders(w http.ResponseWriter, download *site.SnapshotZipDownload) {
	ct := download.Response.Header.Get("Content-Type")
	hasContentType := ct != ""

	if hasContentType {
		w.Header().Set("Content-Type", ct)
	} else {
		w.Header().Set("Content-Type", "application/zip")
	}

	filename := download.Meta.Filename
	isFilenameEmpty := filename == ""

	if isFilenameEmpty {
		filename = "snapshot.zip"
	}

	w.Header().Set("Content-Disposition", fmt.Sprintf("attachment; filename=%q", filename))

	cl := download.Response.Header.Get("Content-Length")
	hasContentLength := cl != ""

	if hasContentLength {
		w.Header().Set("Content-Length", cl)
	}
}

// setZipMetadataHeaders exposes cache and size metadata as custom headers.
func setZipMetadataHeaders(w http.ResponseWriter, meta *wordpress.SnapshotDownloadResult) {
	if meta.Cached {
		w.Header().Set("X-Snapshot-Cached", "true")
	} else {
		w.Header().Set("X-Snapshot-Cached", "false")
	}

	hasSize := meta.Size > 0
	if hasSize {
		w.Header().Set("X-Snapshot-Size", strconv.FormatInt(meta.Size, 10))
	}
}
