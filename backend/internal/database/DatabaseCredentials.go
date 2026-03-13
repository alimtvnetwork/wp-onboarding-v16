package database

import (
	"database/sql"
	"fmt"
	"time"

	"wp-plugin-publish/pkg/apperror"
)

// SiteCredential represents a credential row from the SiteCredentials table.
type SiteCredential struct {
	Id                int64
	SiteId            int64
	AppName           string
	Username          string
	PasswordEncrypted []byte     `json:"-"`
	IsDefault         bool
	ConnectionStatus  string
	LastTestedAt      *time.Time `json:",omitempty"`
	CreatedAt         time.Time
	UpdatedAt         time.Time
}

// SeedCredentialInput bundles parameters for CreateSeedCredential.
type SeedCredentialInput struct {
	SiteId            int64
	AppName           string
	Username          string
	PasswordEncrypted []byte
	IsDefault         bool
}

// CreateSiteCredential inserts a new credential for a site.
func (db *DB) CreateSiteCredential(input SeedCredentialInput) (int64, *apperror.AppError) {
	isDefaultInt := 0

	if input.IsDefault {
		isDefaultInt = 1
	}

	result, err := db.Exec(`
		INSERT INTO SiteCredentials (SiteId, AppName, Username, PasswordEncrypted, IsDefault, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'))
	`, input.SiteId, input.AppName, input.Username, input.PasswordEncrypted, isDefaultInt)

	if err != nil {
		return 0, apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to create site credential")
	}

	id, lastIdErr := result.LastInsertId()
	if lastIdErr != nil {
		return 0, apperror.Wrap(lastIdErr, apperror.ErrDatabaseQuery, "failed to get credential insert ID")
	}

	return id, nil
}

// ListSiteCredentials returns all credentials for a site.
func (db *DB) ListSiteCredentials(siteId int64) ([]SiteCredential, *apperror.AppError) {
	rows, err := db.Query(`
		SELECT Id, SiteId, AppName, Username, PasswordEncrypted, IsDefault, ConnectionStatus, LastTestedAt, CreatedAt, UpdatedAt
		FROM SiteCredentials
		WHERE SiteId = ?
		ORDER BY IsDefault DESC, AppName ASC
	`, siteId)

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list site credentials")
	}
	defer rows.Close()

	return scanCredentialRows(rows)
}

// scanCredentialRows scans all credential rows.
func scanCredentialRows(rows *sql.Rows) ([]SiteCredential, *apperror.AppError) {
	var creds []SiteCredential

	for rows.Next() {
		cred, scanErr := scanSingleCredentialRow(rows)
		if scanErr != nil {
			return nil, scanErr
		}

		creds = append(creds, cred)
	}

	return creds, nil
}

// scanSingleCredentialRow scans one credential from rows.
func scanSingleCredentialRow(rows *sql.Rows) (SiteCredential, *apperror.AppError) {
	var cred SiteCredential
	var isDefaultInt int
	var lastTestedAt, createdAt, updatedAt sql.NullString

	err := rows.Scan(
		&cred.Id, &cred.SiteId, &cred.AppName, &cred.Username,
		&cred.PasswordEncrypted, &isDefaultInt, &cred.ConnectionStatus,
		&lastTestedAt, &createdAt, &updatedAt,
	)

	if err != nil {
		return cred, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to scan credential row")
	}

	cred.IsDefault = isDefaultInt == 1
	cred.LastTestedAt = parseNullTimeDB(lastTestedAt)
	cred.CreatedAt = parseTimeDB(createdAt.String)
	cred.UpdatedAt = parseTimeDB(updatedAt.String)

	return cred, nil
}

// GetDefaultCredential returns the default credential for a site.
func (db *DB) GetDefaultCredential(siteId int64) (*SiteCredential, *apperror.AppError) {
	row := db.QueryRow(`
		SELECT Id, SiteId, AppName, Username, PasswordEncrypted, IsDefault, ConnectionStatus, LastTestedAt, CreatedAt, UpdatedAt
		FROM SiteCredentials
		WHERE SiteId = ? AND IsDefault = 1
		LIMIT 1
	`, siteId)

	return scanCredentialRow(row)
}

