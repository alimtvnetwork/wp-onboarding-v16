package handlers

// --- Service getters for lazy resolution ---
// These return the typed interface (or nil) when Services is nil.
// The handler factory configs still accept func() any for the nil-check;
// callers can wrap: func() any { return siteServiceTyped() }

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

// --- Legacy any-returning wrappers (used by handler factory configs) ---
// These exist because the handler factory pattern uses isServiceMissing(w, any, name)
// for nil-checking across different service types.

func siteService() any       { return siteServiceTyped() }
func pluginService() any     { return pluginServiceTyped() }
func syncService() any       { return syncServiceTyped() }
func gitService() any        { return gitServiceTyped() }
func watcherService() any    { return watcherServiceTyped() }
func publishService() any    { return publishServiceTyped() }
func backupService() any     { return backupServiceTyped() }
func errorHistoryService() any { return errorHistoryServiceTyped() }

func versionServiceGetter() any { return VersionService }

func e2eServiceGetter() any { return E2EService }
