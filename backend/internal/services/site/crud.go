package site

import (
	"context"
	"database/sql"
	"fmt"
	"net/url"
	"strings"
	"time"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/dbutil"
)

// SQL query constants (centralized per coding standard).
const siteSelectQuery = `
	SELECT Id, Name, Url, Username, PasswordEncrypted, Category, ConnectionStatus,
	       LastTestedAt, LastSyncAt, CreatedAt, UpdatedAt
	FROM Sites`

const siteSelectByIDQuery = siteSelectQuery + ` WHERE Id = ?`

const siteSelectByURLQuery = siteSelectQuery + ` WHERE Url = ?`

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
	SELECT PluginsJSON, ExpiresAt
	FROM RemotePluginsCache
	WHERE SiteId = ? AND datetime(ExpiresAt) > datetime('now')`

const cacheUpsertQuery = `
	INSERT OR REPLACE INTO RemotePluginsCache (SiteId, PluginsJSON, CachedAt, ExpiresAt)
	VALUES (?, ?, datetime('now'), ?)`

const cacheDeleteQuery = `DELETE FROM RemotePluginsCache WHERE SiteId = ?`

// CreateInput holds the data needed to create a site.
type CreateInput struct {
	Name     string
	URL      string
	Username string
	Password string
}

// UpdateInput holds the data for updating a site.
type UpdateInput struct {
	Name     *string
	URL      *string
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
		&dest.site.ID,
		&dest.site.Name,
		&dest.site.URL,
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
	if err := scanSiteColumns(&raw, row.Scan); err != nil {
		return models.Site{}, err
	}
	return raw.toSite(), nil
}

// scanSiteRows is a dbutil.RowsScanner[models.Site] for QueryMany.
func scanSiteRows(rows *sql.Rows) (models.Site, error) {
	var raw siteRaw
	if err := scanSiteColumns(&raw, rows.Scan); err != nil {
		return models.Site{}, err
	}
	return raw.toSite(), nil
}

// List returns all registered sites.
func (s *Service) List(ctx context.Context) apperror.ResultSlice[models.Site] {
	set := dbutil.QueryMany[models.Site](
		ctx,
		s.dbu,
		siteListQuery,
		scanSiteRows,
	)
	if set.HasError() {
		return apperror.FailSlice[models.Site](set.Error())
	}

	items := set.Items()
	if items == nil {
		items = []models.Site{}
	}
	return apperror.OkSlice(items)
}

// GetByID returns a site by its ID.
func (s *Service) GetByID(ctx context.Context, id int64) apperror.Result[models.Site] {
	result := dbutil.QueryOne[models.Site](
		ctx,
		s.dbu,
		siteSelectByIDQuery,
		scanSiteRow,
		id,
	)
	if result.HasError() {
		return apperror.FailWrap[models.Site](result.Error(), apperror.ErrDBRead, "failed to query site")
	}
	if result.IsEmpty() {
		return apperror.FailNew[models.Site](apperror.ErrNotFound, "site not found")
	}

	return apperror.Ok(result.Value())
}

// GetByURL returns a site by its URL.
func (s *Service) GetByURL(ctx context.Context, siteURL string) apperror.Result[models.Site] {
	normalizedURL := normalizeURL(siteURL)

	result := dbutil.QueryOne[models.Site](
		ctx,
		s.dbu,
		siteSelectByURLQuery,
		scanSiteRow,
		normalizedURL,
	)
	if result.HasError() {
		return apperror.FailWrap[models.Site](result.Error(), apperror.ErrDBRead, "failed to query site by URL")
	}

	// Not found is not an error for this method — return empty Result
	if result.IsEmpty() {
		return apperror.Result[models.Site]{}
	}

	return apperror.Ok(result.Value())
}

// Create adds a new WordPress site.
func (s *Service) Create(ctx context.Context, input CreateInput) apperror.Result[models.Site] {
	if err := s.validateInput(input); err != nil {
		return apperror.FailWrap[models.Site](err, apperror.ErrValidation, "invalid site input")
	}

	normalizedURL := normalizeURL(input.URL)

	existing := s.GetByURL(ctx, normalizedURL)
	if existing.HasError() {
		return apperror.Fail[models.Site](existing.Error())
	}
	if existing.IsDefined() {
		return apperror.FailNew[models.Site](apperror.ErrValidation, "site with this URL already exists")
	}

	encryptedPassword, err := encrypt([]byte(input.Password), s.encryptionKey)
	if err != nil {
		return apperror.FailWrap[models.Site](err, apperror.ErrInternal, "failed to encrypt password")
	}

	res := dbutil.Exec(
		ctx,
		s.dbu,
		siteInsertQuery,
		input.Name,
		normalizedURL,
		input.Username,
		encryptedPassword,
	)
	if res.HasError() {
		return apperror.Fail[models.Site](res.Error())
	}

	s.log.Info("Site created", "id", res.LastInsertID, "name", input.Name, "url", normalizedURL)
	return s.GetByID(ctx, res.LastInsertID)
}

// Update modifies an existing site.
func (s *Service) Update(ctx context.Context, id int64, input UpdateInput) apperror.Result[models.Site] {
	existingResult := s.GetByID(ctx, id)
	if existingResult.HasError() {
		return apperror.Fail[models.Site](existingResult.Error())
	}

	existing := existingResult.Value()
	updates, args := s.buildUpdateFields(ctx, id, input, &existing)
	if len(updates) == 0 {
		return existingResult
	}

	updates = append(updates, "UpdatedAt = datetime('now')")
	args = append(args, id)

	query := fmt.Sprintf("UPDATE Sites SET %s WHERE Id = ?", strings.Join(updates, ", "))
	res := dbutil.Exec(ctx, s.dbu, query, args...)
	if res.HasError() {
		return apperror.Fail[models.Site](res.Error())
	}

	s.log.Info("Site updated", "id", id)
	return s.GetByID(ctx, id)
}

// buildUpdateFields constructs SET clauses and args from non-nil input fields.
func (s *Service) buildUpdateFields(_ context.Context, id int64, input UpdateInput, existing *models.Site) ([]string, []any) {
	var updates []string
	var args []any

	if input.Name != nil && *input.Name != "" {
		updates = append(updates, "Name = ?")
		args = append(args, *input.Name)
	}

	if input.URL != nil && *input.URL != "" {
		normalizedURL := normalizeURL(*input.URL)
		if normalizedURL != existing.URL {
			// URL conflict check is done by caller; trust normalized value here
			updates = append(updates, "Url = ?")
			args = append(args, normalizedURL)
		}
	}

	if input.Username != nil && *input.Username != "" {
		updates = append(updates, "Username = ?")
		args = append(args, *input.Username)
	}

	if input.Password != nil && *input.Password != "" {
		encryptedPassword, err := encrypt([]byte(*input.Password), s.encryptionKey)
		if err == nil {
			updates = append(updates, "PasswordEncrypted = ?")
			args = append(args, encryptedPassword)
			updates = append(updates, "ConnectionStatus = 'unknown'")
		}
	}

	return updates, args
}

// Delete removes a site and its mappings (cascaded by FK).
func (s *Service) Delete(ctx context.Context, id int64) error {
	result := s.GetByID(ctx, id)
	if result.HasError() {
		return result.Error()
	}

	res := dbutil.Exec(
		ctx,
		s.dbu,
		siteDeleteQuery,
		id,
	)
	if res.HasError() {
		return res.Error()
	}
	if res.IsEmpty() {
		return apperror.New(apperror.ErrNotFound, "site not found")
	}

	s.log.Info("Site deleted", "id", id)
	return nil
}

// updateConnectionStatus updates the connection status and last tested time.
func (s *Service) updateConnectionStatus(ctx context.Context, id int64, status string) {
	res := dbutil.Exec(
		ctx,
		s.dbu,
		siteUpdateConnectionStatusQuery,
		status,
		id,
	)
	if res.HasError() {
		s.log.Error("Failed to update connection status", "id", id, "error", res.Error())
	}
}

// validateInput validates the create input.
func (s *Service) validateInput(input CreateInput) error {
	if input.Name == "" {
		return apperror.New(apperror.ErrValidation, "name is required")
	}
	if input.URL == "" {
		return apperror.New(apperror.ErrValidation, "URL is required")
	}
	if input.Username == "" {
		return apperror.New(apperror.ErrValidation, "username is required")
	}
	if input.Password == "" {
		return apperror.New(apperror.ErrValidation, "application password is required")
	}
	if _, err := url.Parse(input.URL); err != nil {
		return apperror.New(apperror.ErrValidation, "invalid URL format")
	}
	return nil
}

// parseNullTime parses a nullable time string.
func parseNullTime(ns sql.NullString) *time.Time {
	if !ns.Valid || ns.String == "" {
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
