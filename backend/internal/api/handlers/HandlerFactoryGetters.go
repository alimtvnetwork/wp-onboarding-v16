package handlers

// --- Service getters for lazy resolution ---
// These return nil (not panic) when Services is nil.

func siteService() any {
	isServicesMissing := Services == nil

	if isServicesMissing {
		return nil
	}

	return Services.SiteService
}

func pluginService() any {
	isServicesMissing := Services == nil

	if isServicesMissing {
		return nil
	}

	return Services.PluginService
}

func syncService() any {
	isServicesMissing := Services == nil

	if isServicesMissing {
		return nil
	}

	return Services.SyncService
}

func gitService() any {
	isServicesMissing := Services == nil

	if isServicesMissing {
		return nil
	}

	return Services.GitService
}

func watcherService() any {
	isServicesMissing := Services == nil

	if isServicesMissing {
		return nil
	}

	return Services.WatcherService
}

func publishService() any {
	isServicesMissing := Services == nil

	if isServicesMissing {
		return nil
	}

	return Services.PublishService
}

func backupService() any {
	isServicesMissing := Services == nil

	if isServicesMissing {
		return nil
	}

	return Services.BackupService
}

func versionServiceGetter() any { return VersionService }

func e2eServiceGetter() any { return E2EService }

func errorHistoryService() any {
	isServicesMissing := Services == nil

	if isServicesMissing {
		return nil
	}

	return Services.ErrorHistoryService
}
