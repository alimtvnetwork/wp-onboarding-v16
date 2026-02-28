// Package handlers - Publish and Backup service interfaces and adapters
package handlers

import (
	"context"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/backup"
	"wp-plugin-publish/internal/services/publish"
	"wp-plugin-publish/pkg/apperror"
)

// PublishServiceInterface defines publish service methods.
// All methods return *apperror.AppError — never raw error.
type PublishServiceInterface interface {
	Publish(ctx context.Context, pluginId, siteId int64, opts publish.PublishOptions) (*publish.PublishResult, *apperror.AppError)
	PublishFiles(ctx context.Context, pluginId, siteId int64, files []string) (*publish.PublishResult, *apperror.AppError)
	PreviewPublish(ctx context.Context, pluginId, siteId int64) (*publish.PublishPreviewResult, *apperror.AppError)
	GetFileDiff(ctx context.Context, pluginId, siteId int64, filePath string) (*publish.FileDiffResult, *apperror.AppError)
}

// BackupServiceInterface defines backup service methods for HTTP handlers.
// All methods return *apperror.AppError — never raw error.
type BackupServiceInterface interface {
	List(ctx context.Context, pluginId int64) ([]models.Backup, *apperror.AppError)
	Create(ctx context.Context, pluginId, siteId int64) (*models.Backup, *apperror.AppError)
	Restore(ctx context.Context, backupId int64) *apperror.AppError
	Delete(ctx context.Context, backupId int64) *apperror.AppError
}

// PublishServiceAdapter wraps *publish.Service to implement PublishServiceInterface
type PublishServiceAdapter struct {
	*publish.Service
}

func (a *PublishServiceAdapter) Publish(ctx context.Context, pluginId, siteId int64, opts publish.PublishOptions) (*publish.PublishResult, *apperror.AppError) {
	result := a.Service.Publish(ctx, pluginId, siteId, opts)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *PublishServiceAdapter) PublishFiles(ctx context.Context, pluginId, siteId int64, files []string) (*publish.PublishResult, *apperror.AppError) {
	result := a.Service.PublishFiles(ctx, pluginId, siteId, files)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *PublishServiceAdapter) PreviewPublish(ctx context.Context, pluginId, siteId int64) (*publish.PublishPreviewResult, *apperror.AppError) {
	result := a.Service.PreviewPublish(ctx, pluginId, siteId)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *PublishServiceAdapter) GetFileDiff(ctx context.Context, pluginId, siteId int64, filePath string) (*publish.FileDiffResult, *apperror.AppError) {
	result := a.Service.GetFileDiff(ctx, pluginId, siteId, filePath)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

// BackupServiceAdapter wraps *backup.Service to implement BackupServiceInterface
type BackupServiceAdapter struct {
	*backup.Service
}

func (a *BackupServiceAdapter) List(ctx context.Context, pluginId int64) ([]models.Backup, *apperror.AppError) {
	result := a.Service.List(ctx, pluginId)
	if result.HasError() {
		return nil, result.AppError()
	}
	return result.Items(), nil
}

func (a *BackupServiceAdapter) Create(ctx context.Context, pluginId, siteId int64) (*models.Backup, *apperror.AppError) {
	result := a.Service.Create(ctx, pluginId)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *BackupServiceAdapter) Restore(ctx context.Context, backupId int64) *apperror.AppError {
	result := a.Service.Restore(ctx, backupId)
	if result.HasError() {
		return result.AppError()
	}
	return nil
}

func (a *BackupServiceAdapter) Delete(ctx context.Context, backupId int64) *apperror.AppError {
	return a.Service.Delete(ctx, backupId)
}
