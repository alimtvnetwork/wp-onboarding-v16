// Package handlers provides sync and git HTTP request handlers
package handlers

import (
	"context"
	"net/http"

	"wp-plugin-publish/internal/wordpress"
)

// --- Sync Handlers ---

// CheckSync compares local vs remote plugin files
var CheckSync = handleTwoIDs(
	twoIDConfig{
		GetService:  syncService,
		ServiceName: "Sync service",
		Param1Name:  "id",
		Param2Name:  "siteId",
		ErrCode:     "E4002",
	},
	func(ctx context.Context, pluginID, siteID int64) (any, error) {
		return Services.SyncService.CheckSync(ctx, pluginID, siteID)
	},
)

// CheckAllSites checks sync status for all mapped sites
var CheckAllSites = handleActionByID(
	handlerIDConfig{
		GetService:  syncService,
		ServiceName: "Sync service",
		ParamName:   "id",
		ErrCode:     "E4003",
	},
	func(ctx context.Context, pluginID int64) (any, error) {
		return Services.SyncService.CheckAllSites(ctx, pluginID)
	},
)

// PushSync pushes local changes (including deletions) to the remote site
var PushSync = handleTwoIDs(
	twoIDConfig{
		GetService:  syncService,
		ServiceName: "Sync service",
		Param1Name:  "id",
		Param2Name:  "siteId",
		ErrCode:     "E4004",
	},
	func(ctx context.Context, pluginID, siteID int64) (any, error) {
		return Services.SyncService.PushSync(ctx, pluginID, siteID)
	},
)

// --- Git Handlers ---

// GitPull performs git pull for a specific plugin
var GitPull = handleActionByID(
	handlerIDConfig{
		GetService:  gitService,
		ServiceName: "Git service",
		ParamName:   "id",
		ErrCode:     "E5001",
	},
	func(ctx context.Context, pluginID int64) (any, error) {
		return Services.GitService.Pull(ctx, pluginID)
	},
)

// GitPullAll performs git pull for all plugins
var GitPullAll = handleNoArgs(
	noArgsConfig{
		GetService:  gitService,
		ServiceName: "Git service",
		ErrCode:     "E5002",
	},
	func(ctx context.Context) (any, error) {
		return Services.GitService.PullAll(ctx)
	},
)

// GitStatus returns git status for a specific plugin
var GitStatus = handleActionByID(
	handlerIDConfig{
		GetService:  gitService,
		ServiceName: "Git service",
		ParamName:   "id",
		ErrCode:     "E5003",
	},
	func(ctx context.Context, pluginID int64) (any, error) {
		return Services.GitService.Status(ctx, pluginID)
	},
)

// GitCommit commits changes for a specific plugin
func GitCommit(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.GitService, "Git service") {
		return
	}

	pluginID, ok := parseID(w, r, "id")
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

	result, err := Services.GitService.Commit(r.Context(), pluginID, input.Message)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E5004",
			err.Error(),
		)

		return
	}

	respondSuccess(w, result)
}

// GitPush pushes commits to remote for a specific plugin
var GitPush = handleActionByID(
	handlerIDConfig{
		GetService:  gitService,
		ServiceName: "Git service",
		ParamName:   "id",
		ErrCode:     "E5005",
	},
	func(ctx context.Context, pluginID int64) (any, error) {
		return Services.GitService.Push(ctx, pluginID)
	},
)
