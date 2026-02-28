// Package plugin provides local plugin directory management
package plugin

import (
	"context"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/dbutil"
)

// Config holds service configuration
type Config struct {
	DB     *database.DB
	Logger *logger.Logger
}

// Service provides plugin management operations
type Service struct {
	db  *database.DB
	dbu *dbutil.DB
	log *logger.Logger
}

// New creates a new plugin service instance
func New(cfg Config) *Service {
	return &Service{
		db:  cfg.DB,
		dbu: dbutil.New(cfg.DB.DB),
		log: cfg.Logger,
	}
}

// Interface methods are implemented in:
// - crud.go: List, GetById, Create, Update, Delete, RefreshFileCount
// - scanner.go: ScanDirectory, ValidatePath
// - mappings.go: GetMappings, GetMappingsBySite, CreateMapping, DeleteMapping, UpdateMappingsForPlugin

// ServiceInterface defines the plugin service contract
type ServiceInterface interface {
	// CRUD operations — Result-wrapped returns
	List(ctx context.Context) apperror.ResultSlice[models.Plugin]
	GetById(ctx context.Context, id int64) apperror.Result[models.Plugin]
	Create(ctx context.Context, input CreateInput) apperror.Result[models.Plugin]
	Update(ctx context.Context, id int64, input UpdateInput) apperror.Result[models.Plugin]
	Delete(ctx context.Context, id int64) *apperror.AppError
	RefreshFileCount(ctx context.Context, id int64) *apperror.AppError

	// Directory scanning
	ScanDirectory(ctx context.Context, path string) apperror.Result[ScanResult]
	ValidatePath(ctx context.Context, path string) *apperror.AppError

	// Mappings — Result-wrapped returns
	GetMappings(ctx context.Context, pluginID int64) apperror.ResultSlice[models.PluginMapping]
	GetMappingsBySite(ctx context.Context, siteID int64) apperror.ResultSlice[models.PluginMapping]
	CreateMapping(ctx context.Context, input CreateMappingInput) apperror.Result[models.PluginMapping]
	DeleteMapping(ctx context.Context, mappingID int64) *apperror.AppError
	UpdateMappingsForPlugin(ctx context.Context, pluginID int64, siteIDs []int64, remoteSlug string) *apperror.AppError
	UpdateMappingsForSite(ctx context.Context, siteID int64, pluginIDs []int64) *apperror.AppError
}
