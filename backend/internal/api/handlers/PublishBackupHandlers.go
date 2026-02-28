// Package handlers provides publish and backup HTTP request handlers
package handlers

import (
	"context"
	"net/http"
	"strconv"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/enums/publishtype"
	"wp-plugin-publish/internal/services/publish"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/internal/ws"
)

// PublishInput represents the request body for publishing
type PublishInput struct {
	Mode         string   `json:"mode"`         // external key (frontend request body)
	Files        []string `json:"files"`         // external key
	CreateBackup bool     `json:"createBackup"` // external key
	KeepZipFiles bool     `json:"keepZipFiles"` // external key
}

// PublishPlugin publishes plugin changes to a site
func PublishPlugin(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.PublishService, "Publish service") {
		return
	}

	pluginID, ok := parseID(w, r, "id")
	if !ok {
		return
	}

	siteID, ok := parseID(w, r, "siteId")
	if !ok {
		return
	}

	var input PublishInput
	if isBodyInvalid(w, r, &input) {
		return
	}

	mode, parseErr := publishtype.Parse(input.Mode)
	isInvalidMode :=
		parseErr != nil ||
		mode.IsUndefined()
	if isInvalidMode {
		mode = publishtype.Full
	}

	result, appErr := Services.PublishService.Publish(r.Context(), pluginID, siteID, publish.PublishOptions{
		Mode:                mode,
		Files:               input.Files,
		IsCreateBackup:      input.CreateBackup,
		IsKeepZipFiles:      input.KeepZipFiles,
		IsRollbackOnFailure: true,
	})
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E5006",
			appErr.Error(),
		)

		return
	}

	respondSuccess(w, result)
}

// PreviewPublish returns a preview of files that will be published
var PreviewPublish = handleTwoIDs(
	twoIDConfig{GetService: publishService, ServiceName: "Publish service", Param1Name: "id", Param2Name: "siteId", ErrCode: "E5007"},
	func(ctx context.Context, pluginID, siteID int64) (any, error) {
		result, appErr := Services.PublishService.PreviewPublish(ctx, pluginID, siteID)
		if appErr != nil {
			return nil, appErr.CompiledError()
		}

		return result, nil
	},
)

// NOTE: GetFileDiff is defined in files.go

// --- Backup Handlers ---

// GetBackups returns backup history for a plugin
var GetBackups = handleActionByID(
	handlerIDConfig{GetService: backupService, ServiceName: "Backup service", ParamName: "id", ErrCode: "E6001"},
	func(ctx context.Context, pluginID int64) (any, error) {
		result, appErr := Services.BackupService.List(ctx, pluginID)
		if appErr != nil {
			return nil, appErr.CompiledError()
		}

		return result, nil
	},
)

// RestoreBackup restores a plugin from backup
func RestoreBackup(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.BackupService, "Backup service") {
		return
	}

	backupID, ok := parseID(w, r, "id")
	if !ok {
		return
	}

	appErr := Services.BackupService.Restore(r.Context(), backupID)
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E6002",
			appErr.Error(),
		)

		return
	}

	respondSuccess(w, ActionResponse{IsRestored: true})
}

// DeleteBackup removes a backup file
var DeleteBackup = handleDeleteByID(
	handlerIDConfig{GetService: backupService, ServiceName: "Backup service", ParamName: "id", ErrCode: "E6003"},
	func(ctx context.Context, id int64) error {
		appErr := Services.BackupService.Delete(ctx, id)
		if appErr != nil {
			return appErr.CompiledError()
		}

		return nil
	},
)

// --- Version History Handlers ---

// VersionServiceInterface defines version history service methods.
// All methods return *apperror.AppError — never raw error.
type VersionServiceInterface interface {
	GetVersions(ctx context.Context, pluginID int64, siteID *int64, limit int) ([]database.PluginVersionRow, *apperror.AppError)
	GetVersion(ctx context.Context, versionID int64) (*database.PluginVersionRow, *apperror.AppError)
	Rollback(ctx context.Context, versionID int64) (*ws.RollbackCompleteData, *apperror.AppError)
	DeleteVersion(ctx context.Context, versionID int64) *apperror.AppError
}

// VersionService holds the version service instance
var VersionService VersionServiceInterface

// GetPluginVersions returns version history for a plugin
func GetPluginVersions(w http.ResponseWriter, r *http.Request) {
	if VersionService == nil {
		respondSuccess(w, []any{})

		return
	}

	pluginID, ok := parseID(w, r, "id")
	if !ok {
		return
	}

	var siteID *int64

	siteIDStr := r.URL.Query().Get("siteId")
	hasSiteIdParam := siteIDStr != ""

	if hasSiteIdParam {
		parsed, err := strconv.ParseInt(siteIDStr, 10, 64)
		if err == nil {
			siteID = &parsed
		}
	}

	limit := 50

	l := r.URL.Query().Get("limit")
	hasLimitParam := l != ""

	if hasLimitParam {
		parsed, err := strconv.Atoi(l)
		if err == nil && parsed > 0 {
			limit = parsed
		}
	}

	versions, appErr := VersionService.GetVersions(r.Context(), pluginID, siteID, limit)
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E8001",
			appErr.Error(),
		)

		return
	}

	respondSuccess(w, versions)
}

// GetPluginVersion returns a specific version entry
var GetPluginVersion = handleActionByID(
	handlerIDConfig{GetService: versionServiceGetter, ServiceName: "Version service", ParamName: "versionId", ErrCode: "E8002"},
	func(ctx context.Context, id int64) (any, error) {
		result, appErr := VersionService.GetVersion(ctx, id)
		if appErr != nil {
			return nil, appErr.CompiledError()
		}

		return result, nil
	},
)

// RollbackPluginVersion restores a plugin to a previous version
var RollbackPluginVersion = handleActionByID(
	handlerIDConfig{GetService: versionServiceGetter, ServiceName: "Version service", ParamName: "versionId", ErrCode: "E8003"},
	func(ctx context.Context, id int64) (any, error) {
		result, appErr := VersionService.Rollback(ctx, id)
		if appErr != nil {
			return nil, appErr.CompiledError()
		}

		return result, nil
	},
)

// DeletePluginVersion removes a version entry
var DeletePluginVersion = handleDeleteByID(
	handlerIDConfig{GetService: versionServiceGetter, ServiceName: "Version service", ParamName: "versionId", ErrCode: "E8004"},
	func(ctx context.Context, id int64) error {
		appErr := VersionService.DeleteVersion(ctx, id)
		if appErr != nil {
			return appErr.CompiledError()
		}

		return nil
	},
)
