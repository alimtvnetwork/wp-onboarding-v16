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
	syncService,
	"Sync service",
	"id",
	"plugin ID",
	"siteId",
	"site ID",
	"E4002",
	func(ctx context.Context, pluginID, siteID int64) (any, error) {
		return Services.SyncService.CheckSync(ctx, pluginID, siteID)
	},
)

// CheckAllSites checks sync status for all mapped sites
var CheckAllSites = handleActionByID(
	syncService,
	"Sync service",
	"id",
	"plugin ID",
	"E4003",
	func(ctx context.Context, pluginID int64) (any, error) {
		return Services.SyncService.CheckAllSites(ctx, pluginID)
	},
)

// PushSync pushes local changes (including deletions) to the remote site
var PushSync = handleTwoIDs(
	syncService,
	"Sync service",
	"id",
	"plugin ID",
	"siteId",
	"site ID",
	"E4004",
	func(ctx context.Context, pluginID, siteID int64) (any, error) {
		return Services.SyncService.PushSync(ctx, pluginID, siteID)
	},
)

// --- Git Handlers ---

// GitPull performs git pull for a specific plugin
var GitPull = handleActionByID(
	gitService,
	"Git service",
	"id",
	"plugin ID",
	"E5001",
	func(ctx context.Context, pluginID int64) (any, error) {
		return Services.GitService.Pull(ctx, pluginID)
	},
)

// GitPullAll performs git pull for all plugins
var GitPullAll = handleNoArgs(gitService, "Git service", "E5002",
	func(ctx context.Context) (any, error) {
		return Services.GitService.PullAll(ctx)
	},
)

// GitStatus returns git status for a specific plugin
var GitStatus = handleActionByID(
	gitService,
	"Git service",
	"id",
	"plugin ID",
	"E5003",
	func(ctx context.Context, pluginID int64) (any, error) {
		return Services.GitService.Status(ctx, pluginID)
	},
)

// GitCommit commits changes for a specific plugin
func GitCommit(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.GitService, "Git service") {
		return
	}

	pluginID, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	var input struct {
		Message string `json:"message"`
	}
	if !decodeJSON(w, r, &input) {
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
	gitService,
	"Git service",
	"id",
	"plugin ID",
	"E5005",
	func(ctx context.Context, pluginID int64) (any, error) {
		return Services.GitService.Push(ctx, pluginID)
	},
)
