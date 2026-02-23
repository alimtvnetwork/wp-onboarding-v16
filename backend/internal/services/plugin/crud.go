package plugin

import (
	"context"
	"database/sql"
	"encoding/json"
	"strings"
	"time"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/dbutil"
)

// SQL query constants (centralized per coding standard).
const pluginSelectQuery = `
	SELECT p.Id, p.Name, p.Path, p.Category, p.WatchEnabled, p.AutoPublish, p.ExcludePatterns, 
	       p.FileCount, p.LastScannedAt, p.CreatedAt, p.UpdatedAt,
	       COALESCE(g.GitEnabled, 0) as GitEnabled
	FROM Plugins p
	LEFT JOIN PluginGitConfig g ON g.PluginId = p.Id`

const pluginSelectByIDQuery = pluginSelectQuery + ` WHERE p.Id = ?`

const pluginSelectByPathQuery = `SELECT Id FROM Plugins WHERE Path = ?`

const pluginInsertQuery = `
	INSERT INTO Plugins (Name, Path, Category, WatchEnabled, AutoPublish, ExcludePatterns, FileCount, LastScannedAt, CreatedAt, UpdatedAt)
	VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'), datetime('now'))`

const pluginGitInsertQuery = `
	INSERT INTO PluginGitConfig (PluginId, GitEnabled, GitBranch, GitRemoteUrl, BuildEnabled, BuildCommand, UpdatedAt)
	VALUES (?, 1, 'main', ?, ?, ?, datetime('now'))`

const pluginUpdateFileCountQuery = `
	UPDATE Plugins SET FileCount = ?, LastScannedAt = datetime('now'), UpdatedAt = datetime('now') WHERE Id = ?`

// pluginRaw holds raw scanned columns before parsing into models.Plugin.
type pluginRaw struct {
	plugin       models.Plugin
	category     sql.NullString
	excludeJSON  string
	lastScanned  sql.NullString
	createdAtStr sql.NullString
	updatedAtStr sql.NullString
	autoPublish  int
	gitEnabled   int
}

// scanPluginColumns scans columns into pluginRaw (shared by Row and Rows scanners).
func scanPluginColumns(dest *pluginRaw, scan func(dest ...any) error) error {
	return scan(
		&dest.plugin.Id,
		&dest.plugin.Name,
		&dest.plugin.Path,
		&dest.category,
		&dest.plugin.WatchEnabled,
		&dest.autoPublish,
		&dest.excludeJSON,
		&dest.plugin.FileCount,
		&dest.lastScanned,
		&dest.createdAtStr,
		&dest.updatedAtStr,
		&dest.gitEnabled,
	)
}

// toPlugin converts a pluginRaw into a finalized models.Plugin.
func (r *pluginRaw) toPlugin() models.Plugin {
	p := r.plugin
	p.AutoPublish = r.autoPublish == 1
	p.GitEnabled = r.gitEnabled == 1

	if r.category.Valid {
		p.Category = r.category.String
	}

	p.CreatedAt = parseDateTime(r.createdAtStr.String)
	p.UpdatedAt = parseDateTime(r.updatedAtStr.String)

	if r.excludeJSON != "" {
		json.Unmarshal([]byte(r.excludeJSON), &p.ExcludePatterns)
	}
	if r.lastScanned.Valid {
		t, _ := time.Parse(time.RFC3339, r.lastScanned.String)
		p.LastScannedAt = &t
	}
	return p
}

// scanPluginRow is a dbutil.RowScanner[models.Plugin] for QueryOne.
func scanPluginRow(row *sql.Row) (models.Plugin, error) {
	var raw pluginRaw
	if err := scanPluginColumns(&raw, row.Scan); err != nil {
		return models.Plugin{}, err
	}
	return raw.toPlugin(), nil
}

// scanPluginRows is a dbutil.RowsScanner[models.Plugin] for QueryMany.
func scanPluginRows(rows *sql.Rows) (models.Plugin, error) {
	var raw pluginRaw
	if err := scanPluginColumns(&raw, rows.Scan); err != nil {
		return models.Plugin{}, err
	}
	return raw.toPlugin(), nil
}

// List returns all registered plugins.
func (s *Service) List(ctx context.Context) apperror.ResultSlice[models.Plugin] {
	s.log.Debug("Listing all plugins")
	query := pluginSelectQuery + ` ORDER BY p.Name ASC`
	set := dbutil.QueryMany[models.Plugin](
		ctx,
		s.dbu,
		query,
		scanPluginRows,
	)

	if set.HasError() {
		return apperror.FailSlice[models.Plugin](set.Error())
	}

	plugins := set.Items()
	if plugins == nil {
		plugins = []models.Plugin{}
	}

	plugins = s.loadMappingsForAll(ctx, plugins)

	return apperror.OkSlice(plugins)
}

