// Package handlers provides publish and backup HTTP request handlers
package handlers

import (
	"context"
	"net/http"
	"strconv"
)

// PublishInput represents the request body for publishing
type PublishInput struct {
	Mode         string   `json:"mode"`         // "full" or "selected"
	Files        []string `json:"files"`         // files to publish (for "selected" mode)
	CreateBackup bool     `json:"createBackup"` // create backup before publish
	KeepZipFiles bool     `json:"keepZipFiles"` // keep ZIP files after publish (for debugging)
}

// PublishPlugin publishes plugin changes to a site
func PublishPlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PublishService, "Publish service") {
		return
	}

	pluginID, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}
	siteID, ok := parseID(w, r, "siteId", "site ID")
	if !ok {
		return
	}

	var input PublishInput
	if !decodeJSON(w, r, &input) {
		return
	}

	if input.Mode == "" {
		input.Mode = "full"
	}

	result, err := Services.PublishService.Publish(r.Context(), pluginID, siteID, input)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E5006", err.Error())
		return
	}
	respondSuccess(w, result)
}

// PreviewPublish returns a preview of files that will be published
func PreviewPublish(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PublishService, "Publish service") {
		return
	}

	pluginID, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}
	siteID, ok := parseID(w, r, "siteId", "site ID")
	if !ok {
		return
	}

	result, err := Services.PublishService.PreviewPublish(r.Context(), pluginID, siteID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E5007", err.Error())
		return
	}
	respondSuccess(w, result)
}

// NOTE: GetFileDiff is defined in files.go

// --- Backup Handlers ---

// GetBackups returns backup history for a plugin
func GetBackups(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.BackupService == nil {
		respondSuccess(w, []interface{}{})
		return
	}

	pluginID, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	backups, err := Services.BackupService.List(r.Context(), pluginID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E6001", err.Error())
		return
	}
	respondSuccess(w, backups)
}

// RestoreBackup restores a plugin from backup
func RestoreBackup(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.BackupService, "Backup service") {
		return
	}

	backupID, ok := parseID(w, r, "id", "backup ID")
	if !ok {
		return
	}

	if err := Services.BackupService.Restore(r.Context(), backupID); err != nil {
		respondError(w, http.StatusInternalServerError, "E6002", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"restored": true})
}

// DeleteBackup removes a backup file
func DeleteBackup(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.BackupService, "Backup service") {
		return
	}

	backupID, ok := parseID(w, r, "id", "backup ID")
	if !ok {
		return
	}

	if err := Services.BackupService.Delete(r.Context(), backupID); err != nil {
		respondError(w, http.StatusBadRequest, "E6003", err.Error())
		return
	}
	respondDeleted(w)
}

// --- Version History Handlers ---

// VersionServiceInterface defines version history service methods
type VersionServiceInterface interface {
	GetVersions(ctx context.Context, pluginID int64, siteID *int64, limit int) (interface{}, error)
	GetVersion(ctx context.Context, versionID int64) (interface{}, error)
	Rollback(ctx context.Context, versionID int64) (interface{}, error)
	DeleteVersion(ctx context.Context, versionID int64) error
}

// VersionService holds the version service instance
var VersionService VersionServiceInterface

// GetPluginVersions returns version history for a plugin
func GetPluginVersions(w http.ResponseWriter, r *http.Request) {
	if VersionService == nil {
		respondSuccess(w, []interface{}{})
		return
	}

	pluginID, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	var siteID *int64
	if siteIDStr := r.URL.Query().Get("siteId"); siteIDStr != "" {
		if parsed, err := strconv.ParseInt(siteIDStr, 10, 64); err == nil {
			siteID = &parsed
		}
	}

	limit := 50
	if l := r.URL.Query().Get("limit"); l != "" {
		if parsed, err := strconv.Atoi(l); err == nil && parsed > 0 {
			limit = parsed
		}
	}

	versions, err := VersionService.GetVersions(r.Context(), pluginID, siteID, limit)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E8001", err.Error())
		return
	}
	respondSuccess(w, versions)
}

// GetPluginVersion returns a specific version entry
func GetPluginVersion(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, VersionService, "Version service") {
		return
	}

	versionID, ok := parseID(w, r, "versionId", "version ID")
	if !ok {
		return
	}

	version, err := VersionService.GetVersion(r.Context(), versionID)
	if err != nil {
		respondError(w, http.StatusNotFound, "E8002", err.Error())
		return
	}
	respondSuccess(w, version)
}

// RollbackPluginVersion restores a plugin to a previous version
func RollbackPluginVersion(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, VersionService, "Version service") {
		return
	}

	versionID, ok := parseID(w, r, "versionId", "version ID")
	if !ok {
		return
	}

	result, err := VersionService.Rollback(r.Context(), versionID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E8003", err.Error())
		return
	}
	respondSuccess(w, result)
}

// DeletePluginVersion removes a version entry
func DeletePluginVersion(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, VersionService, "Version service") {
		return
	}

	versionID, ok := parseID(w, r, "versionId", "version ID")
	if !ok {
		return
	}

	if err := VersionService.DeleteVersion(r.Context(), versionID); err != nil {
		respondError(w, http.StatusBadRequest, "E8004", err.Error())
		return
	}
	respondDeleted(w)
}
