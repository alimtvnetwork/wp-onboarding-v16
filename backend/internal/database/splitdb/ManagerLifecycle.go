// Package splitdb — lifecycle operations: archiving, purging, closing.
package splitdb

import (
	"database/sql"
	"strings"
	"time"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// ArchiveStale archives databases not accessed within maxAge
func (m *DBManager) ArchiveStale(maxAge time.Duration) *apperror.AppError {
	cutoff := time.Now().Add(-maxAge)

	result, err := m.rootDB.Exec(`
		UPDATE Databases 
		SET Status = 'archived', UpdatedAt = CURRENT_TIMESTAMP
		WHERE LastAccessedAt < ? AND Status = 'active'
	`, cutoff)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to archive stale databases")
	}

	affected, _ := result.RowsAffected()
	hasArchivedDatabases := affected > 0

	if hasArchivedDatabases {
		m.log.Info("Archived stale databases", "count", affected, "maxAge", maxAge.String())
	}

	return nil
}

// PurgeArchived deletes archived databases older than retention period
func (m *DBManager) PurgeArchived(retention time.Duration) *apperror.AppError {
	cutoff := time.Now().Add(-retention)

	deleted, appErr := m.deleteArchivedFiles(cutoff)
	if appErr != nil {
		return appErr
	}

	// Remove records
	_, err := m.rootDB.Exec(`
		DELETE FROM Databases 
		WHERE Status = 'archived' AND UpdatedAt < ?
	`, cutoff)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseDelete, "failed to purge archived database records")
	}

	hasPurgedDatabases := deleted > 0

	if hasPurgedDatabases {
		m.log.Info("Purged archived databases", "count", deleted)
	}

	return nil
}

// deleteArchivedFiles removes on-disk files for archived databases.
func (m *DBManager) deleteArchivedFiles(cutoff time.Time) (int, *apperror.AppError) {
	rows, err := m.rootDB.Query(`
		SELECT Path FROM Databases 
		WHERE Status = 'archived' AND UpdatedAt < ?
	`, cutoff)
	if err != nil {
		return 0, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to query archived databases for purge")
	}
	defer rows.Close()

	var deleted int
	for rows.Next() {
		var path string
		rows.Scan(&path)
		fullPath, pathErr := pathutil.Join(m.dataDir, path)
		if pathErr != nil {
			m.log.Warn("Failed to resolve archived database path", "path", path, "error", pathErr)
			continue
		}
		appErr := pathutil.RemoveFile(fullPath, "fullPath")
		if appErr != nil {
			m.log.Warn("Failed to delete archived database file", "path", pathutil.ForDisplay(fullPath), "error", appErr)
		} else {
			deleted++
		}
	}

	return deleted, nil
}

// closeProjectDBs closes all open databases for a project
func (m *DBManager) closeProjectDBs(projectSlug string) {
	prefix := projectSlug + "/"
	for key, db := range m.openDBs {
		if strings.HasPrefix(key, prefix) {
			db.Close()
			delete(m.openDBs, key)
		}
	}
}

// Close closes all open databases
func (m *DBManager) Close() *apperror.AppError {
	m.mu.Lock()
	defer m.mu.Unlock()

	for _, db := range m.openDBs {
		db.Close()
	}
	m.openDBs = make(map[string]*sql.DB)

	err := m.rootDB.Close()
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to close root database")
	}

	return nil
}