// loadMappingsForAll attaches mappings to each plugin in the slice.
func (s *Service) loadMappingsForAll(ctx context.Context, plugins []models.Plugin) []models.Plugin {
	for i := range plugins {
		result := s.GetMappings(ctx, plugins[i].Id)
		if result.IsSafe() {
			plugins[i].Mappings = result.Items()
		}
	}
	return plugins
}

// GetById returns a plugin by its Id.
func (s *Service) GetById(ctx context.Context, id int64) apperror.Result[models.Plugin] {
	s.log.Debug("Getting plugin by Id", "pluginId", id)

	result := dbutil.QueryOne[models.Plugin](
		ctx,
		s.dbu,
		pluginSelectByIDQuery,
		scanPluginRow,
		id,
	)
	if result.HasError() {
		return apperror.FailWrap[models.Plugin](result.Error(), apperror.ErrDatabaseQuery, "get plugin by Id")
	}
	if result.IsEmpty() {
		return apperror.FailNew[models.Plugin](apperror.ErrNotFound, "plugin not found")
	}

	p := result.Value()
	mappings := s.GetMappings(ctx, p.Id)
	if mappings.IsSafe() {
		p.Mappings = mappings.Items()
	}

	return apperror.Ok(p)
}

// parseDateTime parses SQLite datetime strings into time.Time.
func parseDateTime(s string) time.Time {
	if s == "" {
		return time.Time{}
	}
	if t, err := time.Parse("2006-01-02 15:04:05", s); err == nil {
		return t
	}
	if t, err := time.Parse(time.RFC3339, s); err == nil {
		return t
	}
	return time.Time{}
}

// Create registers a new local plugin directory.
func (s *Service) Create(ctx context.Context, input CreateInput) apperror.Result[models.Plugin] {
	s.log.Info("Creating plugin", "name", input.Name, "path", input.Path, "forceCreate", input.ForceCreate)

	if err := s.validateCreatePath(ctx, input); err != nil {
		return apperror.FailWrap[models.Plugin](err, apperror.ErrValidation, "plugin path validation failed")
	}

	existing, done := s.checkDuplicatePath(ctx, input)
	if done {
		return existing
	}

	return s.insertPlugin(ctx, input)
}

// validateCreatePath validates the path unless forceCreate is set.
func (s *Service) validateCreatePath(ctx context.Context, input CreateInput) error {
	if input.ForceCreate {
		return nil
	}
	return s.ValidatePath(ctx, input.Path)
}

// checkDuplicatePath returns the existing plugin if the path is already registered.
// The bool indicates whether the caller should return the result (true = handled).
func (s *Service) checkDuplicatePath(ctx context.Context, input CreateInput) (apperror.Result[models.Plugin], bool) {
	result := dbutil.QueryOne[int64](
		ctx,
		s.dbu,
		pluginSelectByPathQuery,
		scanID,
		input.Path,
	)
	if result.HasError() {
		return apperror.Fail[models.Plugin](result.Error()), true
	}
	if result.IsEmpty() {
		return apperror.Result[models.Plugin]{}, false
	}

	if input.ForceCreate {
		s.log.Info("Plugin path already registered; returning existing", "pluginId", result.Value(), "path", input.Path)
		return s.GetById(ctx, result.Value()), true
	}

	return apperror.FailNew[models.Plugin](apperror.ErrDuplicate, "plugin path already registered"), true
}

// scanID scans a single int64 from a row.
func scanID(row *sql.Row) (int64, error) {
	var id int64
	err := row.Scan(&id)
	return id, err
}

// insertPlugin inserts a new plugin and optional git config.
func (s *Service) insertPlugin(ctx context.Context, input CreateInput) apperror.Result[models.Plugin] {
	scanResult := s.ScanDirectory(ctx, input.Path)
	fileCount := 0
	if scanResult.IsSafe() {
		fileCount = scanResult.Value().FileCount
	}

	excludeJSON := s.encodeExcludePatterns(input.ExcludePatterns)

	res := dbutil.Exec(
		ctx,
		s.dbu,
		pluginInsertQuery,
		input.Name,
		input.Path,
		input.Category,
		input.WatchEnabled,
		input.AutoPublish,
		excludeJSON,
		fileCount,
	)
	if res.HasError() {
		return apperror.Fail[models.Plugin](res.Error())
	}

	s.log.Info("Plugin created", "pluginId", res.LastInsertId, "name", input.Name)
	s.insertGitConfig(ctx, res.LastInsertId, input)
	return s.GetById(ctx, res.LastInsertId)
}

