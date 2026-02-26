// Package handlers - Publish and Backup service interfaces and adapters
package handlers

import (
	"context"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/backup"
	"wp-plugin-publish/internal/services/publish"
)

// PublishServiceInterface defines publish service methods
type PublishServiceInterface interface {
	Publish(ctx context.Context, pluginID, siteID int64, opts publish.PublishOptions) (*publish.PublishResult, error)
	PublishFiles(ctx context.Context, pluginID, siteID int64, files []string) (*publish.PublishResult, error)
	PreviewPublish(ctx context.Context, pluginID, siteID int64) (*publish.PublishPreviewResult, error)
	GetFileDiff(ctx context.Context, pluginID, siteID int64, filePath string) (*publish.FileDiffResult, error)
}

// BackupServiceInterface defines backup service methods for HTTP handlers.
// Returns (T, error) tuples — the adapter unwraps Result types from the service layer.
type BackupServiceInterface interface {
	List(ctx context.Context, pluginID int64) ([]models.Backup, error)
	Create(ctx context.Context, pluginID, siteID int64) (*models.Backup, error)
	Restore(ctx context.Context, backupID int64) error
	Delete(ctx context.Context, backupID int64) error
}

// PublishServiceAdapter wraps *publish.Service to implement PublishServiceInterface
type PublishServiceAdapter struct {
	*publish.Service
}

func (a *PublishServiceAdapter) Publish(ctx context.Context, pluginID, siteID int64, opts publish.PublishOptions) (*publish.PublishResult, error) {
	result := a.Service.Publish(ctx, pluginID, siteID, opts)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *PublishServiceAdapter) PublishFiles(ctx context.Context, pluginID, siteID int64, files []string) (*publish.PublishResult, error) {
	result := a.Service.PublishFiles(ctx, pluginID, siteID, files)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *PublishServiceAdapter) PreviewPublish(ctx context.Context, pluginID, siteID int64) (*publish.PublishPreviewResult, error) {
	result := a.Service.PreviewPublish(ctx, pluginID, siteID)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *PublishServiceAdapter) GetFileDiff(ctx context.Context, pluginID, siteID int64, filePath string) (*publish.FileDiffResult, error) {
	result := a.Service.GetFileDiff(ctx, pluginID, siteID, filePath)
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

func (a *BackupServiceAdapter) List(ctx context.Context, pluginID int64) ([]models.Backup, error) {
	result := a.Service.List(ctx, pluginID)
	if result.HasError() {
		return nil, result.AppError()
	}
	return result.Items(), nil
}

func (a *BackupServiceAdapter) Create(ctx context.Context, pluginID, siteID int64) (*models.Backup, error) {
	result := a.Service.Create(ctx, pluginID)
	if result.HasError() {
		return nil, result.AppError()
	}
	v := result.Value()
	return &v, nil
}

func (a *BackupServiceAdapter) Restore(ctx context.Context, backupID int64) error {
	result := a.Service.Restore(ctx, backupID)
	if result.HasError() {
		return result.AppError()
	}
	return nil
}

func (a *BackupServiceAdapter) Delete(ctx context.Context, backupID int64) error {
	return a.Service.Delete(ctx, backupID)
}
