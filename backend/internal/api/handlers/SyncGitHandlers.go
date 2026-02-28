// Package handlers provides sync and git HTTP request handlers
package handlers

import (
	"context"
	"net/http"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// --- Sync Handlers ---

// CheckSync compares local vs remote plugin files
var CheckSync = handleTwoIds(
	twoIdConfig{
		GetService:  syncService,
		ServiceName: "Sync service",
		Param1Name:  "id",
		Param2Name:  "siteId",
		ErrCode:     "E4002",
	},
	func(ctx context.Context, pluginId, siteId int64) (any, *apperror.AppError) {
		return Services.SyncService.CheckSync(ctx, pluginId, siteId)
	},
)

// CheckAllSites checks sync status for all mapped sites
var CheckAllSites = handleActionById(
	handlerIdConfig{
		GetService:  syncService,
		ServiceName: "Sync service",
		ParamName:   "id",
		ErrCode:     "E4003",
	},
	func(ctx context.Context, pluginId int64) (any, *apperror.AppError) {
		return Services.SyncService.CheckAllSites(ctx, pluginId)
	},
)

// PushSync pushes local changes (including deletions) to the remote site
var PushSync = handleTwoIds(
	twoIdConfig{
		GetService:  syncService,
		ServiceName: "Sync service",
		Param1Name:  "id",
		Param2Name:  "siteId",
		ErrCode:     "E4004",
	},
	func(ctx context.Context, pluginId, siteId int64) (any, *apperror.AppError) {
		return Services.SyncService.PushSync(ctx, pluginId, siteId)
	},
)

// --- Git Handlers ---

// GitPull performs git pull for a specific plugin
var GitPull = handleActionById(
	handlerIdConfig{
		GetService:  gitService,
		ServiceName: "Git service",
		ParamName:   "id",
		ErrCode:     "E5001",
	},
	func(ctx context.Context, pluginId int64) (any, *apperror.AppError) {
		return Services.GitService.Pull(ctx, pluginId)
	},
)

// GitPullAll performs git pull for all plugins
var GitPullAll = handleNoArgs(
	noArgsConfig{
		GetService:  gitService,
		ServiceName: "Git service",
		ErrCode:     "E5002",
	},
	func(ctx context.Context) (any, *apperror.AppError) {
		return Services.GitService.PullAll(ctx)
	},
)

// GitStatus returns git status for a specific plugin
var GitStatus = handleActionById(
	handlerIdConfig{
		GetService:  gitService,
		ServiceName: "Git service",
		ParamName:   "id",
		ErrCode:     "E5003",
	},
	func(ctx context.Context, pluginId int64) (any, *apperror.AppError) {
		return Services.GitService.Status(ctx, pluginId)
	},
)

// GitCommit commits changes for a specific plugin
func GitCommit(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.GitService, "Git service") {
		return
	}

	pluginId, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	var input struct {
		Message string `json:"message"` // external key (frontend request body)
	}
	if isBodyInvalid(w, r, &input) {
		return
	}

	if input.Message == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1003",
			"Commit message is required",
		)

		return
	}

	result, appErr := Services.GitService.Commit(r.Context(), pluginId, input.Message)
	if appErr != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E5004",
			appErr.Error(),
		)

		return
	}

	respondSuccess(w, result)
}

// GitPush pushes commits to remote for a specific plugin
var GitPush = handleActionById(
	handlerIdConfig{
		GetService:  gitService,
		ServiceName: "Git service",
		ParamName:   "id",
		ErrCode:     "E5005",
	},
	func(ctx context.Context, pluginId int64) (any, *apperror.AppError) {
		return Services.GitService.Push(ctx, pluginId)
	},
)
