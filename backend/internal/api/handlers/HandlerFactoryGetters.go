package handlers

// --- Typed service getters for lazy resolution ---
// These return the typed interface (or nil) when Services is nil.

func siteServiceTyped() SiteServiceInterface {
	if Services == nil {
		return nil
	}

	return Services.SiteService
}

func pluginServiceTyped() PluginServiceInterface {
	if Services == nil {
		return nil
	}

	return Services.PluginService
}

func syncServiceTyped() SyncServiceInterface {
	if Services == nil {
		return nil
	}

	return Services.SyncService
}

func gitServiceTyped() GitServiceInterface {
	if Services == nil {
		return nil
	}

	return Services.GitService
}

func watcherServiceTyped() WatcherServiceInterface {
	if Services == nil {
		return nil
	}

	return Services.WatcherService
}

func publishServiceTyped() PublishServiceInterface {
	if Services == nil {
		return nil
	}

	return Services.PublishService
}

func backupServiceTyped() BackupServiceInterface {
	if Services == nil {
		return nil
	}

	return Services.BackupService
}

func errorHistoryServiceTyped() ErrorHistoryServiceInterface {
	if Services == nil {
		return nil
	}

	return Services.ErrorHistoryService
}

// --- Bool readiness checks (used by handler factory configs) ---
// These replace the legacy func() any wrappers.

func isSiteServiceReady() bool           { return siteServiceTyped() != nil }
func isPluginServiceReady() bool          { return pluginServiceTyped() != nil }
func isSyncServiceReady() bool            { return syncServiceTyped() != nil }
func isGitServiceReady() bool             { return gitServiceTyped() != nil }
func isWatcherServiceReady() bool         { return watcherServiceTyped() != nil }
func isPublishServiceReady() bool         { return publishServiceTyped() != nil }
func isBackupServiceReady() bool          { return backupServiceTyped() != nil }
func isErrorHistoryServiceReady() bool    { return errorHistoryServiceTyped() != nil }
func isVersionServiceReady() bool         { return VersionService != nil }
func isE2EServiceReady() bool             { return E2EService != nil }
