package plugin

import (
	"context"
	"database/sql"
	"encoding/json"
	"strings"
	"time"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
)

// List returns all registered plugins
func (s *Service) List(ctx context.Context) ([]models.Plugin, error) {
	s.log.Debug("Listing all plugins")

	rows, err := s.db.QueryContext(ctx, `
		SELECT Id, Name, Path, WatchEnabled, ExcludePatterns, 
		       FileCount, LastScannedAt, CreatedAt, UpdatedAt
		FROM Plugins
		ORDER BY Name ASC
	`)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list plugins")
	}
	defer rows.Close()

	var plugins []models.Plugin
	for rows.Next() {
		var p models.Plugin
		var excludeJSON string
		var lastScannedAt sql.NullString

		err := rows.Scan(
			&p.ID, &p.Name, &p.Path, &p.WatchEnabled, &excludeJSON,
			&p.FileCount, &lastScannedAt, &p.CreatedAt, &p.UpdatedAt,
		)
		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrDatabaseScan, "failed to scan plugin row")
		}

		// Parse exclude patterns JSON
		if excludeJSON != "" {
			json.Unmarshal([]byte(excludeJSON), &p.ExcludePatterns)
		}

		// Parse last scanned timestamp
		if lastScannedAt.Valid {
			t, _ := time.Parse(time.RFC3339, lastScannedAt.String)
			p.LastScannedAt = &t
		}

		// Load mappings for each plugin
		p.Mappings, _ = s.GetMappings(ctx, p.ID)

		plugins = append(plugins, p)
	}

	if plugins == nil {
		plugins = []models.Plugin{}
	}

	return plugins, nil
}

// GetByID returns a plugin by its ID
func (s *Service) GetByID(ctx context.Context, id int64) (*models.Plugin, error) {
	s.log.Debug("Getting plugin by ID", "pluginId", id)

	var p models.Plugin
	var excludeJSON string
	var lastScannedAt sql.NullString

	err := s.db.QueryRowContext(ctx, `
		SELECT Id, Name, Path, WatchEnabled, ExcludePatterns, 
		       FileCount, LastScannedAt, CreatedAt, UpdatedAt
		FROM Plugins
		WHERE Id = ?
	`, id).Scan(
		&p.ID, &p.Name, &p.Path, &p.WatchEnabled, &excludeJSON,
		&p.FileCount, &lastScannedAt, &p.CreatedAt, &p.UpdatedAt,
	)

	if err == sql.ErrNoRows {
		return nil, apperror.New(apperror.ErrNotFound, "plugin not found").
			WithContext("pluginId", id)
	}
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get plugin")
	}

	if excludeJSON != "" {
		json.Unmarshal([]byte(excludeJSON), &p.ExcludePatterns)
	}
	if lastScannedAt.Valid {
		t, _ := time.Parse(time.RFC3339, lastScannedAt.String)
		p.LastScannedAt = &t
	}

	p.Mappings, _ = s.GetMappings(ctx, p.ID)
	return &p, nil
}

// Create registers a new local plugin directory
func (s *Service) Create(ctx context.Context, input CreateInput) (*models.Plugin, error) {
	s.log.Info("Creating plugin", "name", input.Name, "path", input.Path, "forceCreate", input.ForceCreate)

	// Validate path exists and is a valid plugin directory (skip if forceCreate)
	if !input.ForceCreate {
		if err := s.ValidatePath(ctx, input.Path); err != nil {
			return nil, err
		}
	}

	// Check for duplicate path (always check, even with forceCreate)
	var exists int
	err := s.db.QueryRowContext(ctx,
		"SELECT 1 FROM Plugins WHERE Path = ?", input.Path,
	).Scan(&exists)
	if err != sql.ErrNoRows {
		return nil, apperror.New(apperror.ErrDuplicate, "plugin path already registered").
			WithContext("path", input.Path)
	}

	// Scan directory to get file count
	scan, _ := s.ScanDirectory(ctx, input.Path)
	fileCount := 0
	if scan != nil {
		fileCount = scan.FileCount
	}

	// Encode exclude patterns as JSON
	excludeJSON, _ := json.Marshal(input.ExcludePatterns)
	if input.ExcludePatterns == nil {
		excludeJSON = []byte("[]")
	}

	// Insert plugin
	result, err := s.db.ExecContext(ctx, `
		INSERT INTO Plugins (Name, Path, WatchEnabled, ExcludePatterns, FileCount, LastScannedAt, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'), datetime('now'))
	`, input.Name, input.Path, input.WatchEnabled, string(excludeJSON), fileCount)

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to create plugin")
	}

	id, _ := result.LastInsertId()
	s.log.Info("Plugin created", "pluginId", id, "name", input.Name)

	// If git is enabled, save git config
	if input.GitEnabled {
		s.db.ExecContext(ctx, `
			INSERT INTO PluginGitConfig (PluginId, GitEnabled, GitBranch, GitRemoteUrl, BuildEnabled, BuildCommand, UpdatedAt)
			VALUES (?, 1, 'main', ?, ?, ?, datetime('now'))
		`, id, input.GitRemoteURL, input.BuildCommand != "", input.BuildCommand)
	}

	return s.GetByID(ctx, id)
}

