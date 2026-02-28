// Package publishhistory — SQL query constants.
package publishhistory

const selectHistorySQL = `SELECT
	Id,
	PluginId,
	PluginName,
	SiteId,
	SiteName,
	SiteUrl,
	SessionId,
	Status,
	Mode,
	FilesUpdated,
	ActivationStatus,
	RollbackStatus,
	RollbackMessage,
	ErrorMessage,
	DurationMs,
	CreatedAt
FROM PublishHistory`

const insertHistorySQL = `INSERT INTO PublishHistory (
	PluginId,
	PluginName,
	SiteId,
	SiteName,
	SiteUrl,
	SessionId,
	Status,
	Mode,
	FilesUpdated,
	ActivationStatus,
	RollbackStatus,
	RollbackMessage,
	ErrorMessage,
	DurationMs
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`

const statsSQL = `SELECT
	COUNT(*),
	COALESCE(SUM(CASE WHEN Status = 'success' THEN 1 ELSE 0 END), 0),
	COALESCE(SUM(CASE WHEN Status = 'failed' THEN 1 ELSE 0 END), 0),
	COALESCE(SUM(CASE WHEN Status = 'partial' THEN 1 ELSE 0 END), 0),
	COALESCE(AVG(DurationMs), 0),
	COALESCE(SUM(FilesUpdated), 0),
	MAX(CreatedAt)
FROM PublishHistory`
