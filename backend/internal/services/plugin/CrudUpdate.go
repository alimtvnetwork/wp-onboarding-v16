package plugin

import (
	"context"
	"encoding/json"
	"strings"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/dbutil"
)

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
		return apperror.Fail[models.Plugin](res.AppError())
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
		if appErr := s.ValidatePath(ctx, *input.Path); appErr == nil {
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
	if err := s.deletePluginRelated(ctx, id); err != nil {
		return err
	}

	res := dbutil.Exec(ctx, s.dbu, "DELETE FROM Plugins WHERE Id = ?", id)
	if res.HasError() {
		return res.AppError()
	}

	s.log.Info("Plugin deleted", "pluginId", id)
	return nil
}

// deletePluginRelated removes mappings, git config, and file changes.
func (s *Service) deletePluginRelated(ctx context.Context, id int64) error {
	res := dbutil.Exec(ctx, s.dbu, "DELETE FROM PluginMappings WHERE PluginId = ?", id)
	if res.HasError() {
		return res.AppError()
	}

	dbutil.Exec(ctx, s.dbu, "DELETE FROM PluginGitConfig WHERE PluginId = ?", id)
	dbutil.Exec(ctx, s.dbu, "DELETE FROM FileChanges WHERE PluginId = ?", id)
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
		return res.AppError()
	}
	return nil
}
