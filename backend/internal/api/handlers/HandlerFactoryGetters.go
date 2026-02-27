package handlers

// --- Service getters for lazy resolution ---
// These return nil (not panic) when Services is nil.

func siteService() any {
	if Services == nil {
		return nil
	}
	return Services.SiteService
}

func pluginService() any {
	if Services == nil {
		return nil
	}
	return Services.PluginService
}

func syncService() any {
	if Services == nil {
		return nil
	}
	return Services.SyncService
}

func gitService() any {
	if Services == nil {
		return nil
	}
	return Services.GitService
}

func watcherService() any {
	if Services == nil {
		return nil
	}
	return Services.WatcherService
}

func publishService() any {
	if Services == nil {
		return nil
	}
	return Services.PublishService
}

func backupService() any {
	if Services == nil {
		return nil
	}
	return Services.BackupService
}

func versionServiceGetter() any { return VersionService }

func e2eServiceGetter() any { return E2EService }

func errorHistoryService() any {
	if Services == nil {
		return nil
	}
	return Services.ErrorHistoryService
}
