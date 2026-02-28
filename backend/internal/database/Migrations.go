// Package database - Schema migration runner
package database

import (
	"database/sql"
	"fmt"

	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/pkg/apperror"
)

// Migrate runs all pending migrations
func Migrate(db *DB, log *logger.Logger) error {
	log.Info("Starting database migrations")

	err := ensureMigrationsTable(db, log)
	if err != nil {

		return err
	}

	currentVersion, err := getCurrentMigrationVersion(db, log)
	if err != nil {
		return err
	}

	log.Debug("Current migration version", "version", currentVersion)

	appliedCount, err := applyPendingMigrations(db, log, currentVersion)
	if err != nil {
		return err
	}

	logMigrationSummary(log, appliedCount, currentVersion)
	return nil
}

// ensureMigrationsTable creates the migrations tracking table if it doesn't exist.
func ensureMigrationsTable(db *DB, log *logger.Logger) error {
	_, err := db.Exec(`
		CREATE TABLE IF NOT EXISTS _migrations (
			Version INTEGER PRIMARY KEY,
			Description TEXT NOT NULL,
			AppliedAt TEXT DEFAULT (datetime('now'))
		)
	`)
	if err != nil {
		log.Error("Failed to create migrations table", "error", err)
		return apperror.Wrap(err, apperror.ErrDatabaseMigrate, "failed to create migrations table")
	}
	return nil
}

// getCurrentMigrationVersion returns the highest applied migration version.
func getCurrentMigrationVersion(db *DB, log *logger.Logger) (int, error) {
	var version int
	err := db.QueryRow("SELECT COALESCE(MAX(Version), 0) FROM _migrations").Scan(&version)
	if err != nil {
		log.Error("Failed to get current migration version", "error", err)
		return 0, apperror.Wrap(err, apperror.ErrDatabaseMigrate, "failed to get current migration version")
	}
	return version, nil
}

// applyPendingMigrations runs all migrations above currentVersion, returns count applied.
func applyPendingMigrations(db *DB, log *logger.Logger, currentVersion int) (int, error) {
	applied := 0
	for _, m := range migrations {
		if m.Version <= currentVersion {
			continue
		}
		err := applySingleMigration(db, log, m)
		if err != nil {

			return applied, err
		}
		applied++
	}
	return applied, nil
}

// applySingleMigration runs one migration inside a transaction.
func applySingleMigration(db *DB, log *logger.Logger, m Migration) error {
	log.Info("Applying migration", "version", m.Version, "description", m.Description)

	tx, err := db.Begin()
	if err != nil {
		return wrapMigrationError(err, "failed to begin transaction", m.Version)
	}

	err = executeMigrationTx(tx, m)
	if err != nil {
		tx.Rollback()

		return err
	}

	err = tx.Commit()
	if err != nil {

		return wrapMigrationError(err, "failed to commit migration", m.Version)
	}

	log.Info("Migration completed", "version", m.Version)
	return nil
}

// executeMigrationTx runs the SQL and records the migration within a transaction.
func executeMigrationTx(tx *sql.Tx, m Migration) error {
	_, err := tx.Exec(m.SQL)
	if err != nil {

		return wrapMigrationError(err, "failed to apply migration SQL", m.Version).
			WithDetails(fmt.Sprintf("description=%s", m.Description))
	}

	_, err = tx.Exec("INSERT INTO _migrations (Version, Description) VALUES (?, ?)", m.Version, m.Description)
	if err != nil {

		return wrapMigrationError(err, "failed to record migration", m.Version)
	}
	return nil
}

// wrapMigrationError wraps an error with migration context.
func wrapMigrationError(err error, message string, version int) *apperror.AppError {
	return apperror.Wrap(err, apperror.ErrDatabaseMigrate, message).
		WithDetails(fmt.Sprintf("version=%d", version))
}

// logMigrationSummary logs the final migration result.
func logMigrationSummary(log *logger.Logger, appliedCount, currentVersion int) {
	if appliedCount > 0 {
		log.Info("Migrations applied", "count", appliedCount, "currentVersion", len(migrations))
	} else {
		log.Debug("No new migrations to apply", "currentVersion", currentVersion)
	}
}