// scanCredentialRow scans a single credential from a Row.
// ScanCredentialRowExported is the exported version for use by other packages.
func ScanCredentialRowExported(row *sql.Row) (*SiteCredential, *apperror.AppError) {
	return scanCredentialRow(row)
}

func scanCredentialRow(row *sql.Row) (*SiteCredential, *apperror.AppError) {
	var cred SiteCredential
	var isDefaultInt int
	var lastTestedAt, createdAt, updatedAt sql.NullString

	err := row.Scan(
		&cred.Id, &cred.SiteId, &cred.AppName, &cred.Username,
		&cred.PasswordEncrypted, &isDefaultInt, &cred.ConnectionStatus,
		&lastTestedAt, &createdAt, &updatedAt,
	)

	if err == sql.ErrNoRows {
		return nil, apperror.New(apperror.ErrNotFound, "no credential found")
	}

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to scan credential")
	}

	cred.IsDefault = isDefaultInt == 1
	cred.LastTestedAt = parseNullTimeDB(lastTestedAt)
	cred.CreatedAt = parseTimeDB(createdAt.String)
	cred.UpdatedAt = parseTimeDB(updatedAt.String)

	return &cred, nil
}

// SetDefaultCredential sets a credential as default, unsetting others.
func (db *DB) SetDefaultCredential(siteId, credentialId int64) *apperror.AppError {
	_, err := db.Exec(`UPDATE SiteCredentials SET IsDefault = 0, UpdatedAt = datetime('now') WHERE SiteId = ?`, siteId)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to clear default credentials")
	}

	_, err = db.Exec(`UPDATE SiteCredentials SET IsDefault = 1, UpdatedAt = datetime('now') WHERE Id = ? AND SiteId = ?`, credentialId, siteId)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to set default credential")
	}

	return nil
}

// DeleteSiteCredential deletes a credential by ID.
func (db *DB) DeleteSiteCredential(credentialId int64) *apperror.AppError {
	_, err := db.Exec(`DELETE FROM SiteCredentials WHERE Id = ?`, credentialId)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseDelete, "failed to delete credential")
	}

	return nil
}

// UpdateSiteCredential updates a credential's fields.
func (db *DB) UpdateSiteCredential(credentialId int64, appName, username string, passwordEncrypted []byte) *apperror.AppError {
	_, err := db.Exec(`
		UPDATE SiteCredentials
		SET AppName = ?, Username = ?, PasswordEncrypted = ?, UpdatedAt = datetime('now')
		WHERE Id = ?
	`, appName, username, passwordEncrypted, credentialId)

	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseUpdate, "failed to update credential")
	}

	return nil
}

// CredentialExistsBySiteAndAppName checks if a credential already exists.
func (db *DB) CredentialExistsBySiteAndAppName(siteId int64, appName string) (bool, *apperror.AppError) {
	var count int

	err := db.QueryRow(
		`SELECT COUNT(*) FROM SiteCredentials WHERE SiteId = ? AND AppName = ?`,
		siteId, appName,
	).Scan(&count)

	if err != nil {
		return false, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to check credential existence")
	}

	return count > 0, nil
}

// parseNullTimeDB parses a nullable time string from SQLite.
func parseNullTimeDB(ns sql.NullString) *time.Time {
	isInvalid := !ns.Valid || ns.String == ""

	if isInvalid {
		return nil
	}

	t := parseTimeDB(ns.String)

	return &t
}

// parseTimeDB parses a time string from SQLite.
func parseTimeDB(s string) time.Time {
	isEmpty := s == ""

	if isEmpty {
		return time.Time{}
	}

	t, err := time.Parse("2006-01-02 15:04:05", s)
	if err != nil {
		fmt.Println("parseTimeDB error:", err, "input:", s)

		return time.Time{}
	}

	return t
}
