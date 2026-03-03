package database

import (
	"database/sql"
	"embed"
	"fmt"
	"sort"
	"strings"
)

//go:embed migrations/*.sql
var migrationFS embed.FS

// Migrate runs all pending SQL migrations in order.
// It tracks applied migrations in a `schema_migrations` table.
func Migrate(db *sql.DB) error {
	initErr := ensureMigrationsTable(db)
	if initErr != nil {
		return fmt.Errorf("ensure migrations table: %w", initErr)
	}

	applied, appliedErr := getAppliedMigrations(db)
	if appliedErr != nil {
		return fmt.Errorf("get applied migrations: %w", appliedErr)
	}

	files, readErr := listMigrationFiles()
	if readErr != nil {
		return fmt.Errorf("list migration files: %w", readErr)
	}

	for _, file := range files {
		_, isAlreadyApplied := applied[file]
		if isAlreadyApplied {
			continue
		}

		applyErr := applyMigration(db, file)
		if applyErr != nil {
			return fmt.Errorf("apply migration %s: %w", file, applyErr)
		}
	}

	return nil
}

// ensureMigrationsTable creates the schema_migrations tracking table.
func ensureMigrationsTable(db *sql.DB) error {
	query := `CREATE TABLE IF NOT EXISTS schema_migrations (
		name       TEXT    NOT NULL UNIQUE,
		applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
	)`

	_, execErr := db.Exec(query)

	return execErr
}

// getAppliedMigrations returns a set of already-applied migration filenames.
func getAppliedMigrations(db *sql.DB) (map[string]bool, error) {
	rows, queryErr := db.Query("SELECT name FROM schema_migrations")
	if queryErr != nil {
		return nil, queryErr
	}
	defer rows.Close()

	applied := make(map[string]bool)

	for rows.Next() {
		var name string
		scanErr := rows.Scan(&name)

		if scanErr != nil {
			return nil, scanErr
		}

		applied[name] = true
	}

	return applied, rows.Err()
}

// listMigrationFiles returns sorted migration filenames from the embedded FS.
func listMigrationFiles() ([]string, error) {
	entries, readErr := migrationFS.ReadDir("migrations")
	if readErr != nil {
		return nil, readErr
	}

	var files []string

	for _, entry := range entries {
		isSQLFile := !entry.IsDir() && strings.HasSuffix(entry.Name(), ".sql")

		if isSQLFile {
			files = append(files, entry.Name())
		}
	}

	sort.Strings(files)

	return files, nil
}

// applyMigration reads and executes a single migration file within a transaction.
func applyMigration(db *sql.DB, filename string) error {
	content, readErr := migrationFS.ReadFile("migrations/" + filename)
	if readErr != nil {
		return fmt.Errorf("read file: %w", readErr)
	}

	tx, txErr := db.Begin()
	if txErr != nil {
		return fmt.Errorf("begin transaction: %w", txErr)
	}

	_, execErr := tx.Exec(string(content))
	if execErr != nil {
		tx.Rollback() //nolint:errcheck
		return fmt.Errorf("exec SQL: %w", execErr)
	}

	_, recordErr := tx.Exec("INSERT INTO schema_migrations (name) VALUES (?)", filename)
	if recordErr != nil {
		tx.Rollback() //nolint:errcheck
		return fmt.Errorf("record migration: %w", recordErr)
	}

	return tx.Commit()
}
