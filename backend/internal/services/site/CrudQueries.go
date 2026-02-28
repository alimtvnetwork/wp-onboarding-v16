package site

import (
	"database/sql"
	"time"

	"wp-plugin-publish/internal/models"
)

// SQL query constants (centralized per coding standard).
const siteSelectQuery = `
	SELECT Id, Name, Url, Username, PasswordEncrypted, Category, ConnectionStatus,
	       LastTestedAt, LastSyncAt, CreatedAt, UpdatedAt
	FROM Sites`

const siteSelectByIdQuery = siteSelectQuery + ` WHERE Id = ?`

const siteSelectByUrlQuery = siteSelectQuery + ` WHERE Url = ?`

const siteListQuery = siteSelectQuery + ` ORDER BY Name ASC`

const siteInsertQuery = `
	INSERT INTO Sites (Name, Url, Username, PasswordEncrypted, ConnectionStatus, CreatedAt, UpdatedAt)
	VALUES (?, ?, ?, ?, 'unknown', datetime('now'), datetime('now'))`

const siteDeleteQuery = `DELETE FROM Sites WHERE Id = ?`

const siteUpdateConnectionStatusQuery = `
	UPDATE Sites
	SET ConnectionStatus = ?, LastTestedAt = datetime('now'), UpdatedAt = datetime('now')
	WHERE Id = ?`

const cacheSelectQuery = `
	SELECT PluginsJson, ExpiresAt
	FROM RemotePluginsCache
	WHERE SiteId = ? AND datetime(ExpiresAt) > datetime('now')`

const cacheUpsertQuery = `
	INSERT OR REPLACE INTO RemotePluginsCache (SiteId, PluginsJson, CachedAt, ExpiresAt)
	VALUES (?, ?, datetime('now'), ?)`

const cacheDeleteQuery = `DELETE FROM RemotePluginsCache WHERE SiteId = ?`

// CreateInput holds the data needed to create a site.
type CreateInput struct {
	Name     string
	Url      string
	Username string
	Password string
}

// UpdateInput holds the data for updating a site.
type UpdateInput struct {
	Name     *string
	Url      *string
	Username *string
	Password *string // Only updated if non-nil
}

// siteRaw holds raw scanned columns before parsing into models.Site.
type siteRaw struct {
	site         models.Site
	category     sql.NullString
	lastTestedAt sql.NullString
	lastSyncAt   sql.NullString
	createdAtStr sql.NullString
	updatedAtStr sql.NullString
}

// scanSiteColumns scans columns into siteRaw (shared by Row and Rows scanners).
func scanSiteColumns(dest *siteRaw, scan func(dest ...any) error) error {
	return scan(
		&dest.site.Id,
		&dest.site.Name,
		&dest.site.Url,
		&dest.site.Username,
		&dest.site.PasswordEncrypted,
		&dest.category,
		&dest.site.ConnectionStatus,
		&dest.lastTestedAt,
		&dest.lastSyncAt,
		&dest.createdAtStr,
		&dest.updatedAtStr,
	)
}

// toSite converts a siteRaw into a finalized models.Site.
func (r *siteRaw) toSite() models.Site {
	s := r.site
	if r.category.Valid {
		s.Category = r.category.String
	}
	s.LastTestedAt = parseNullTime(r.lastTestedAt)
	s.LastSyncAt = parseNullTime(r.lastSyncAt)
	s.CreatedAt = parseTime(r.createdAtStr.String)
	s.UpdatedAt = parseTime(r.updatedAtStr.String)
	return s
}

// scanSiteRow is a dbutil.RowScanner[models.Site] for QueryOne.
func scanSiteRow(row *sql.Row) (models.Site, error) {
	var raw siteRaw

	err := scanSiteColumns(&raw, row.Scan)
	if err != nil {
		return models.Site{}, err
	}

	return raw.toSite(), nil
}

// scanSiteRows is a dbutil.RowsScanner[models.Site] for QueryMany.
func scanSiteRows(rows *sql.Rows) (models.Site, error) {
	var raw siteRaw

	err := scanSiteColumns(&raw, rows.Scan)
	if err != nil {
		return models.Site{}, err
	}

	return raw.toSite(), nil
}

// parseNullTime parses a nullable time string.
func parseNullTime(ns sql.NullString) *time.Time {
	isInvalid := !ns.Valid
	isEmpty := ns.String == ""
	if isInvalid || isEmpty {
		return nil
	}
	t := parseTime(ns.String)
	return &t
}

// parseTime parses a time string from SQLite.
func parseTime(s string) time.Time {
	if s == "" {
		return time.Time{}
	}
	t, err := time.Parse("2006-01-02 15:04:05", s)
	if err != nil {
		return time.Time{}
	}
	return t
}
