// Package handlers provides sync and git HTTP request handlers
package handlers

import (
	"net/http"
)

// --- Sync Handlers ---

// CheckSync compares local vs remote plugin files
func CheckSync(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SyncService, "Sync service") {
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

	result, err := Services.SyncService.CheckSync(r.Context(), pluginID, siteID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E4002", err.Error())
		return
	}
	respondSuccess(w, result)
}

// CheckAllSites checks sync status for all mapped sites
func CheckAllSites(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SyncService, "Sync service") {
		return
	}

	pluginID, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	result, err := Services.SyncService.CheckAllSites(r.Context(), pluginID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E4003", err.Error())
		return
	}
	respondSuccess(w, result)
}

// PushSync pushes local changes (including deletions) to the remote site
func PushSync(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SyncService, "Sync service") {
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

	result, err := Services.SyncService.PushSync(r.Context(), pluginID, siteID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E4004", err.Error())
		return
	}
	respondSuccess(w, result)
}

// --- Git Handlers ---

// GitPull performs git pull for a specific plugin
func GitPull(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.GitService, "Git service") {
		return
	}

	pluginID, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	result, err := Services.GitService.Pull(r.Context(), pluginID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E5001", err.Error())
		return
	}
	respondSuccess(w, result)
}

// GitPullAll performs git pull for all plugins
func GitPullAll(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.GitService, "Git service") {
		return
	}

	result, err := Services.GitService.PullAll(r.Context())
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E5002", err.Error())
		return
	}
	respondSuccess(w, result)
}

// GitStatus returns git status for a specific plugin
func GitStatus(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.GitService, "Git service") {
		return
	}

	pluginID, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	result, err := Services.GitService.Status(r.Context(), pluginID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E5003", err.Error())
		return
	}
	respondSuccess(w, result)
}

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
		respondError(w, http.StatusBadRequest, "E1003", "Commit message is required")
		return
	}

	result, err := Services.GitService.Commit(r.Context(), pluginID, input.Message)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E5004", err.Error())
		return
	}
	respondSuccess(w, result)
}

// GitPush pushes commits to remote for a specific plugin
func GitPush(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.GitService, "Git service") {
		return
	}

	pluginID, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	result, err := Services.GitService.Push(r.Context(), pluginID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E5005", err.Error())
		return
	}
	respondSuccess(w, result)
}