// Update modifies an existing plugin
func (s *Service) Update(ctx context.Context, id int64, input UpdateInput) (*models.Plugin, error) {
	s.log.Info("Updating plugin", "pluginId", id)

	// Verify plugin exists
	existing, err := s.GetByID(ctx, id)
	if err != nil {
		return nil, err
	}

	// Build update query dynamically
	var updates []string
	var args []interface{}

	if input.Name != nil {
		updates = append(updates, "Name = ?")
		args = append(args, *input.Name)
	}
	if input.Path != nil {
		// Validate new path
		if err := s.ValidatePath(ctx, *input.Path); err != nil {
			return nil, err
		}
		updates = append(updates, "Path = ?")
		args = append(args, *input.Path)
	}
	if input.WatchEnabled != nil {
		updates = append(updates, "WatchEnabled = ?")
		args = append(args, *input.WatchEnabled)
	}
	if input.ExcludePatterns != nil {
		excludeJSON, _ := json.Marshal(*input.ExcludePatterns)
		updates = append(updates, "ExcludePatterns = ?")
		args = append(args, string(excludeJSON))
	}

	if len(updates) == 0 {
		return existing, nil
	}

	updates = append(updates, "UpdatedAt = datetime('now')")
	args = append(args, id)

	query := "UPDATE Plugins SET " + strings.Join(updates, ", ") + " WHERE Id = ?"
	_, err = s.db.ExecContext(ctx, query, args...)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to update plugin")
	}

	return s.GetByID(ctx, id)
}

// Delete removes a plugin registration
func (s *Service) Delete(ctx context.Context, id int64) error {
	s.log.Info("Deleting plugin", "pluginId", id)

	// Verify plugin exists
	if _, err := s.GetByID(ctx, id); err != nil {
		return err
	}

	// Delete mappings first (foreign key)
	_, err := s.db.ExecContext(ctx, "DELETE FROM PluginMappings WHERE PluginId = ?", id)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to delete plugin mappings")
	}

	// Delete git config
	s.db.ExecContext(ctx, "DELETE FROM PluginGitConfig WHERE PluginId = ?", id)

	// Delete file changes
	s.db.ExecContext(ctx, "DELETE FROM FileChanges WHERE PluginId = ?", id)

	// Delete plugin
	_, err = s.db.ExecContext(ctx, "DELETE FROM Plugins WHERE Id = ?", id)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to delete plugin")
	}

	s.log.Info("Plugin deleted", "pluginId", id)
	return nil
}

// RefreshFileCount updates the file count for a plugin
func (s *Service) RefreshFileCount(ctx context.Context, id int64) error {
	plugin, err := s.GetByID(ctx, id)
	if err != nil {
		return err
	}

	scan, err := s.ScanDirectory(ctx, plugin.Path)
	if err != nil {
		return err
	}

	_, err = s.db.ExecContext(ctx, `
		UPDATE Plugins 
		SET FileCount = ?, LastScannedAt = datetime('now'), UpdatedAt = datetime('now')
		WHERE Id = ?
	`, scan.FileCount, id)

	return err
}
