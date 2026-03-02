// Package git provides git operations and build command execution
package git

import (
	"context"
	"sync"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// Config holds git service configuration
type Config struct {
	DB            *database.DB
	Logger        *logger.Logger
	PluginService *plugin.Service
	WSHub         *ws.Hub
	DefaultBranch string
	Timeout       int
}

// Service provides git and build operations
type Service struct {
	db            *database.DB
	log           *logger.Logger
	pluginService *plugin.Service
	wsHub         *ws.Hub
	defaultBranch string
	timeout       int
	mu            sync.Mutex
}

// New creates a new git service
func New(cfg Config) *Service {
	isBranchMissing := cfg.DefaultBranch == ""

	if isBranchMissing {
		cfg.DefaultBranch = "main"
	}

	isTimeoutMissing := cfg.Timeout == 0

	if isTimeoutMissing {
		cfg.Timeout = 60
	}

	return &Service{
		db:            cfg.DB,
		log:           cfg.Logger,
		pluginService: cfg.PluginService,
		wsHub:         cfg.WSHub,
		defaultBranch: cfg.DefaultBranch,
		timeout:       cfg.Timeout,
	}
}

// GetConfig returns git configuration for a plugin
func (s *Service) GetConfig(ctx context.Context, pluginId int64) apperror.Result[PluginGitConfig] {
	var config PluginGitConfig
	config.PluginId = pluginId

	err := s.db.QueryRowContext(ctx, `
		SELECT GitEnabled, GitBranch, GitRemoteUrl, BuildEnabled, BuildCommand
		FROM PluginGitConfig
		WHERE PluginId = ?
	`, pluginId).Scan(
		&config.GitEnabled,
		&config.Branch,
		&config.GitRemoteUrl,
		&config.BuildEnabled,
		&config.BuildCommand,
	)

	if err != nil {
		// Return default config (not an error — just absent row)
		config.GitEnabled = true
		config.Branch = s.defaultBranch
		config.BuildEnabled = false

		return apperror.Ok(config)
	}

	return apperror.Ok(config)
}

// UpdateConfig saves git configuration for a plugin
func (s *Service) UpdateConfig(ctx context.Context, config PluginGitConfig) *apperror.AppError {
	_, err := s.db.ExecContext(ctx, `
		INSERT OR REPLACE INTO PluginGitConfig (PluginId, GitEnabled, GitBranch, GitRemoteUrl, BuildEnabled, BuildCommand, UpdatedAt)
		VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
	`,
		config.PluginId,
		config.GitEnabled,
		config.Branch,
		config.GitRemoteUrl,
		config.BuildEnabled,
		config.BuildCommand,
	)

	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to update git config")
	}

	return nil
}
