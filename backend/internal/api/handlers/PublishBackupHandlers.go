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
	"wp-plugin-publish/pkg/apperror"
)

// PublishInput represents the request body for publishing
type PublishInput struct {
	Mode                   string   `json:"mode"`                   // external key (frontend request body)
	Files                  []string `json:"files"`                  // external key
	CreateBackup           bool     `json:"createBackup"`           // external key
	KeepZipFiles           bool     `json:"keepZipFiles"`           // external key
	CloudStorageAccountIds []int    `json:"cloudStorageAccountIds"` // external key
}

// PublishPlugin publishes plugin changes to a site
func PublishPlugin(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.PublishService, "Publish service") {
		return
	}

	pluginId, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	siteId, ok := parseId(w, r, "siteId")
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

	result, appErr := Services.PublishService.Publish(r.Context(), pluginId, siteId, publish.PublishOptions{
		Mode:                   mode,
		Files:                  input.Files,
		IsCreateBackup:         input.CreateBackup,
		IsKeepZipFiles:         input.KeepZipFiles,
		IsRollbackOnFailure:    true,
		CloudStorageAccountIds: input.CloudStorageAccountIds,
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
var PreviewPublish = handleTwoIds(
	twoIdConfig{GetService: publishService, ServiceName: "Publish service", Param1Name: "id", Param2Name: "siteId", ErrCode: "E5007"},
	func(ctx context.Context, pluginId, siteId int64) (any, *apperror.AppError) {
		return Services.PublishService.PreviewPublish(ctx, pluginId, siteId)
	},
)

// ComputeDiff returns a true diff between local and remote files
var ComputeDiff = handleTwoIds(
	twoIdConfig{GetService: publishService, ServiceName: "Publish service", Param1Name: "id", Param2Name: "siteId", ErrCode: "E5008"},
	func(ctx context.Context, pluginId, siteId int64) (any, *apperror.AppError) {
		return Services.PublishService.ComputeDiff(ctx, pluginId, siteId)
	},
)

// NOTE: GetFileDiff is defined in files.go

// --- Backup Handlers ---

// GetBackups returns backup history for a plugin
var GetBackups = handleActionById(
	handlerIdConfig{GetService: backupService, ServiceName: "Backup service", ParamName: "id", ErrCode: "E6001"},
	func(ctx context.Context, pluginId int64) (any, *apperror.AppError) {
		return Services.BackupService.List(ctx, pluginId)
	},
)

// RestoreBackup restores a plugin from backup
func RestoreBackup(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.BackupService, "Backup service") {
		return
	}

	backupId, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	appErr := Services.BackupService.Restore(r.Context(), backupId)
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
var DeleteBackup = handleDeleteById(
	handlerIdConfig{GetService: backupService, ServiceName: "Backup service", ParamName: "id", ErrCode: "E6003"},
	func(ctx context.Context, id int64) *apperror.AppError {
		return Services.BackupService.Delete(ctx, id)
	},
)

// --- Version History Handlers ---

// VersionServiceInterface defines version history service methods.
// All methods return *apperror.AppError — never raw error.
type VersionServiceInterface interface {
	GetVersions(ctx context.Context, pluginId int64, siteId *int64, limit int) ([]database.PluginVersionRow, *apperror.AppError)
	GetVersion(ctx context.Context, versionId int64) (*database.PluginVersionRow, *apperror.AppError)
	Rollback(ctx context.Context, versionId int64) (*ws.RollbackCompleteData, *apperror.AppError)
	DeleteVersion(ctx context.Context, versionId int64) *apperror.AppError
}

// VersionService holds the version service instance
var VersionService VersionServiceInterface

// GetPluginVersions returns version history for a plugin
func GetPluginVersions(w http.ResponseWriter, r *http.Request) {
	isServiceMissing := VersionService == nil

	if isServiceMissing {
		respondSuccess(w, []any{})

		return
	}

	pluginId, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	var siteId *int64

	siteIdStr := r.URL.Query().Get("siteId")
	hasSiteIdParam := siteIdStr != ""

	if hasSiteIdParam {
		parsed, err := strconv.ParseInt(siteIdStr, 10, 64)
		if err == nil {
			siteId = &parsed
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

	versions, appErr := VersionService.GetVersions(r.Context(), pluginId, siteId, limit)
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
var GetPluginVersion = handleActionById(
	handlerIdConfig{GetService: versionServiceGetter, ServiceName: "Version service", ParamName: "versionId", ErrCode: "E8002"},
	func(ctx context.Context, id int64) (any, *apperror.AppError) {
		return VersionService.GetVersion(ctx, id)
	},
)

// RollbackPluginVersion restores a plugin to a previous version
var RollbackPluginVersion = handleActionById(
	handlerIdConfig{GetService: versionServiceGetter, ServiceName: "Version service", ParamName: "versionId", ErrCode: "E8003"},
	func(ctx context.Context, id int64) (any, *apperror.AppError) {
		return VersionService.Rollback(ctx, id)
	},
)

// DeletePluginVersion removes a version entry
var DeletePluginVersion = handleDeleteById(
	handlerIdConfig{GetService: versionServiceGetter, ServiceName: "Version service", ParamName: "versionId", ErrCode: "E8004"},
	func(ctx context.Context, id int64) *apperror.AppError {
		return VersionService.DeleteVersion(ctx, id)
	},
)
