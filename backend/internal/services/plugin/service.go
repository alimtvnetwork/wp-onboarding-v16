// Package plugin provides local plugin directory management
package plugin

import (
	"context"
	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
)

// Config holds service configuration
type Config struct {
	DB     *database.DB
	Logger *logger.Logger
}

// Service provides plugin management operations
type Service struct {
	db  *database.DB
	log *logger.Logger
}

// New creates a new plugin service instance
func New(cfg Config) *Service {
	return &Service{
		db:  cfg.DB,
		log: cfg.Logger,
	}
}

// List returns all registered plugins
func (s *Service) List(ctx context.Context) ([]models.Plugin, error) {
	// TODO: Implement database query
	return []models.Plugin{}, nil
}

// GetByID returns a plugin by its ID
func (s *Service) GetByID(ctx context.Context, id int64) (*models.Plugin, error) {
	// TODO: Implement database query
	return nil, nil
}

// Create registers a new local plugin directory
func (s *Service) Create(ctx context.Context, plugin *models.Plugin) error {
	// TODO: Validate path exists and contains valid plugin
	// TODO: Scan directory for files
	return nil
}

// Update modifies an existing plugin
func (s *Service) Update(ctx context.Context, plugin *models.Plugin) error {
	// TODO: Implement update logic
	return nil
}

// Delete removes a plugin registration
func (s *Service) Delete(ctx context.Context, id int64) error {
	// TODO: Implement delete logic
	return nil
}

// ScanDirectory scans a plugin directory and returns file information
func (s *Service) ScanDirectory(ctx context.Context, id int64) (*ScanResult, error) {
	// TODO: Implement directory scanning
	return &ScanResult{}, nil
}

// GetMappings returns all site mappings for a plugin
func (s *Service) GetMappings(ctx context.Context, pluginID int64) ([]models.PluginMapping, error) {
	// TODO: Implement database query
	return []models.PluginMapping{}, nil
}

// CreateMapping creates a new plugin-site mapping
func (s *Service) CreateMapping(ctx context.Context, mapping *models.PluginMapping) error {
	// TODO: Implement create logic
	return nil
}

// DeleteMapping removes a plugin-site mapping
func (s *Service) DeleteMapping(ctx context.Context, mappingID int64) error {
	// TODO: Implement delete logic
	return nil
}

// ScanResult represents the result of a directory scan
type ScanResult struct {
	FileCount int      `json:"fileCount"`
	TotalSize int64    `json:"totalSize"`
	Files     []string `json:"files,omitempty"`
}