// encodeExcludePatterns marshals exclude patterns to JSON string.
func (s *Service) encodeExcludePatterns(patterns []string) string {
	if patterns == nil {
		return "[]"
	}
	b, _ := json.Marshal(patterns)
	return string(b)
}

// insertGitConfig saves git config if git is enabled.
func (s *Service) insertGitConfig(ctx context.Context, pluginID int64, input CreateInput) {
	if input.GitEnabled {
		dbutil.Exec(
			ctx,
			s.dbu,
			pluginGitInsertQuery,
			pluginID,
			input.GitRemoteURL,
			input.BuildCommand != "",
			input.BuildCommand,
		)
	}
}

// Update modifies an existing plugin.
func (s *Service) Update(ctx context.Context, id int64, input UpdateInput) apperror.Result[models.Plugin] {
	s.log.Info("Updating plugin", "pluginId", id)

	existing := s.GetById(ctx, id)
	if existing.HasError() {
		return existing
	}

	updates, args := s.buildUpdateFields(ctx, input)
	if len(updates) == 0 {
		return existing
	}

	updates = append(updates, "UpdatedAt = datetime('now')")
	args = append(args, id)

	query := "UPDATE Plugins SET " + strings.Join(updates, ", ") + " WHERE Id = ?"
	res := dbutil.Exec(ctx, s.dbu, query, args...)
	if res.HasError() {
		return apperror.Fail[models.Plugin](res.Error())
	}

	return s.GetById(ctx, id)
}

// buildUpdateFields constructs SET clauses and args from non-nil input fields.
func (s *Service) buildUpdateFields(ctx context.Context, input UpdateInput) ([]string, []any) {
	var updates []string
	var args []any

	if input.Name != nil {
		updates = append(updates, "Name = ?")
		args = append(args, *input.Name)
	}
	if input.Path != nil {
		if err := s.ValidatePath(ctx, *input.Path); err == nil {
			updates = append(updates, "Path = ?")
			args = append(args, *input.Path)
		}
	}
	appendOptionalFields(&updates, &args, input)
	return updates, args
}

// appendOptionalFields appends WatchEnabled, AutoPublish, Category, ExcludePatterns.
func appendOptionalFields(updates *[]string, args *[]any, input UpdateInput) {
	if input.WatchEnabled != nil {
		*updates = append(*updates, "WatchEnabled = ?")
		*args = append(*args, *input.WatchEnabled)
	}
	if input.AutoPublish != nil {
		*updates = append(*updates, "AutoPublish = ?")
		*args = append(*args, *input.AutoPublish)
	}
	if input.Category != nil {
		*updates = append(*updates, "Category = ?")
		*args = append(*args, *input.Category)
	}
	if input.ExcludePatterns != nil {
		excludeJSON, _ := json.Marshal(*input.ExcludePatterns)
		*updates = append(*updates, "ExcludePatterns = ?")
		*args = append(*args, string(excludeJSON))
	}
}

// Delete removes a plugin registration.
func (s *Service) Delete(ctx context.Context, id int64) error {
	s.log.Info("Deleting plugin", "pluginId", id)

	result := s.GetById(ctx, id)
	if result.HasError() {
		return result.AppError()
	}

	return s.deletePluginCascade(ctx, id)
}

// deletePluginCascade removes all related records then the plugin itself.
func (s *Service) deletePluginCascade(ctx context.Context, id int64) error {
	res := dbutil.Exec(
		ctx,
		s.dbu,
		"DELETE FROM PluginMappings WHERE PluginId = ?",
		id,
	)
	if res.HasError() {
		return res.Error()
	}

	dbutil.Exec(
		ctx,
		s.dbu,
		"DELETE FROM PluginGitConfig WHERE PluginId = ?",
		id,
	)
	dbutil.Exec(
		ctx,
		s.dbu,
		"DELETE FROM FileChanges WHERE PluginId = ?",
		id,
	)

	res = dbutil.Exec(
		ctx,
		s.dbu,
		"DELETE FROM Plugins WHERE Id = ?",
		id,
	)
	if res.HasError() {
		return res.Error()
	}

	s.log.Info("Plugin deleted", "pluginId", id)
	return nil
}

// RefreshFileCount updates the file count for a plugin.
func (s *Service) RefreshFileCount(ctx context.Context, id int64) error {
	result := s.GetById(ctx, id)
	if result.HasError() {
		return result.AppError()
	}

	plugin := result.Value()
	scan := s.ScanDirectory(ctx, plugin.Path)
	if scan.HasError() {
		return scan.AppError()
	}

	res := dbutil.Exec(
		ctx,
		s.dbu,
		pluginUpdateFileCountQuery,
		scan.Value().FileCount,
		id,
	)
	if res.HasError() {
		return res.Error()
	}
	return nil
}
